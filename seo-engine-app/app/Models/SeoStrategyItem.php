<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoStrategyItem extends Model
{
    protected $fillable = [
        'site_id',
        'priority',
        'type',
        'title',
        'description',
        'keywords_json',
        'estimated_impact',
        'status',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'keywords_json' => 'array',
            'generated_at'  => 'datetime',
        ];
    }
}
