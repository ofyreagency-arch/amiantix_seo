<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoOverride extends Model
{
    protected $fillable = [
        'seo_page_id',
        'rewrite_allowed',
        'forced_noindex',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rewrite_allowed' => 'boolean',
            'forced_noindex' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }
}
