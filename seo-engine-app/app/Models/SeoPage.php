<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoPage extends Model
{
    protected $fillable = [
        'site_id',
        'keyword',
        'slug',
        'cluster',
        'status',
        'title',
        'h1',
        'meta_description',
        'content',
        'faq_json',
        'schema_json',
        'internal_links_json',
        'canonical_url',
        'forced_noindex',
        'is_indexed',
        'published_at',
        'image_path',
        'image_alt',
        'image_prompt',
        'image_status',
        'image_quality_json',
        'topical_score',
        'quality_score',
        'seo_score',
        'indexability_score',
        'image_quality_score',
        'duplicate_risk_score',
        'spam_risk',
        'review_issues_json',
        'last_audit_at',
    ];

    protected function casts(): array
    {
        return [
            'faq_json' => 'array',
            'schema_json' => 'array',
            'internal_links_json' => 'array',
            'image_quality_json' => 'array',
            'review_issues_json' => 'array',
            'forced_noindex' => 'boolean',
            'is_indexed' => 'boolean',
            'published_at' => 'datetime',
            'last_audit_at' => 'datetime',
        ];
    }

    public function audits(): HasMany
    {
        return $this->hasMany(SeoAudit::class);
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(SeoSuggestion::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(SeoOverride::class);
    }

    public function searchConsoleMetrics(): HasMany
    {
        return $this->hasMany(SeoSearchConsoleMetric::class);
    }

    public function canonicalPath(): string
    {
        $slug = ltrim((string) $this->slug, '/');

        return $slug === '' ? '/' : '/'.$slug;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
