<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoRecommendation extends Model
{
    protected $fillable = [
        'site_id',
        'site_page_id',
        'site_crawl_id',
        'type',
        'priority',
        'estimated_impact',
        'difficulty',
        'cluster',
        'title',
        'reasoning',
        'suggested_action',
        'status',
        'meta_json',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
