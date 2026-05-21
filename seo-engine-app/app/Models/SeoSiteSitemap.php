<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSiteSitemap extends Model
{
    protected $fillable = [
        'site_id',
        'site_crawl_id',
        'url',
        'url_hash',
        'sitemap_type',
        'parent_url',
        'lastmod_at',
        'discovered_at',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'lastmod_at' => 'datetime',
            'discovered_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }
}
