<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSemanticLink extends Model
{
    protected $fillable = [
        'site_id',
        'relation_type',
        'source_key',
        'source_id',
        'target_key',
        'target_id',
        'label',
        'url',
        'reason',
        'similarity_score',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'similarity_score' => 'float',
        ];
    }
}
