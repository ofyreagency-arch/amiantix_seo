<?php

declare(strict_types=1);

namespace App\SeoBridge\Repositories;

use App\Models\SeoPage;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;

class MysqlSeoPageRepository implements SeoPageRepository
{
    public function findBySlug(string $slug): ?object
    {
        return SeoPage::query()->where('slug', ltrim($slug, '/'))->first();
    }

    public function publishedPages(): iterable
    {
        return SeoPage::query()->published()->orderBy('published_at', 'desc')->get();
    }

    public function pagesForScoreRefresh(?string $slug = null): iterable
    {
        return SeoPage::query()
            ->when($slug, fn ($query) => $query->where('slug', ltrim((string) $slug, '/')))
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
