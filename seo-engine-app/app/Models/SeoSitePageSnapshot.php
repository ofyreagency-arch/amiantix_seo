<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSitePageSnapshot extends Model
{
    protected $fillable = [
        'site_id',
        'site_crawl_id',
        'site_page_id',
        'url',
        'title',
        'meta_description',
        'canonical_url',
        'h1_json',
        'h2_json',
        'h3_json',
        'content_text',
        'content_html',
        'robots_meta',
        'status_code',
        'is_indexable',
        'word_count',
        'internal_links_count',
        'outlinks_count',
        'schema_count',
        'content_hash',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'h1_json' => 'array',
            'h2_json' => 'array',
            'h3_json' => 'array',
            'is_indexable' => 'boolean',
            'observed_at' => 'datetime',
        ];
    }
}
