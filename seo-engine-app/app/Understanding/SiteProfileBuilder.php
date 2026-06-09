<?php

declare(strict_types=1);

namespace App\Understanding;

use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Models\SeoSiteSchema;
use App\ObservedSite\BusinessPageRelevanceFilter;
use App\Recommendations\BusinessIntentService;
use Illuminate\Support\Str;

final class SiteProfileBuilder
{
    private const STOPWORDS = [
        'avec', 'dans', 'pour', 'plus', 'tout', 'tous', 'toute', 'toutes', 'cette', 'cela', 'comme',
        'vous', 'nous', 'elle', 'elles', 'leur', 'leurs', 'chez', 'sans', 'sous', 'entre', 'mais',
        'the', 'and', 'for', 'with', 'your', 'from', 'that', 'this', 'have', 'will', 'page', 'site',
        'accueil', 'home', 'contact', 'mentions', 'legales', 'politique', 'cookies', 'blog',
    ];

    public function __construct(
        private readonly BusinessIntentService $businessIntent,
        private readonly BusinessPageRelevanceFilter $businessPages,
    ) {}

    /**
     * @param  array<string,mixed>  $understanding
     * @return array<string,mixed>
     */
    public function build(SeoSite $site, ?int $crawlId = null, array $understanding = []): array
    {
        $pages = $this->businessPages->filterObservedPages(
            SeoSitePage::query()
                ->where('site_id', $site->site_id)
                ->businessRelevant()
                ->whereNotNull('last_snapshot_id')
                ->orderByDesc('authority_score')
                ->orderByDesc('pillar_likelihood')
                ->limit(80)
                ->get(),
            $site->site_id,
        )->take(40);

        $snapshots = SeoSitePageSnapshot::query()
            ->where('site_id', $site->site_id)
            ->whereIn('id', $pages->pluck('last_snapshot_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $schemas = SeoSiteSchema::query()
            ->where('site_id', $site->site_id)
            ->when($crawlId, fn ($q) => $q->where('site_crawl_id', $crawlId))
            ->orderByDesc('observed_at')
            ->limit(50)
            ->get();

        $homepage = $this->resolveHomepage($pages, $site);
        $homepageSnapshot = $homepage ? $snapshots->get($homepage->last_snapshot_id) : null;
        $services = $this->extractServices($pages, $snapshots);
        $mainPages = $this->extractMainPages($pages, $understanding);
        $vocabulary = $this->extractVocabulary($pages, $snapshots, $site);
        $geography = $this->extractGeography($site, $schemas, $homepageSnapshot);
        $audience = $this->extractAudience($pages, $snapshots, $vocabulary);
        $businessSummary = $this->buildBusinessSummary($site, $homepage, $homepageSnapshot, $services);

        $locale = trim((string) $site->locale) ?: 'fr';
        $language = str_starts_with(strtolower($locale), 'en') ? 'en' : 'fr';

        return [
            'version' => 'v1',
            'status' => $this->resolveStatus($pages, $businessSummary),
            'generated_at' => now()->toIso8601String(),
            'source_crawl_id' => $crawlId,
            'business' => [
                'summary' => $businessSummary,
                'industry' => $this->resolveIndustry($site, $vocabulary),
                'positioning' => $this->resolvePositioning($site, $homepageSnapshot),
            ],
            'services' => $services,
            'vocabulary' => $vocabulary,
            'main_pages' => $mainPages,
            'geography' => $geography,
            'audience' => $audience,
            'generation_directives' => [
                'language' => $language,
                'locale' => $locale,
                'site_name' => $site->name,
                'site_url' => $site->url,
                'niche' => $site->niche,
                'forbid_english' => $language === 'fr',
                'forbid_saas_template' => true,
                'forbid_generic_sections' => true,
                'must_reference_site_services' => count($services) > 0,
                'must_use_core_vocabulary' => count($vocabulary['core_terms'] ?? []) > 0,
            ],
        ];
    }

    private function resolveStatus($pages, string $summary): string
    {
        if ($pages->isEmpty() || trim($summary) === '') {
            return 'insufficient_data';
        }

        return 'ready';
    }

    private function resolveHomepage($pages, SeoSite $site): ?SeoSitePage
    {
        $base = rtrim((string) $site->url, '/');

        return $pages->first(fn (SeoSitePage $page): bool => rtrim((string) $page->normalized_url, '/') === $base)
            ?? $pages->first(fn (SeoSitePage $page): bool => in_array(trim((string) $page->path, '/'), ['', '/'], true))
            ?? $pages->sortBy('path')->first();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function extractServices($pages, $snapshots): array
    {
        $services = [];

        foreach ($pages as $page) {
            $snapshot = $snapshots->get($page->last_snapshot_id);
            $intent = $this->businessIntent->classify($this->pagePayload($page, $snapshot));

            if (! in_array($intent['intent_type'], ['MONEY_PAGE', 'CONVERSION_PAGE'], true)) {
                continue;
            }

            $h2 = collect($snapshot?->h2_json ?? [])->filter()->take(3)->values()->all();
            $services[] = [
                'name' => trim((string) ($page->primary_h1 ?: $page->title ?: $page->path)),
                'description' => trim((string) ($page->meta_description ?: Str::limit((string) ($snapshot?->content_text ?? ''), 220))),
                'source_url' => $page->normalized_url,
                'headings' => $h2,
                'intent' => $intent['intent_type'],
            ];
        }

        return collect($services)->unique('source_url')->take(12)->values()->all();
    }

    /**
     * @param  array<string,mixed>  $understanding
     * @return array<int,array<string,mixed>>
     */
    private function extractMainPages($pages, array $understanding): array
    {
        $pillarUrls = collect($understanding['pillar_pages'] ?? [])
            ->pluck('url')
            ->filter()
            ->all();

        return $pages
            ->sortByDesc(fn (SeoSitePage $page): float => (float) $page->authority_score + (float) $page->pillar_likelihood)
            ->take(12)
            ->map(function (SeoSitePage $page) use ($pillarUrls): array {
                $role = 'content';
                if (in_array($page->normalized_url, $pillarUrls, true)) {
                    $role = 'pillar';
                } elseif ((float) $page->pillar_likelihood >= 0.6) {
                    $role = 'pillar';
                } elseif (str_contains(strtolower((string) $page->path), 'contact')) {
                    $role = 'contact';
                }

                return [
                    'url' => $page->normalized_url,
                    'title' => $page->title,
                    'path' => $page->path,
                    'role' => $role,
                    'cluster' => $page->cluster_label,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function extractVocabulary($pages, $snapshots, SeoSite $site): array
    {
        $terms = [];

        foreach ($pages as $page) {
            $snapshot = $snapshots->get($page->last_snapshot_id);
            $blob = implode(' ', array_filter([
                $page->title,
                $page->meta_description,
                $page->primary_h1,
                $page->cluster_label,
                implode(' ', $snapshot?->h1_json ?? []),
                implode(' ', $snapshot?->h2_json ?? []),
            ]));
            foreach ($this->tokenize($blob) as $token) {
                $terms[$token] = ($terms[$token] ?? 0) + 1;
            }
        }

        arsort($terms);
        $core = array_slice(array_keys(array_filter($terms, fn (int $count): bool => $count >= 2)), 0, 25);

        return [
            'core_terms' => $core,
            'forbidden_generic' => [
                'Field example',
                'innovative solution',
                'SaaS knowledge base',
                'Operational context',
                'Implementation checklist',
                'professional SaaS',
            ],
            'tone' => $site->niche === 'amiante' ? 'expert réglementaire' : 'expert métier',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function extractGeography(SeoSite $site, $schemas, ?SeoSitePageSnapshot $homepage): array
    {
        $regions = [];
        $evidence = [];

        foreach ($schemas as $schema) {
            $json = $schema->schema_json ?? [];
            foreach (['addressLocality', 'addressRegion', 'areaServed'] as $key) {
                $value = data_get($json, $key) ?? data_get($json, 'address.'.$key);
                if (is_string($value) && trim($value) !== '') {
                    $regions[] = trim($value);
                    $evidence[] = 'schema:'.$schema->schema_type.'.'.$key;
                }
            }
        }

        if (preg_match('/\b(paris|lyon|marseille|toulouse|bordeaux|lille|nantes|strasbourg)\b/i', (string) ($homepage?->content_text ?? ''), $match)) {
            $regions[] = ucfirst(strtolower($match[1]));
            $evidence[] = 'homepage:city_mention';
        }

        $host = parse_url((string) $site->url, PHP_URL_HOST) ?: '';
        if (str_ends_with($host, '.fr')) {
            $evidence[] = 'domain:.fr';
        }

        $scope = count($regions) <= 1 ? 'local' : (count($regions) <= 3 ? 'regional' : 'national');

        return [
            'scope' => $scope,
            'regions' => array_values(array_unique($regions)),
            'evidence' => array_values(array_unique($evidence)),
        ];
    }

    /**
     * @param  array<string,mixed>  $vocabulary
     * @return array<string,mixed>
     */
    private function extractAudience($pages, $snapshots, array $vocabulary): array
    {
        $haystack = strtolower(implode(' ', $vocabulary['core_terms'] ?? []));

        $segments = [];

        if (str_contains($haystack, 'copropri') || str_contains($haystack, 'syndic')) {
            $segments[] = ['label' => 'Copropriétés et syndics', 'needs' => ['conformité', 'planning'], 'signals' => ['copropriété', 'syndic']];
        }
        if (str_contains($haystack, 'entreprise') || str_contains($haystack, 'industriel') || str_contains($haystack, 'erp')) {
            $segments[] = ['label' => 'Professionnels et entreprises', 'needs' => ['conformité', 'coordination chantier'], 'signals' => ['entreprise', 'ERP']];
        }
        if (str_contains($haystack, 'particulier') || str_contains($haystack, 'maison')) {
            $segments[] = ['label' => 'Particuliers', 'needs' => ['diagnostic', 'accompagnement'], 'signals' => ['particulier', 'maison']];
        }

        if ($segments === []) {
            $segments[] = [
                'label' => 'Décideurs et équipes opérationnelles',
                'needs' => ['clarté', 'mise en oeuvre'],
                'signals' => array_slice($vocabulary['core_terms'] ?? [], 0, 5),
            ];
        }

        return ['segments' => $segments];
    }

    private function buildBusinessSummary(SeoSite $site, ?SeoSitePage $homepage, ?SeoSitePageSnapshot $snapshot, array $services): string
    {
        $parts = array_filter([
            trim((string) $site->name),
            trim((string) ($homepage?->meta_description ?? $snapshot?->meta_description ?? '')),
        ]);

        if ($services !== []) {
            $serviceNames = collect($services)->pluck('name')->take(4)->implode(', ');
            $parts[] = 'Services observés : '.$serviceNames.'.';
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param  array<string,mixed>  $vocabulary
     */
    private function resolveIndustry(SeoSite $site, array $vocabulary): string
    {
        if ($site->niche !== '' && $site->niche !== 'general') {
            return $site->niche;
        }

        $terms = implode(' ', $vocabulary['core_terms'] ?? []);

        return match (true) {
            str_contains($terms, 'amiante') => 'amiante',
            str_contains($terms, 'desamiant') => 'désamiantage',
            default => 'activité locale',
        };
    }

    private function resolvePositioning(SeoSite $site, ?SeoSitePageSnapshot $homepage): string
    {
        $title = trim((string) ($homepage?->title ?? $site->name));

        return $title !== '' ? $title : (string) $site->name;
    }

    /**
     * @return array<string,mixed>
     */
    private function pagePayload(SeoSitePage $page, ?SeoSitePageSnapshot $snapshot): array
    {
        return [
            'url' => $page->normalized_url,
            'title' => $page->title,
            'meta_description' => $page->meta_description,
            'h1' => $page->primary_h1,
            'cluster' => $page->cluster_label,
            'content' => $snapshot?->content_text,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function tokenize(string $text): array
    {
        $normalized = Str::lower(Str::ascii($text));
        preg_match_all('/[a-z][a-z0-9\-]{2,}/', $normalized, $matches);

        return array_values(array_filter(
            $matches[0] ?? [],
            fn (string $token): bool => ! in_array($token, self::STOPWORDS, true) && strlen($token) >= 4,
        ));
    }
}
