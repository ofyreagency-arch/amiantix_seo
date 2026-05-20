<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoVector extends Model
{
    protected $fillable = [
        'site_id',
        'entity_type',
        'entity_key',
        'entity_id',
        'source_text',
        'source_hash',
        'embedding_model',
        'embedding_version',
        'embedding_json',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'embedding_json' => 'array',
            'meta_json' => 'array',
        ];
    }
}
