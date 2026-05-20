<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoCrawlPage extends Model
{
    protected $fillable = [
        'site_id',
        'url',
        'title',
        'meta_description',
        'status_code',
        'word_count',
        'depth',
        'is_covered',
        'coverage_page_id',
        'crawled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_covered' => 'boolean',
            'crawled_at' => 'datetime',
        ];
    }
}
