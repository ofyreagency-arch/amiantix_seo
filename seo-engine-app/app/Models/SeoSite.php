<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public function googleConnection(): HasOne
    {
        return $this->hasOne(SeoSiteGoogleConnection::class, 'site_id', 'site_id');
    }

    public function remoteInstallations(): HasMany
    {
        return $this->hasMany(RemoteInstallation::class, 'site_id', 'site_id');
    }

    public function latestRemoteInstallation(): HasOne
    {
        return $this->hasOne(RemoteInstallation::class, 'site_id', 'site_id')->latestOfMany();
    }

    public function crawls(): HasMany
    {
        return $this->hasMany(SeoSiteCrawl::class, 'site_id', 'site_id');
    }

    public function latestObservedCrawl(): HasOne
    {
        return $this->hasOne(SeoSiteCrawl::class, 'site_id', 'site_id')->latestOfMany('created_at');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_seo_sites')
            ->withPivot('role')
            ->withTimestamps();
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

    public function resolvedGoogleConnection(): ?SeoSiteGoogleConnection
    {
        if ($this->relationLoaded('googleConnection')) {
            return $this->getRelation('googleConnection');
        }

        return $this->googleConnection()->first();
    }

    public function resolvedGscSiteUrl(): ?string
    {
        return $this->resolvedGoogleConnection()?->property_url ?: $this->gsc_site_url;
    }

    public function resolvedGscCredentialsPath(): ?string
    {
        return $this->resolvedGoogleConnection()?->credentials_path ?: $this->gsc_credentials_path;
    }

    public function resolvedGscConnectionMode(): ?string
    {
        $connection = $this->resolvedGoogleConnection();

        if ($connection?->connection_mode) {
            return $connection->connection_mode;
        }

        return ($this->gsc_site_url || $this->gsc_credentials_path)
            ? 'service_account'
            : null;
    }

    public function resolvedGscConnectionStatus(): string
    {
        $connection = $this->resolvedGoogleConnection();

        if ($connection?->connection_status) {
            return $connection->connection_status;
        }

        return ($this->gsc_site_url || $this->gsc_credentials_path)
            ? 'configured'
            : 'not_connected';
    }

    public function hasSearchConsoleConfigured(): bool
    {
        return (bool) ($this->resolvedGscSiteUrl() || $this->resolvedGscCredentialsPath());
    }

    public function resolvedPublicationMode(): string
    {
        $mode = trim((string) data_get($this->settings_json, 'publication.mode', ''));

        return in_array($mode, ['runtime', 'laravel_bridge', 'symfony_bridge', 'wordpress_bridge', 'webhook_api', 'disabled'], true)
            ? $mode
            : 'runtime';
    }

    public function resolvedPublicationModeLabel(): string
    {
        return match ($this->resolvedPublicationMode()) {
            'laravel_bridge' => 'Bridge Laravel',
            'symfony_bridge' => 'Bridge Symfony',
            'wordpress_bridge' => 'Plugin WordPress',
            'webhook_api' => 'Webhook CMS/API',
            'disabled' => 'Publication externe désactivée',
            default => 'Runtime interne',
        };
    }

    public function publicationWebhookUrl(): ?string
    {
        return $this->webhook_url ?: data_get($this->settings_json, 'publication.webhook_url');
    }

    public function publicationSharedSecret(): ?string
    {
        $secret = trim((string) data_get($this->settings_json, 'publication.shared_secret', ''));

        return $secret !== '' ? $secret : null;
    }

    public function publicationPathPrefix(): ?string
    {
        $prefix = trim((string) data_get($this->settings_json, 'publication.path_prefix', ''), '/');

        return $prefix !== '' ? $prefix : null;
    }

    public function publicationConnectCode(): ?string
    {
        $code = trim((string) data_get($this->settings_json, 'publication.connect_code', ''));

        return $code !== '' ? strtoupper($code) : null;
    }

    public function publicationBridgeStatus(): string
    {
        return trim((string) data_get($this->settings_json, 'publication.bridge_status', 'pending')) ?: 'pending';
    }

    public static function resolveByToken(string $rawToken): ?self
    {
        return self::query()
            ->active()
            ->where('api_token_hash', hash('sha256', $rawToken))
            ->first();
    }

    public static function resolveByPublicationConnectCode(string $rawCode): ?self
    {
        $needle = strtoupper(trim($rawCode));

        return self::query()
            ->active()
            ->get()
            ->first(fn (self $site): bool => $site->publicationConnectCode() === $needle);
    }

    public static function generateToken(): array
    {
        $raw = bin2hex(random_bytes(32));

        return [
            'token' => $raw,
            'hash'  => hash('sha256', $raw),
        ];
    }

    public static function generatePublicationConnectCode(): string
    {
        return strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
    }
}
