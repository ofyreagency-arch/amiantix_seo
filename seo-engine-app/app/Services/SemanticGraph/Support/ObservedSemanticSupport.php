<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph\Support;

use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ObservedSemanticSupport
{
    public function normalizeUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '/');
        $path = $path === '' ? '/' : $path;
        $path = $path !== '/' ? rtrim($path, '/') : '/';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$path.$query;
    }

    /**
     * @return array<int,string>
     */
    public function intentTokens(SeoSitePage $page, ?SeoSitePageSnapshot $snapshot = null): array
    {
        $source = implode(' ', array_filter([
            $page->path,
            $page->title,
            $page->primary_h1,
            implode(' ', $snapshot?->h2_json ?? []),
            implode(' ', $snapshot?->h3_json ?? []),
        ]));

        return $this->significantTokens($source);
    }

    public function intentSimilarity(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $leftCollection = collect($left);
        $intersection = $leftCollection->intersect($right)->count();
        $union = $leftCollection->merge($right)->unique()->count();

        return $union > 0 ? round($intersection / $union, 4) : 0.0;
    }

    public function pageLabel(SeoSitePage $page): string
    {
        return trim((string) ($page->title ?: $page->primary_h1 ?: $page->path ?: $page->normalized_url));
    }

    /**
     * @return array<int,string>
     */
    public function significantTokens(string $text): array
    {
        return collect(preg_split('/[^a-z0-9]+/i', Str::lower(Str::ascii($text))) ?: [])
            ->map(static fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => strlen($token) >= 4)
            ->reject(fn (string $token): bool => in_array($token, [
                'https', 'http', 'html', 'page', 'avec', 'pour', 'dans', 'site',
                'from', 'that', 'your', 'vous', 'plus', 'sans', 'tout', 'this',
            ], true))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int,SeoSitePageSnapshot>  $snapshots
     */
    public function snapshotForPage(SeoSitePage $page, Collection $snapshots): ?SeoSitePageSnapshot
    {
        return $page->last_snapshot_id ? $snapshots->get($page->last_snapshot_id) : null;
    }
}
