<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSiteSnapshot extends Model
{
    protected $fillable = [
        'site_id',
        'health_score',
        'page_count',
        'published_count',
        'avg_seo_score',
        'avg_quality_score',
        'avg_topical_score',
        'snapshot_date',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
        ];
    }
}
