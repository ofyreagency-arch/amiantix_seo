<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunRemoteInstallationJob;
use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\Models\User;
use App\Models\UserAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClientSiteInstallationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_store_remote_installation_request(): void
    {
        Queue::fake();

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

        $response->assertAccepted();
        $response->assertJsonPath('site.publication_bridge_status', 'requested');
        $response->assertJsonPath('site.installation.status', 'pending');
        $response->assertJsonPath('site.installation.current_step', 'pending');
        $response->assertJsonPath('site.installation.hosting_provider', 'vps_linux');
        $response->assertJsonPath('site.installation.access_method', 'ssh');

        $site->refresh();

        self::assertSame('requested', data_get($site->settings_json, 'publication.bridge_status'));

        $installation = RemoteInstallation::query()->where('site_id', 'amiantix')->latest('id')->first();

        self::assertNotNull($installation);
        self::assertSame(RemoteInstallation::STATUS_PENDING, $installation->status);
        self::assertSame('pending', $installation->current_step);
        self::assertSame('vps_linux', $installation->hosting_provider);
        self::assertSame('ssh', $installation->connection_type);
        self::assertSame('/var/www/amiantix', data_get($installation->connection_metadata, 'project_path'));
        self::assertSame('deploy', data_get($installation->encrypted_credentials, 'username'));
        self::assertSame('ssh.amiantix.com', data_get($installation->encrypted_credentials, 'host'));
        self::assertSame('super-secret-key', data_get($installation->encrypted_credentials, 'secret'));

        Queue::assertPushed(RunRemoteInstallationJob::class, function (RunRemoteInstallationJob $job) use ($installation): bool {
            return $job->installationId === $installation?->id;
        });
    }

    public function test_client_can_read_remote_installation_status(): void
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
                    'bridge_status' => 'requested',
                ],
            ],
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $installation = RemoteInstallation::query()->create([
            'site_id' => 'amiantix',
            'status' => RemoteInstallation::STATUS_INSTALLING,
            'current_step' => 'installing_praeviseo',
            'progress' => 45,
            'hosting_provider' => 'vps_linux',
            'connection_type' => 'ssh',
            'encrypted_credentials' => [
                'host' => 'ssh.amiantix.com',
                'port' => 22,
                'username' => 'deploy',
                'secret' => 'super-secret-key',
            ],
            'connection_metadata' => [
                'project_path' => '/var/www/amiantix',
            ],
            'detected_framework' => 'symfony',
            'detected_php_version' => '8.3.7',
            'detected_composer' => 'Composer version 2.8.5',
            'logs_json' => [[
                'at' => now()->toIso8601String(),
                'level' => 'info',
                'step' => 'installing_praeviseo',
                'message' => 'Installation du package PraeviSEO sur le site.',
            ]],
            'started_at' => now(),
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->getJson('/api/client/sites/amiantix/installation-status');

        $response->assertOk();
        $response->assertJsonPath('site.site_id', 'amiantix');
        $response->assertJsonPath('installation.status', 'installing');
        $response->assertJsonPath('installation.current_step', 'installing_praeviseo');
        $response->assertJsonPath('installation.progress', 45);
        $response->assertJsonPath('installation.hosting_provider', 'vps_linux');
        $response->assertJsonPath('installation.detected_framework', 'symfony');
        $response->assertJsonPath('installation.logs.0.message', 'Installation du package PraeviSEO sur le site.');
    }
}
