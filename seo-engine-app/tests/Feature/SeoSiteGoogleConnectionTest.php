<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoSite;
use App\Models\SeoSiteGoogleConnection;
use App\Runtime\SeoEngineContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoSiteGoogleConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_context_prefers_dedicated_google_connection_over_legacy_fields(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'token'),
            'gsc_site_url' => 'sc-domain:legacy.example',
            'gsc_credentials_path' => '/legacy/path.json',
            'is_active' => true,
        ]);

        SeoSiteGoogleConnection::query()->create([
            'site_id' => $site->site_id,
            'connection_mode' => 'service_account',
            'property_url' => 'sc-domain:amiantix.com',
            'credentials_path' => '/secure/site.json',
            'google_account_email' => 'seo-bot@amiantix.test',
            'connection_status' => 'configured',
        ]);

        /** @var SeoEngineContext $context */
        $context = app(SeoEngineContext::class);
        $context->loadFromSite($site->fresh(['googleConnection']));

        $this->assertSame('sc-domain:amiantix.com', $context->gscSiteUrl());
        $this->assertSame('/secure/site.json', $context->gscCredentialsPath());
        $this->assertSame('sc-domain:amiantix.com', config('seo-engine.search_console.site_url'));
        $this->assertSame('/secure/site.json', config('seo-engine.search_console.credentials'));
    }

    public function test_site_falls_back_to_legacy_gsc_fields_when_no_dedicated_connection_exists(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'legacy',
            'name' => 'Legacy Site',
            'url' => 'https://legacy.test',
            'niche' => 'general',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'legacy-token'),
            'gsc_site_url' => 'sc-domain:legacy.test',
            'gsc_credentials_path' => '/legacy/service-account.json',
            'is_active' => true,
        ]);

        $this->assertNull($site->resolvedGoogleConnection());
        $this->assertSame('sc-domain:legacy.test', $site->resolvedGscSiteUrl());
        $this->assertSame('/legacy/service-account.json', $site->resolvedGscCredentialsPath());
        $this->assertSame('service_account', $site->resolvedGscConnectionMode());
        $this->assertSame('configured', $site->resolvedGscConnectionStatus());
        $this->assertTrue($site->hasSearchConsoleConfigured());
    }
}
