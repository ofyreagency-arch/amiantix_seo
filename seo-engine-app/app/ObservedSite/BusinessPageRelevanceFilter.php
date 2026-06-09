<?php

declare(strict_types=1);

namespace App\ObservedSite;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class BusinessPageRelevanceFilter
{
    /** @var array<int,string> */
    private const PATH_FRAGMENTS = [
        'slug-test',
        'slug_test',
        'bridge-lab',
        'bridge_lab',
        'bridge-test',
        'bridge_test',
        'bridge-lab-test',
        'symfony-bridge',
        'praeviseo',
        '/e2e',
        '-e2e',
        '_e2e',
        'sandbox',
        'validation',
        'mot-de-passe',
        'mot_de_passe',
        'forgot-password',
        'reset-password',
        '/login',
    ];

    /** @var array<int,string> */
    private const PATH_SEGMENTS = [
        'e2e',
        'sandbox',
        'validation',
        'lab',
        'login',
        'connexion',
        'auth',
        'register',
        'signup',
        'account',
        'oublie',
    ];

    /** @var array<int,string> */
    private const TEXT_PATTERNS = [
        '/\btest\s+bridge\b/i',
        '/\bbridge\s+.*\bpraeviseo\b/i',
        '/\bpraeviseo\b/i',
        '/\be2e\b/i',
        '/\bbridge[\s\-_]?lab\b/i',
        '/\bslug[\s\-_]?test\b/i',
        '/\bsandbox\b/i',
        '/\bvalidation\s+(page|process|bridge|lab)\b/i',
        '/\bguide\s+to\s+reviewing\b/i',
        '/\bfield\s+example\b/i',
        '/\bmot\s+de\s+passe\s+oubli[eé]\b/i',
        '/\bforgot\s+password\b/i',
        '/\breset\s+password\b/i',
    ];

    /** @var array<int,string> */
    private const KEYWORD_TOKENS = [
        'bridge',
        'e2e',
        'sandbox',
        'validation',
        'praeviseo',
    ];

