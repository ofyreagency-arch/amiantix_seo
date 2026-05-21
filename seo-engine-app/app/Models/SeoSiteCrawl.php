<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSiteCrawl extends Model
{
    protected $fillable = [
        'site_id',
        'base_url',
        'status',
        'max_pages',
        'discovered_url_count',
        'crawled_url_count',
        'started_at',
        'completed_at',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
