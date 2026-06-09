<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunSiteOnboardingJob;
use App\Models\SeoSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoBridgeConnectController extends Controller
{
    public function connect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'connection_code' => ['required', 'string', 'max:80'],
            'app_url' => ['required', 'url', 'max:500'],
            'bridge' => ['required', 'string', 'in:laravel_bridge,symfony_bridge,wordpress_bridge'],
            'publication_prefix' => ['nullable', 'string', 'max:120'],
        ]);

        $site = SeoSite::resolveByPublicationConnectCode((string) $data['connection_code']);

        abort_unless($site, 404, 'Code de connexion inconnu.');

        $secret = $site->publicationSharedSecret() ?: bin2hex(random_bytes(24));
        $appUrl = rtrim((string) $data['app_url'], '/');
        $prefix = trim((string) ($data['publication_prefix'] ?? ''), '/');
        $endpoint = $data['bridge'] === 'wordpress_bridge'
            ? $appUrl.'/wp-json/praeviseo/v1/publish'
            : $appUrl.'/api/praeviseo/bridge/publish';

        $settings = $site->settings_json ?? [];
        $publication = is_array($settings['publication'] ?? null) ? $settings['publication'] : [];
        $publication['mode'] = (string) $data['bridge'];
        $publication['webhook_url'] = $endpoint;
        $publication['shared_secret'] = $secret;
        $publication['path_prefix'] = $prefix !== '' ? $prefix : null;
        $publication['bridge_status'] = 'connected';
        $publication['bridge_connected_at'] = now()->toIso8601String();
        $publication['bridge_app_url'] = $appUrl;
        $settings['publication'] = $publication;

        $site->forceFill([
            'webhook_url' => $endpoint,
            'settings_json' => $settings,
        ])->save();

        RunSiteOnboardingJob::dispatch($site->site_id);

        return response()->json([
            'status' => 'connected',
            'site_id' => $site->site_id,
            'publication_mode' => $site->resolvedPublicationMode(),
            'publication_endpoint' => $endpoint,
            'publication_prefix' => $prefix,
            'bridge_secret' => $secret,
            'message' => 'Site connecté. Publication et monitoring peuvent maintenant être activés côté Praeviseo.',
        ]);
    }
}