    public function isRelevantUrl(string $url): bool
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? '/'));

        return ! $this->pathIsTechnical($path);
    }

    public function isRelevantObservedPage(SeoSitePage $page, ?SeoSitePageSnapshot $snapshot = null): bool
    {
        if ((string) $page->indexability_state === 'excluded_technical') {
            return false;
        }

        if ($this->pathIsTechnical((string) $page->path)) {
            return false;
        }

        return ! $this->textIsTechnical($this->observedPageHaystack($page, $snapshot));
    }

    public function isRelevantSeoPage(SeoPage $page): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $page->slug,
            $page->keyword,
            $page->title,
            $page->h1,
            $page->meta_description,
            Str::limit(strip_tags((string) $page->content), 500),
        ])));

        if ($this->pathIsTechnical('/'.trim((string) $page->slug, '/'))) {
            return false;
        }

        return ! $this->textIsTechnical($haystack);
    }

    /**
     * @param  Collection<int,SeoSitePage>  $pages
     * @return Collection<int,SeoSitePage>
     */
    public function filterObservedPages(Collection $pages, ?string $siteId = null): Collection
    {
        if ($pages->isEmpty()) {
            return $pages;
        }

        $snapshots = SeoSitePageSnapshot::query()
            ->whereIn('id', $pages->pluck('last_snapshot_id')->filter()->all())
            ->when($siteId, fn (Builder $query) => $query->where('site_id', $siteId))
            ->get()
            ->keyBy('id');

        return $pages
            ->filter(fn (SeoSitePage $page): bool => $this->isRelevantObservedPage(
                $page,
                $snapshots->get($page->last_snapshot_id),
            ))
            ->values();
    }

    /**
     * @param  callable(Builder):void|null  $constraint
     * @return Collection<int,SeoSitePage>
     */
    public function loadObservedPages(string $siteId, ?callable $constraint = null): Collection
    {
        $query = SeoSitePage::query()
            ->where('site_id', $siteId)
            ->businessRelevant();

        if ($constraint !== null) {
            $constraint($query);
        }

        return $this->filterObservedPages($query->get(), $siteId);
    }

    /**
     * @param  Builder<SeoPage>  $query
     */
    public function firstRelevantSeoPage(Builder $query): ?SeoPage
    {
        return $query
            ->get()
            ->first(fn (SeoPage $page): bool => $this->isRelevantSeoPage($page));
    }

    public function constrainObservedQuery(Builder $query): Builder
    {
        $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('indexability_state')
                ->orWhere('indexability_state', '!=', 'excluded_technical');
        });

        foreach (self::PATH_FRAGMENTS as $fragment) {
            $query->where(function (Builder $builder) use ($fragment): void {
                $builder
                    ->whereNull('path')
                    ->orWhere('path', 'not like', '%'.$fragment.'%');
            });
        }

        return $query;
    }

    public function markExcludedTechnicalPages(SeoSite $site): int
    {
        $pages = SeoSitePage::query()
            ->where('site_id', $site->site_id)
            ->get();

        $snapshots = SeoSitePageSnapshot::query()
            ->where('site_id', $site->site_id)
            ->whereIn('id', $pages->pluck('last_snapshot_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $marked = 0;

        foreach ($pages as $page) {
            $snapshot = $snapshots->get($page->last_snapshot_id);
            if ($this->isRelevantObservedPage($page, $snapshot)) {
                continue;
            }

            if ((string) $page->indexability_state === 'excluded_technical') {
                continue;
            }

            $page->forceFill([
                'indexability_state' => 'excluded_technical',
                'cluster_label' => 'technical_excluded',
            ])->save();
            $marked++;
        }

        return $marked;
    }

    /**
     * @return array{relevant:bool,reason:?string}
     */
    public function inspectObservedPage(SeoSitePage $page, ?SeoSitePageSnapshot $snapshot = null): array
    {
        if ((string) $page->indexability_state === 'excluded_technical') {
            return ['relevant' => false, 'reason' => 'excluded_technical'];
        }

        if ($this->pathIsTechnical((string) $page->path)) {
            return ['relevant' => false, 'reason' => 'technical_path'];
        }

        $reason = $this->technicalTextReason($this->observedPageHaystack($page, $snapshot));
        if ($reason !== null) {
            return ['relevant' => false, 'reason' => $reason];
        }

        return ['relevant' => true, 'reason' => null];
    }

    private function pathIsTechnical(string $path): bool
    {
        $normalized = strtolower(trim($path));
        if ($normalized === '') {
            return false;
        }

        foreach (self::PATH_FRAGMENTS as $fragment) {
            if (str_contains($normalized, strtolower($fragment))) {
                return true;
            }
        }

        $segments = array_values(array_filter(explode('/', trim($normalized, '/'))));
        foreach ($segments as $segment) {
            if (in_array($segment, self::PATH_SEGMENTS, true)) {
                return true;
            }
            if (preg_match('/^(test|bridge|e2e|sandbox|validation|lab)[\-_]/', $segment) === 1) {
                return true;
            }
            if (preg_match('/[\-_](test|bridge|e2e|sandbox|validation|lab)$/', $segment) === 1) {
                return true;
            }
        }

        return false;
    }

    private function textIsTechnical(string $haystack): bool
    {
        return $this->technicalTextReason($haystack) !== null;
    }

    private function technicalTextReason(string $haystack): ?string
    {
        $normalized = strtolower(trim($haystack));
        if ($normalized === '') {
            return null;
        }

        foreach (self::TEXT_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return 'pattern:'.trim($pattern, '/');
            }
        }

        if (preg_match('/\b(test|bridge|e2e|sandbox|validation|lab)\b/i', $normalized) === 1) {
            foreach (self::KEYWORD_TOKENS as $token) {
                if (preg_match('/\b'.preg_quote($token, '/').'\b/i', $normalized) === 1) {
                    return 'keyword:'.$token;
                }
            }
            if (preg_match('/\btest\b/i', $normalized) === 1) {
                return 'keyword:test';
            }
            if (preg_match('/\blab\b/i', $normalized) === 1) {
                return 'keyword:lab';
            }
        }

        return null;
    }

    private function observedPageHaystack(SeoSitePage $page, ?SeoSitePageSnapshot $snapshot): string
    {
        return strtolower(implode(' ', array_filter([
            $page->path,
            $page->title,
            $page->meta_description,
            $page->primary_h1,
            $page->cluster_label,
            implode(' ', $snapshot?->h1_json ?? []),
            implode(' ', $snapshot?->h2_json ?? []),
            Str::limit((string) ($snapshot?->content_text ?? ''), 400),
        ])));
    }
}
