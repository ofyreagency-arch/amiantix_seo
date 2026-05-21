<?php

declare(strict_types=1);

namespace App\Recommendations;

use App\Models\SeoStrategyItem;
use App\Models\SeoSite;
use Illuminate\Database\Eloquent\Collection;

class SiteStrategyService
{
    public function __construct(private readonly RecommendationEngineService $recommendations) {}

    public function generate(SeoSite $site): array
    {
        return $this->recommendations->generate($site)->all();
    }

    public function items(string $siteId): Collection
    {
        return SeoStrategyItem::query()
            ->where('site_id', $siteId)
            ->orderBy('priority')
            ->get();
    }

    public function markDone(int $id): void
    {
        SeoStrategyItem::query()->findOrFail($id)->update(['status' => 'done']);
    }
}
