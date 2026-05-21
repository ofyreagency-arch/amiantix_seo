<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSiteCrawlIssue extends Model
{
    protected $fillable = [
        'site_id',
        'site_crawl_id',
        'site_page_id',
        'issue_type',
        'severity',
        'url',
        'details',
        'meta_json',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'detected_at' => 'datetime',
        ];
    }
}
