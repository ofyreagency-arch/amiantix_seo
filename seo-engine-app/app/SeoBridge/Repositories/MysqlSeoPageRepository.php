<?php

declare(strict_types=1);

namespace App\SeoBridge\Repositories;

use App\Models\SeoPage;
use App\Runtime\SeoEngineContext;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;

class MysqlSeoPageRepository implements SeoPageRepository
{
    public function __construct(private readonly SeoEngineContext $context) {}

    public function findBySlug(string $slug): ?object
    {
        return SeoPage::query()
            ->where('site_id', $this->context->siteId())
            ->where('slug', ltrim($slug, '/'))
            ->first();
    }

    public function publishedPages(): iterable
    {
        return SeoPage::query()
            ->where('site_id', $this->context->siteId())
            ->published()
            ->orderBy('published_at', 'desc')
            ->get();
    }

    public function pagesForScoreRefresh(?string $slug = null): iterable
    {
        return SeoPage::query()
            ->where('site_id', $this->context->siteId())
            ->when($slug, fn ($query) => $query->where('slug', ltrim((string) $slug, '/')))
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
