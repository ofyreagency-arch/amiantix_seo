<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($pages as $page)
    @if($page->live_url)
    <url>
        <loc>{{ $page->live_url }}</loc>
        @if($page->last_published_at)
        <lastmod>{{ $page->last_published_at->toAtomString() }}</lastmod>
        @endif
    </url>
    @endif
@endforeach
</urlset>
