<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoSite extends Model
{
    protected $fillable = [
        'site_id',
        'name',
        'url',
        'niche',
        'locale',
        'preset',
        'api_token_hash',
        'webhook_url',
        'gsc_site_url',
        'gsc_credentials_path',
        'is_active',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    public function resolvedPreset(): string
    {
        $preset = trim((string) ($this->preset ?? ''));

        if ($preset !== '') {
            return $preset;
        }

        return $this->niche === 'amiante' ? 'amiantix' : 'generic';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function resolveByToken(string $rawToken): ?self
    {
        return self::query()
            ->active()
            ->where('api_token_hash', hash('sha256', $rawToken))
            ->first();
    }

    public static function generateToken(): array
    {
        $raw = bin2hex(random_bytes(32));

        return [
            'token' => $raw,
            'hash'  => hash('sha256', $raw),
        ];
    }
}
