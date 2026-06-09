<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;

final class SiteAwareInternalLinkProvider implements InternalLinkProvider
{
    public function clusterForKeyword(string $keyword): string
    {
        $profile = SiteProfilePromptContext::profile() ?? [];
        $pages = is_array($profile['main_pages'] ?? null) ? $profile['main_pages'] : [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            $cluster = trim((string) ($page['cluster'] ?? ''));
            if ($cluster !== '' && str_contains(Str::lower($keyword), Str::lower($cluster))) {
                return $cluster;
            }
        }

        return (string) data_get($profile, 'business.industry', 'site');
    }

    /**
     * @return array<int,array{label:string,url:string,reason:string}>
     */
    public function linksFor(object $page): array
    {
        $profile = SiteProfilePromptContext::profile() ?? [];
        $pages = is_array($profile['main_pages'] ?? null) ? $profile['main_pages'] : [];

        return collect($pages)
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['url'] ?? null))
            ->take(6)
            ->map(fn (array $item): array => [
                'label' => (string) ($item['title'] ?? $item['path'] ?? $item['url']),
                'url' => (string) $item['url'],
                'reason' => 'page_principale_'.($item['role'] ?? 'content'),
            ])
            ->values()
            ->all();
    }
}
