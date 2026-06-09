<?php

declare(strict_types=1);

namespace App\Copilot;

use App\Models\SeoPage;
use App\Models\SeoSearchConsoleMetric;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\SeoPresets\SiteAware\NicheEditorialRegistry;

final class PageModificationEvidenceService
{
    /**
     * @return array{
     *   page_title:?string,
     *   page_path:?string,
     *   word_count:?int,
     *   h2_headings:array<int,string>,
     *   gsc_queries:array<int,array{query:string,impressions:int,position:float}>,
     *   missing_topics:array<int,string>,
     *   faq_candidates:array<int,string>,
     *   section_gaps:array<int,string>,
     *   niche_key:string
     * }
     */
    public function gather(
        string $siteId,
        ?int $seoPageId,
        string $slug,
        ?string $primaryQuery,
        string $subject,
    ): array {
        $site = SeoSite::query()->where('site_id', $siteId)->first();
        $seoPage = $this->resolveSeoPage($siteId, $seoPageId, $slug);
        $observedPage = $this->resolveObservedPage($siteId, $seoPage, $slug);
        $snapshot = $this->latestSnapshot($observedPage);
        $h2Headings = $this->normalizeHeadings($snapshot?->h2_json ?? []);
        $pageTitle = trim((string) ($observedPage?->title ?: $seoPage?->title ?: ''));
        $contentHaystack = implode(' ', $h2Headings).' '.(string) ($snapshot?->content_text ?? '');
        $gscQueries = $this->topGscQueries(
            $siteId,
            $seoPage,
            $slug,
            $primaryQuery,
            $pageTitle,
            $subject,
            $contentHaystack,
        );
        $nicheProfile = $this->nicheProfile($site, $seoPage, $subject);
        $nicheKey = (string) ($nicheProfile['niche'] ?? 'generic');
        $relevantTopics = $this->relevantDepthTopics($nicheProfile, $slug, $subject, $gscQueries);
        $missingTopics = $this->missingTopics($relevantTopics, $h2Headings, $snapshot?->content_text ?? '');
        $sectionGaps = array_map(
            fn (string $topic): string => 'Section manquante : '.$topic,
            array_slice($missingTopics, 0, 3),
        );
        $faqCandidates = $this->faqCandidates(
            $nicheKey,
            $nicheProfile,
            $slug,
            $subject,
            $gscQueries,
            $primaryQuery,
            $missingTopics,
        );

        return [
            'page_title' => $observedPage?->title ?: $seoPage?->title,
            'page_path' => $observedPage?->path,
            'word_count' => $snapshot?->word_count ?? $observedPage?->latest_word_count,
            'h2_headings' => $h2Headings,
            'gsc_queries' => $gscQueries,
            'missing_topics' => $missingTopics,
            'faq_candidates' => $faqCandidates,
            'section_gaps' => $sectionGaps,
            'niche_key' => $nicheKey,
        ];
    }

    private function resolveSeoPage(string $siteId, ?int $seoPageId, string $slug): ?SeoPage
    {
        if ($seoPageId) {
            return SeoPage::query()->where('site_id', $siteId)->whereKey($seoPageId)->first();
        }

        if ($slug === '') {
            return null;
        }

        return SeoPage::query()
            ->where('site_id', $siteId)
            ->where('slug', $slug)
            ->first();
    }

    private function resolveObservedPage(string $siteId, ?SeoPage $seoPage, string $slug): ?SeoSitePage
    {
        if ($seoPage?->observed_site_page_id) {
            return SeoSitePage::query()->find($seoPage->observed_site_page_id);
        }

        if ($slug === '') {
            return SeoSitePage::query()
                ->where('site_id', $siteId)
                ->where(function ($query): void {
                    $query->where('path', '/')->orWhere('path', '');
                })
                ->orderByDesc('last_seen_at')
                ->first();
        }

        return SeoSitePage::query()
            ->where('site_id', $siteId)
            ->where('path', '/'.ltrim($slug, '/'))
            ->orderByDesc('last_seen_at')
            ->first();
    }

