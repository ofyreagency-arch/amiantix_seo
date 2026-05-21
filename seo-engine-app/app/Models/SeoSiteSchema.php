<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSiteSchema extends Model
{
    protected $fillable = [
        'site_id',
        'site_crawl_id',
        'site_page_id',
        'page_url',
        'schema_type',
        'schema_json',
        'content_hash',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
            'observed_at' => 'datetime',
        ];
    }
}
