<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoSearchConsoleMetric extends Model
{
    protected $fillable = [
        'seo_page_id',
        'metric_date',
        'window_days',
        'query',
        'url',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'is_indexed',
        'coverage_json',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'clicks' => 'float',
            'impressions' => 'float',
            'ctr' => 'float',
            'position' => 'float',
            'is_indexed' => 'boolean',
            'coverage_json' => 'array',
            'payload_json' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }
}
