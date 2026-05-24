<!doctype html>
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
        <meta name="robots" content="noindex,nofollow">
    @endif
</head>
<body>
    <article>
        <h1>{{ $page->h1 ?: $page->title }}</h1>
        {!! $page->content_html !!}

        @if(!empty($page->faq_json))
            <section>
                <h2>FAQ</h2>
                @foreach($page->faq_json as $item)
                    <div>
                        <h3>{{ $item['question'] ?? '' }}</h3>
                        <div>{!! $item['answer'] ?? '' !!}</div>
                    </div>
                @endforeach
            </section>
        @endif
    </article>
</body>
</html>
