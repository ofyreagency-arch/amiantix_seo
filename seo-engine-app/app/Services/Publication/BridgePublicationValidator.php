<?php

declare(strict_types=1);

namespace App\Services\Publication;

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\ObservedSite\SeoPageObservedLinkService;
use Illuminate\Support\Facades\Http;

class BridgePublicationValidator
{
    public function __construct(
        private readonly SeoLivePublicationService $livePublication,
        private readonly SeoPageObservedLinkService $observedLinks,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function inspectSite(SeoSite $site): array
    {
        $target = $this->livePublication->targetStatusForSite($site);
        $endpoint = $site->publicationWebhookUrl();
        $secret = $site->publicationSharedSecret();

        $checks = [
            'site_id' => $site->site_id,
            'site_url' => $site->url,
            'publication_mode' => $site->resolvedPublicationMode(),
            'bridge_status' => $site->publicationBridgeStatus(),
            'endpoint' => $endpoint,
            'has_secret' => $secret !== null && $secret !== '',
            'path_prefix' => $site->publicationPathPrefix(),
            'target' => $target,
            'endpoint_reachable' => null,
            'endpoint_reachable_detail' => null,
        ];

        if ($endpoint) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders(['User-Agent' => 'Praeviseo-BridgeValidator/1.0'])
                    ->send('OPTIONS', $endpoint);

                $checks['endpoint_reachable'] = in_array($response->status(), [200, 204, 405, 422, 419], true);
                $checks['endpoint_reachable_detail'] = 'HTTP '.$response->status();
            } catch (\Throwable $exception) {
                $checks['endpoint_reachable'] = false;
                $checks['endpoint_reachable_detail'] = $exception->getMessage();
            }
        }

        $checks['ready'] = $target['engine_actionable'] ?? false;

        return $checks;
    }

    /**
     * @return array<string,mixed>
     */
    public function inspectPublishedPage(SeoSite $site, SeoPage $page): array
    {
        $siteReport = $this->inspectSite($site);
        $observed = $this->observedLinks->observedForPage($page, resolve: true);

        return array_merge($siteReport, [
            'page_id' => $page->id,
            'slug' => $page->slug,
            'published_live' => $page->isPublishedLive(),
            'live_url' => $page->live_url,
            'canonical_url' => $page->canonical_url,
            'observed_matched' => $observed !== null,
            'observed_path' => $observed?->path,
            'observed_http_status' => $observed?->last_status_code,
            'observed_last_seen_at' => $observed?->last_seen_at,
            'observed_match_rule' => $page->observed_page_match_rule,
            'chain_ok' => $page->isPublishedLive()
                && ($observed !== null)
                && (int) ($observed->last_status_code ?? 0) > 0
                && (int) ($observed->last_status_code ?? 0) < 400,
        ]);
    }
}
