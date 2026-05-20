<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoSuggestion extends Model
{
    protected $fillable = [
        'seo_page_id',
        'source',
        'signals_json',
        'suggestions_json',
        'status',
        'applied_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'signals_json' => 'array',
            'suggestions_json' => 'array',
            'applied_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }
}
