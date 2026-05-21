<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSitePage extends Model
{
    protected $fillable = [
        'site_id',
        'normalized_url',
        'url_hash',
        'path',
        'title',
        'meta_description',
        'canonical_url',
        'primary_h1',
        'indexability_state',
        'last_status_code',
        'last_crawl_id',
        'last_snapshot_id',
        'latest_word_count',
        'internal_inlinks',
        'internal_outlinks',
        'authority_score',
        'orphan_score',
        'overlap_score',
        'pillar_likelihood',
        'cluster_label',
        'discovered_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'authority_score' => 'float',
            'orphan_score' => 'float',
            'overlap_score' => 'float',
            'pillar_likelihood' => 'float',
            'discovered_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
