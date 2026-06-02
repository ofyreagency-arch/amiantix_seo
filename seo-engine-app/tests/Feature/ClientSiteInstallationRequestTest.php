<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunRemoteInstallationJob;
use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\Models\User;
use App\Models\UserAccessToken;
use App\RemoteInstallation\InstallationPrecheckService;
use App\RemoteInstallation\InstallationReadinessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClientSiteInstallationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_store_remote_installation_request(): void
    {
        Queue::fake();
        $this->mock(InstallationPrecheckService::class, function ($mock): void {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn(new InstallationReadinessReport(
                    92,
                    [['key' => 'ssh', 'label' => 'SSH valide', 'detail' => 'OK']],
                    [],
                    [],
                    [],
                    [],
                    ['framework' => 'symfony', 'php_version' => '8.3', 'composer_version' => 'Composer 2', 'project_path' => '/var/www/amiantix', 'access_method' => 'ssh'],
                ));
        });

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
        self::assertSame(92, data_get($installation->connection_metadata, 'precheck_report.score'));
        self::assertSame('deploy', data_get($installation->encrypted_credentials, 'username'));
        self::assertSame('ssh.amiantix.com', data_get($installation->encrypted_credentials, 'host'));
        self::assertSame('super-secret-key', data_get($installation->encrypted_credentials, 'secret'));
        self::assertSame('installation_started', data_get($site->fresh()->settings_json, 'installation_doctor.status'));

        Queue::assertPushed(RunRemoteInstallationJob::class, function (RunRemoteInstallationJob $job) use ($installation): bool {
            return $job->installationId === $installation?->id;
        });
    }

    public function test_client_can_read_installation_precheck_report(): void
    {
        $this->mock(InstallationPrecheckService::class, function ($mock): void {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn(new InstallationReadinessReport(
                    85,
                    [['key' => 'ssh', 'label' => 'SSH valide', 'detail' => 'Le serveur répond.']],
                    [['key' => 'worker', 'label' => 'Aucun worker détecté', 'detail' => 'Warning.']],
                    [['key' => 'app_url', 'label' => 'APP_URL absente', 'detail' => 'Bloquant.', 'autofixable' => true]],
                    [['key' => 'app_url_autofix', 'label' => 'APP_URL automatique', 'detail' => 'Corrigible.']],
                    [],
                    ['framework' => 'symfony', 'php_version' => '8.3', 'composer_version' => 'Composer 2', 'project_path' => '/var/www/amiantix', 'access_method' => 'ssh'],
                ));
        });

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
            'settings_json' => ['publication' => ['mode' => 'symfony_bridge', 'bridge_status' => 'pending']],
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->postJson('/api/client/sites/amiantix/installation-precheck', [
                'hosting_provider' => 'vps_linux',
                'access_method' => 'ssh',
                'ssh_host' => '127.0.0.1',
                'ssh_port' => 22,
                'ssh_username' => 'root',
                'ssh_project_path' => '/var/www/amiantix',
                'ssh_secret' => 'secret',
            ]);

        $response->assertOk();
        $response->assertJsonPath('report.score', 85);
        $response->assertJsonPath('report.blockers.0.label', 'APP_URL absente');
        $response->assertJsonPath('report.validated.0.label', 'SSH valide');

        $site->refresh();
        self::assertSame('blocked', data_get($site->settings_json, 'installation_doctor.status'));
        self::assertSame(85, data_get($site->settings_json, 'installation_doctor.last_report.score'));
    }

    public function test_installation_request_stops_when_precheck_has_blockers(): void
    {
        Queue::fake();
        $this->mock(InstallationPrecheckService::class, function ($mock): void {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn(new InstallationReadinessReport(
                    60,
                    [],
                    [],
                    [['key' => 'app_url', 'label' => 'APP_URL absente', 'detail' => 'Bloquant.', 'autofixable' => true]],
                    [['key' => 'app_url_autofix', 'label' => 'APP_URL automatique', 'detail' => 'Corrigible.']],
                    [],
                    ['framework' => 'symfony', 'php_version' => '8.3', 'composer_version' => 'Composer 2', 'project_path' => '/var/www/amiantix', 'access_method' => 'ssh'],
                ));
        });

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
            'settings_json' => ['publication' => ['mode' => 'symfony_bridge', 'bridge_status' => 'pending']],
        ]);

        $user->seoSites()->attach($site->id, ['role' => 'owner']);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$rawToken)
            ->postJson('/api/client/sites/amiantix/installation', [
                'hosting_provider' => 'vps_linux',
                'access_method' => 'ssh',
                'ssh_host' => '127.0.0.1',
                'ssh_port' => 22,
                'ssh_username' => 'root',
                'ssh_project_path' => '/var/www/amiantix',
                'ssh_secret' => 'secret',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('report.blockers.0.label', 'APP_URL absente');
        self::assertNull(RemoteInstallation::query()->where('site_id', 'amiantix')->first());
        Queue::assertNothingPushed();
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
