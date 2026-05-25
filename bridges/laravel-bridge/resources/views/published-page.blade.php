<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $page->title }}</title>
    @if($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @if($page->canonical_url)
        <link rel="canonical" href="{{ $page->canonical_url }}">
    @endif
    @if($page->is_noindex)
        <meta name="robots" content="noindex,follow">
    @endif
</head>
<body>
    <article>
        <h1>{{ $page->h1 ?: $page->title }}</h1>
        {!! $page->content_html !!}
    </article>
</body>
</html>
