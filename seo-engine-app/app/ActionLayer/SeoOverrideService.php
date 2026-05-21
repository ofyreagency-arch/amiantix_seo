<?php

declare(strict_types=1);

namespace App\ActionLayer;

use App\Models\SeoOverride;
use Ofyre\SeoEngine\Contracts\RewriteAccessDecider;

class SeoOverrideService implements RewriteAccessDecider
{
    public function rewriteAllowed(object $page): bool
    {
        $override = $this->overrideFor($page);

        return $override?->rewrite_allowed ?? true;
    }

    public function forcedNoindex(object $page): bool
    {
        return $this->overrideFor($page)?->forced_noindex ?? false;
    }

    private function overrideFor(object $page): ?SeoOverride
    {
        $pageId = (int) ($page->id ?? 0);

        if ($pageId <= 0) {
            return null;
        }

        return SeoOverride::query()->where('seo_page_id', $pageId)->first();
    }
}
