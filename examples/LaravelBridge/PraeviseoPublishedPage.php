<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PraeviseoPublishedPage extends Model
{
    protected $fillable = [
        'praeviseo_site_id',
        'external_page_id',
        'slug',
        'title',
        'h1',
        'meta_description',
        'content_html',
        'faq_json',
        'schema_json',
        'internal_links_json',
        'canonical_url',
        'live_url',
        'cluster',
        'is_noindex',
        'image_path',
        'image_alt',
        'publication_state',
        'last_published_at',
    ];

    protected function casts(): array
    {
        return [
            'faq_json' => 'array',
            'schema_json' => 'array',
            'internal_links_json' => 'array',
            'is_noindex' => 'boolean',
            'last_published_at' => 'datetime',
        ];
    }
}
