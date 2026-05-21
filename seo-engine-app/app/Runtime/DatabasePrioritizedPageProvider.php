<?php

declare(strict_types=1);

namespace App\Runtime;

use App\Models\SeoPage;
use Ofyre\SeoEngine\Contracts\PrioritizedPageProvider;

class DatabasePrioritizedPageProvider implements PrioritizedPageProvider
{
    public function prioritizedPageIds(): array
    {
        return SeoPage::query()
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', ['published'])
            ->orderBy('seo_score')
            ->limit(50)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
