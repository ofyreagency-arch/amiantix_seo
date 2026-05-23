<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($pages as $page)
    <url>
        <loc>{{ $page->live_url ?: $livePublication->liveUrlFor($page, $site) }}</loc>
        <lastmod>{{ optional($page->published_live_at ?? $page->updated_at)->toDateString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
</urlset>
