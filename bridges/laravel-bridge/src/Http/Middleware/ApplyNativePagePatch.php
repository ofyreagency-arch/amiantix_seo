<?php

declare(strict_types=1);

namespace Praeviseo\LaravelBridge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Praeviseo\LaravelBridge\Models\PraeviseoNativePagePatch;
use Praeviseo\LaravelBridge\Services\NativePageHtmlPatcher;
use Symfony\Component\HttpFoundation\Response;

final class ApplyNativePagePatch
{
    public function __construct(
        private readonly NativePageHtmlPatcher $patcher,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($request->method() !== 'GET' || $response->getStatusCode() !== 200) {
            return $response;
        }

        if ($this->shouldSkipPath($request->path())) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $siteId = trim((string) config('praeviseo-bridge.site_id', ''));

        if ($siteId === '') {
            return $response;
        }

        $targetPath = $this->patcher->normalizePath('/'.$request->path());

        $patch = PraeviseoNativePagePatch::query()
            ->where('praeviseo_site_id', $siteId)
            ->where('target_path', $targetPath)
            ->where('publication_state', 'published')
            ->first();

        if (! $patch) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $response->setContent($this->patcher->apply($content, [
            'title' => (string) $patch->title,
            'meta_description' => $patch->meta_description,
            'content_html' => (string) $patch->content_html,
            'faq_json' => is_array($patch->faq_json) ? $patch->faq_json : [],
        ]));
        $response->headers->set('X-Praeviseo-Native-Patch', 'applied');

        return $response;
    }

    private function shouldSkipPath(string $path): bool
    {
        if (str_starts_with($path, 'api/praeviseo')) {
            return true;
        }

        $prefix = trim((string) config('praeviseo-bridge.prefix', 'ressources'), '/');

        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }
}