    private function latestSnapshot(?SeoSitePage $observedPage): ?SeoSitePageSnapshot
    {
        if (! $observedPage) {
            return null;
        }

        return SeoSitePageSnapshot::query()
            ->where('site_page_id', $observedPage->id)
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int,string>
     */
    private function normalizeHeadings(mixed $headings): array
    {
        return collect(is_array($headings) ? $headings : [])
            ->map(fn (mixed $heading): string => trim(strip_tags((string) $heading)))
            ->filter(fn (string $heading): bool => $heading !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int,array{query:string,impressions:int,position:float}>
     */
    private function topGscQueries(
        string $siteId,
        ?SeoPage $seoPage,
        string $slug,
        ?string $primaryQuery,
        string $pageTitle,
        string $subject,
        string $contentHaystack,
    ): array {
        $query = SeoSearchConsoleMetric::query()
            ->where('site_id', $siteId)
            ->where('window_days', 28)
            ->whereNotNull('query')
            ->where('query', '!=', '');

        if ($seoPage) {
            $query->where('seo_page_id', $seoPage->id);
        } elseif ($slug !== '') {
            $path = '/'.ltrim($slug, '/');
            $site = SeoSite::query()->where('site_id', $siteId)->first();
            $base = rtrim((string) ($site?->url ?? ''), '/');
            $query->where(function ($builder) use ($path, $base): void {
                $builder->where('url', 'like', '%'.$path);
                if ($base !== '') {
                    $builder->orWhere('url', $base.$path);
                }
            });
        }

        $rows = $query
            ->orderByDesc('metric_date')
            ->orderByDesc('impressions')
            ->limit(24)
            ->get()
            ->unique(fn (SeoSearchConsoleMetric $metric): string => mb_strtolower((string) $metric->query))
            ->values();

        $rows = $rows
            ->map(function (SeoSearchConsoleMetric $metric) use ($slug, $pageTitle, $subject, $contentHaystack): array {
                $impressions = (int) round((float) $metric->impressions);
                $relevance = $this->queryPageRelevance(
                    (string) $metric->query,
                    $slug,
                    $pageTitle,
                    $subject,
                    $contentHaystack,
                );

                return [
                    'metric' => $metric,
                    'impressions' => $impressions,
                    'relevance' => $relevance,
                ];
            })
            ->filter(function (array $row): bool {
                return $row['relevance'] >= 2
                    || ($row['relevance'] >= 1 && $row['impressions'] >= 8)
                    || $row['impressions'] >= 20;
            })
            ->sortByDesc(function (array $row) use ($primaryQuery): float {
                $queryText = mb_strtolower((string) $row['metric']->query);
                $needle = $primaryQuery !== null ? mb_strtolower(trim($primaryQuery)) : '';
                $primaryBoost = $needle !== '' && $queryText === $needle ? 1_000_000 : 0;

                return $primaryBoost + ($row['relevance'] * 1_000) + $row['impressions'];
            })
            ->values();

        return $rows
            ->take(6)
            ->map(fn (array $row): array => [
                'query' => (string) $row['metric']->query,
                'impressions' => $row['impressions'],
                'position' => round((float) $row['metric']->position, 1),
                'relevance' => $row['relevance'],
            ])
            ->all();
    }

    private function queryPageRelevance(
        string $query,
        string $slug,
        string $pageTitle,
        string $subject,
        string $contentHaystack,
    ): int {
        $pageHaystack = mb_strtolower(trim($slug.' '.$pageTitle.' '.$subject.' '.$contentHaystack));
        $queryHaystack = mb_strtolower($query);
        $tokens = $this->topicTokens($query);
        $score = collect($tokens)->sum(
            fn (string $token): int => str_contains($pageHaystack, $token) || str_contains($queryHaystack, $token) ? 1 : 0,
        );

        if (str_contains($pageHaystack, 'faq')) {
            foreach (['repérage', 'reperage', 'délai', 'delai', 'dt', 'copropri', 'diagnostic', 'chantier', 'oblig', 'faq', 'question'] as $intent) {
                if (str_contains($queryHaystack, $intent)) {
                    $score += 2;
                }
            }

            if (str_contains($queryHaystack, 'logiciel') && ! str_contains($pageHaystack, 'logiciel')) {
                $score -= 3;
            }
        }

        if (str_contains($pageHaystack, 'reference') || str_contains($pageHaystack, 'reglement')) {
            foreach (['reglement', 'réglement', 'ss3', 'ss4', 'repérage', 'reperage', 'dt', 'code'] as $intent) {
                if (str_contains($queryHaystack, $intent)) {
                    $score += 2;
                }
            }
        }

        return max(0, $score);
    }

    /**
     * @return array<string,mixed>
     */
    private function nicheProfile(?SeoSite $site, ?SeoPage $seoPage, string $subject): array
    {
        $settings = is_array($site?->settings_json) ? $site->settings_json : [];
        $profile = is_array($settings['site_profile'] ?? null) ? $settings['site_profile'] : [];
        $vocabulary = collect((array) ($profile['vocabulary_terms'] ?? []))
            ->merge((array) ($profile['editorial_topics'] ?? []))
            ->filter(fn (mixed $term): bool => is_string($term) && trim($term) !== '')
            ->values()
            ->all();

        return NicheEditorialRegistry::resolve(
            (string) ($site?->niche ?? 'generic'),
            (string) ($seoPage?->keyword ?: $subject),
            $vocabulary,
        );
    }

    /**
     * @param  array<string,mixed>  $nicheProfile
     * @param  array<int,array{query:string,impressions:int,position:float}>  $gscQueries
     * @return array<int,string>
     */
    private function relevantDepthTopics(
        array $nicheProfile,
        string $slug,
        string $subject,
        array $gscQueries,
    ): array {
        $topics = collect((array) ($nicheProfile['depth_topics'] ?? []))
            ->merge((array) ($nicheProfile['composition'] ?? []))
            ->filter(fn (mixed $topic): bool => is_string($topic) && trim($topic) !== '')
            ->values();

        $haystack = mb_strtolower($slug.' '.$subject.' '.collect($gscQueries)->pluck('query')->implode(' '));

        $scored = $topics
            ->map(function (string $topic) use ($haystack): array {
                $tokens = $this->topicTokens($topic);
                $score = collect($tokens)->sum(fn (string $token): int => str_contains($haystack, $token) ? 1 : 0);

                return ['topic' => $topic, 'score' => $score];
            })
            ->sortByDesc('score')
            ->values();

        $matched = $scored->filter(fn (array $row): bool => ($row['score'] ?? 0) > 0)->pluck('topic');
        $fallback = $scored->take(4)->pluck('topic');

        return $matched->isNotEmpty()
            ? $matched->take(6)->all()
            : $fallback->all();
    }

    /**
     * @param  array<int,string>  $topics
     * @param  array<int,string>  $h2Headings
     * @return array<int,string>
     */
    private function missingTopics(array $topics, array $h2Headings, string $contentText): array
    {
        $contentHaystack = mb_strtolower(implode(' ', $h2Headings).' '.$contentText);

        return collect($topics)
            ->filter(function (string $topic) use ($contentHaystack): bool {
                $tokens = $this->topicTokens($topic);
                if ($tokens === []) {
                    return true;
                }

                $matched = collect($tokens)->filter(fn (string $token): bool => str_contains($contentHaystack, $token))->count();

                return $matched < max(1, (int) ceil(count($tokens) * 0.34));
            })
            ->values()
            ->take(4)
            ->all();
    }

    /**
     * @param  array<string,mixed>  $nicheProfile
     * @param  array<int,array{query:string,impressions:int,position:float}>  $gscQueries
     * @param  array<int,string>  $missingTopics
     * @return array<int,string>
     */
    private function faqCandidates(
        string $nicheKey,
        array $nicheProfile,
        string $slug,
        string $subject,
        array $gscQueries,
        ?string $primaryQuery,
        array $missingTopics,
    ): array {
        $faq = collect();

        foreach ($gscQueries as $row) {
            $faq->push($this->queryToFaqQuestion((string) $row['query']));
        }

        if ($primaryQuery !== null && trim($primaryQuery) !== '') {
            $faq->prepend($this->queryToFaqQuestion($primaryQuery));
        }

        foreach ($this->pageSpecificFaq($nicheKey, $slug, $subject, $nicheProfile) as $question) {
            $faq->push($question);
        }

        foreach (array_slice($missingTopics, 0, 2) as $topic) {
            $faq->push($this->topicToFaqQuestion($topic));
        }

        foreach (array_slice((array) ($nicheProfile['mistakes'] ?? []), 0, 2) as $mistake) {
            if (is_string($mistake) && trim($mistake) !== '' && $this->mistakeMatchesPage($mistake, $slug, $subject)) {
                $faq->push('Comment éviter l’erreur suivante : '.$this->lowerSentence(trim($mistake)).' ?');
            }
        }

        return $faq
            ->map(fn (string $question): string => trim($question))
            ->filter(fn (string $question): bool => $question !== '' && mb_strlen($question) >= 12)
            ->unique()
            ->values()
            ->take(4)
            ->all();
    }

    /**
     * @param  array<string,mixed>  $nicheProfile
     * @return array<int,string>
     */
    private function pageSpecificFaq(
        string $nicheKey,
        string $slug,
        string $subject,
        array $nicheProfile,
    ): array {
        $slugHaystack = mb_strtolower($slug.' '.$subject);

        return match ($nicheKey) {
            'amiante' => match (true) {
                str_contains($slugHaystack, 'faq') => [
                    'Quelles questions posent le plus souvent les donneurs d’ordre avant un repérage amiante ?',
                    'Quel délai laisser entre repérage et ouverture de chantier ?',
                    'Qui met à jour le DTA quand plusieurs entreprises interviennent en copropriété ?',
                ],
                str_contains($slugHaystack, 'copropri') => [
                    'Comment organiser le repérage amiante quand plusieurs lots sont concernés ?',
                    'Qui informe le syndic, les occupants et les entreprises avant ouverture chantier ?',
                    'Quels documents doivent être alignés entre versions du DTA ?',
                ],
                str_contains($slugHaystack, 'diagnostic') || str_contains($slugHaystack, 'reperage') || str_contains($slugHaystack, 'repérage') => [
                    'Quelle différence entre diagnostic global et repérage avant travaux sur la zone réelle ?',
                    'Quels éléments doivent figurer dans le rapport pour sécuriser le phasage chantier ?',
                    'Qui valide le périmètre de repérage côté donneur d’ordre ?',
                ],
                str_contains($slugHaystack, 'ss3') || str_contains($slugHaystack, 'ss4') => [
                    'Quand faut-il mobiliser une ingénierie SS3 ou SS4 sur le dossier amiante ?',
                    'Quels livrables documentaires attendre avant de lancer les travaux ?',
                ],
                default => [
                    'Quelles obligations réglementaires doivent être rappelées avant travaux amiante ?',
                    'Quels délais et responsabilités doivent être explicités pour le donneur d’ordre ?',
                ],
            },
            default => collect((array) ($nicheProfile['arbitrages'] ?? []))
                ->filter(fn (mixed $line): bool => is_string($line) && trim($line) !== '')
                ->map(fn (string $line): string => 'Comment trancher : '.$this->lowerSentence(trim($line)).' ?')
                ->take(2)
                ->all(),
        };
    }

    private function queryToFaqQuestion(string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return '';
        }

        if (str_ends_with($query, '?')) {
            return $this->sentenceCase($query);
        }

        if (preg_match('/^(comment|pourquoi|qui|quel|quelle|quels|quelles|combien|quand|où|ou)\b/iu', $query) === 1) {
            return $this->sentenceCase($query).'?';
        }

        return sprintf('Que doit comprendre un client qui tape « %s » avant de vous contacter ?', $query);
    }

    private function topicToFaqQuestion(string $topic): string
    {
        $topic = trim($topic);
        if ($topic === '') {
            return '';
        }

        if (preg_match('/^(comment|pourquoi|qui|quel|quelle|quels|quelles|combien|quand)\b/iu', $topic) === 1) {
            return $this->sentenceCase(rtrim($topic, '?')).'?';
        }

        return sprintf('Comment traiter clairement : %s ?', $this->lowerSentence($topic));
    }

    private function sentenceCase(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }

    private function lowerSentence(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return mb_strtolower(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }

    private function mistakeMatchesPage(string $mistake, string $slug, string $subject): bool
    {
        $haystack = mb_strtolower($slug.' '.$subject);
        $tokens = $this->topicTokens($mistake);

        if ($tokens === []) {
            return true;
        }

        return collect($tokens)->contains(fn (string $token): bool => str_contains($haystack, $token));
    }

    /**
     * @return array<int,string>
     */
    private function topicTokens(string $topic): array
    {
        return collect(preg_split('/[^a-zàâäçéèêëîïôùûüœ0-9]+/iu', mb_strtolower($topic)) ?: [])
            ->filter(fn (string $token): bool => mb_strlen($token) >= 4)
            ->reject(fn (string $token): bool => in_array($token, ['avec', 'dans', 'pour', 'sans', 'entre', 'avant', 'apres', 'après', 'cette', 'cette', 'votre', 'leurs'], true))
            ->values()
            ->all();
    }
}
