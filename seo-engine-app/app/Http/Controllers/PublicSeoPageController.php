<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Publication\SeoLivePublicationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicSeoPageController extends Controller
{
    public function __construct(private readonly SeoLivePublicationService $livePublication) {}

    public function show(Request $request, string $slug): View
    {
        $site = $this->livePublication->resolveSiteByHost((string) $request->getHost());
        abort_if(! $site, 404);

        $normalizedSlug = trim($slug, '/');
        abort_if($normalizedSlug === '', 404);

        $page = $this->livePublication->livePagesQuery($site)
            ->where('slug', $normalizedSlug)
            ->first();

        abort_if(! $page, 404);

        return view('public.page', compact('site', 'page'));
    }

    public function sitemap(Request $request): Response
    {
        $site = $this->livePublication->resolveSiteByHost((string) $request->getHost());
        abort_if(! $site, 404);

        $pages = $this->livePublication->livePagesQuery($site)
            ->where('forced_noindex', false)
            ->orderByDesc('published_live_at')
            ->get();

        $xml = view('public.sitemap', [
            'site' => $site,
            'pages' => $pages,
            'livePublication' => $this->livePublication,
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
