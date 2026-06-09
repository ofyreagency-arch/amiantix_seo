<?php

declare(strict_types=1);

namespace Praeviseo\LaravelBridge\Models;

use Illuminate\Database\Eloquent\Model;

class PraeviseoNativePagePatch extends Model
{
    protected $fillable = [
        'praeviseo_site_id',
        'external_page_id',
        'target_path',
        'title',
        'h1',
        'meta_description',
        'content_html',
        'faq_json',
        'schema_json',
        'internal_links_json',
        'canonical_url',
        'live_url',
        'publication_state',
        'last_published_at',
    ];

    protected function casts(): array
    {
        return [
            'faq_json' => 'array',
            'schema_json' => 'array',
            'internal_links_json' => 'array',
            'last_published_at' => 'datetime',
        ];
    }
}
