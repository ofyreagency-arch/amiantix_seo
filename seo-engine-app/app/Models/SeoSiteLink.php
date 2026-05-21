<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSiteLink extends Model
{
    protected $fillable = [
        'site_id',
        'site_crawl_id',
        'source_page_id',
        'target_page_id',
        'source_url',
        'target_url',
        'anchor_text',
        'relation_type',
        'is_internal',
        'is_nofollow',
        'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_nofollow' => 'boolean',
            'discovered_at' => 'datetime',
        ];
    }
}
