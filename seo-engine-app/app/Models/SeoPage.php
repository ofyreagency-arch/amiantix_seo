<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoPage extends Model
{
    protected $fillable = [
        'site_id',
        'observed_site_page_id',
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
        'published_live',
        'published_live_at',
        'live_url',
        'last_observed_at',
        'generation_source',
        'generation_error',
        'generation_trace_json',
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
        'observed_page_match_rule',
        'observed_page_linked_at',
    ];

    protected function casts(): array
    {
        return [
            'faq_json' => 'array',
            'schema_json' => 'array',
            'internal_links_json' => 'array',
            'image_quality_json' => 'array',
            'review_issues_json' => 'array',
            'generation_trace_json' => 'array',
            'forced_noindex' => 'boolean',
            'is_indexed' => 'boolean',
            'published_at' => 'datetime',
            'published_live' => 'boolean',
            'published_live_at' => 'datetime',
            'last_observed_at' => 'datetime',
            'last_audit_at' => 'datetime',
            'observed_page_linked_at' => 'datetime',
        ];
    }

    public function observedPage(): BelongsTo
    {
        return $this->belongsTo(SeoSitePage::class, 'observed_site_page_id');
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

    public function scopePublishedLive(Builder $query): Builder
    {
        return $query
            ->where('published_live', true)
            ->whereNotNull('published_live_at');
    }

    public function isPublishedInEngine(): bool
    {
        return $this->status === 'published' || $this->published_at !== null;
    }

    public function isPublishedLive(): bool
    {
        return (bool) $this->published_live && $this->published_live_at !== null;
    }

    public function generationSourceLabel(): string
    {
        return match ($this->generation_source) {
            'ai' => 'AI',
            'hybrid' => 'Hybrid',
            'fallback' => 'Fallback preset',
            default => 'Unknown',
        };
    }

    /**
     * @return array<int, string>
     */
    public function generationMissingKeys(): array
    {
        $trace = $this->generation_trace_json;

        return is_array($trace['missing_keys'] ?? null)
            ? array_values(array_map('strval', $trace['missing_keys']))
            : [];
    }

    /**
     * @return array<int, string>
     */
    public function generationReturnedKeys(): array
    {
        $trace = $this->generation_trace_json;

        return is_array($trace['returned_keys'] ?? null)
            ? array_values(array_map('strval', $trace['returned_keys']))
            : [];
    }
}
