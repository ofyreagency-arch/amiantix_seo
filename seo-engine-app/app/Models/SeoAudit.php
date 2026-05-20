<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAudit extends Model
{
    protected $fillable = [
        'seo_page_id',
        'score',
        'issues_json',
        'recommendations_json',
        'search_console_json',
    ];

    protected function casts(): array
    {
        return [
            'issues_json' => 'array',
            'recommendations_json' => 'array',
            'search_console_json' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }
}
