<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoSite;
use App\Models\User;
use App\Models\UserAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSiteInstallationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_store_remote_installation_request(): void
    {
        $user = User::factory()->create();
        $rawToken = 'frontend-token';

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $rawToken),
        ]);

        $site = SeoSite::query()->create([
            'site_id' => 'amiantix',
            'name' => 'Amiantix',
            'url' => 'https://amiantix.com',
            'niche' => 'amiante',
            'locale' => 'fr',
            'preset' => 'amiantix',
            'api_token_hash' => hash('sha256', 'site-token'),
            'is_active' => true,
            'settings_json' => [
                'publication' => [
                    'mode' => 'symfony_bridge',
                    'bridge_status' => 'pending',
                ],
            ],
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->postJson('/api/client/sites/amiantix/installation', [
                'hosting_provider' => 'vps_linux',
                'access_method' => 'ssh',
                'ssh_host' => 'ssh.amiantix.com',
                'ssh_port' => 22,
                'ssh_username' => 'deploy',
                'ssh_project_path' => '/var/www/amiantix',
                'ssh_secret' => 'super-secret-key',
            ]);

        $response->assertOk();
        $response->assertJsonPath('site.publication_bridge_status', 'requested');
        $response->assertJsonPath('site.installation.status', 'requested');
        $response->assertJsonPath('site.installation.hosting_provider', 'vps_linux');
        $response->assertJsonPath('site.installation.access_method', 'ssh');

        $site->refresh();

        self::assertSame('requested', data_get($site->settings_json, 'publication.bridge_status'));
        self::assertSame('vps_linux', data_get($site->settings_json, 'installation.hosting_provider'));
        self::assertSame('ssh', data_get($site->settings_json, 'installation.access_method'));
        self::assertNotEmpty(data_get($site->settings_json, 'installation.ssh.secret_encrypted'));
    }
}
