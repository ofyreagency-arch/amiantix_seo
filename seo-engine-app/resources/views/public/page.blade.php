<!doctype html>
<html lang="{{ $site->locale ?? 'fr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->title ?: $page->keyword }}</title>
    @if($page->meta_description)
    <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @if($page->forced_noindex)
    <meta name="robots" content="noindex, nofollow">
    @else
    <meta name="robots" content="index, follow">
    @endif
    <link rel="canonical" href="{{ $page->live_url ?: rtrim($site->url, '/').$page->canonicalPath() }}">

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ $page->title ?: $page->keyword }}">
    @if($page->meta_description)
    <meta property="og:description" content="{{ $page->meta_description }}">
    @endif
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ $page->live_url ?: rtrim($site->url, '/').$page->canonicalPath() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * { font-family: 'Inter', sans-serif; }

        /* ── Prose typography ── */
        .prose-article h2 {
            font-size: 1.6rem; font-weight: 800; color: #0f172a;
            margin-top: 3rem; margin-bottom: 1.1rem;
            line-height: 1.28; letter-spacing: -0.025em;
            padding-bottom: 0.6rem; border-bottom: 2px solid #e2e8f0;
        }
        .prose-article h3 {
            font-size: 1.2rem; font-weight: 700; color: #1e293b;
            margin-top: 2.25rem; margin-bottom: 0.85rem; line-height: 1.4;
        }
        .prose-article h4 {
            font-size: 1rem; font-weight: 700; color: #334155;
            margin-top: 1.75rem; margin-bottom: 0.6rem;
        }
        .prose-article p {
            color: #374151; line-height: 1.9; margin-bottom: 1.5rem; font-size: 1.05rem;
        }
        .prose-article ul {
            list-style: none; padding-left: 0; margin-bottom: 1.5rem;
        }
        .prose-article ul li {
            padding-left: 1.75rem; position: relative;
            margin-bottom: 0.6rem; line-height: 1.8; color: #374151; font-size: 1.05rem;
        }
        .prose-article ul li::before {
            content: ''; position: absolute; left: 0; top: 0.7rem;
            width: 0.4rem; height: 0.4rem; border-radius: 50%;
            background: #6366f1;
        }
        .prose-article ol {
            list-style: decimal; padding-left: 1.5rem; margin-bottom: 1.5rem; color: #374151;
        }
        .prose-article ol li { margin-bottom: 0.6rem; line-height: 1.8; font-size: 1.05rem; }
        .prose-article strong { font-weight: 700; color: #111827; }
        .prose-article em     { font-style: italic; color: #4b5563; }
        .prose-article a      { color: #4f46e5; text-decoration: underline; text-decoration-color: rgba(99,102,241,0.3); font-weight: 500; }
        .prose-article a:hover { text-decoration-color: #4f46e5; }
        .prose-article blockquote {
            border-left: 3px solid #6366f1;
            padding: 1.1rem 1.5rem; margin: 2rem 0;
            background: linear-gradient(135deg, #f8f9ff, #faf5ff);
            border-radius: 0 1rem 1rem 0;
            color: #4b5563; font-style: italic; font-size: 1.05rem; line-height: 1.85;
        }
        .prose-article table { width: 100%; border-collapse: collapse; margin: 2rem 0; font-size: 0.9375rem; }
        .prose-article th, .prose-article td { border: 1px solid #e2e8f0; padding: 0.75rem 1rem; text-align: left; vertical-align: top; }
        .prose-article th { background: #f8fafc; color: #0f172a; font-weight: 700; }
        .prose-article code { background: #f1f5f9; color: #7c3aed; font-size: 0.875em; padding: 0.15em 0.4em; border-radius: 0.3em; font-family: ui-monospace, monospace; }

        /* ── FAQ ── */
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item[open] { background: #fafbff; }
    </style>

    @if(!empty($page->schema_json))
    @foreach(\Illuminate\Support\Arr::wrap($page->schema_json) as $schemaBlock)
    <script type="application/ld+json">@json($schemaBlock, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
    @endforeach
    @endif
</head>

<body class="bg-slate-50 antialiased">

@php
    $imageUrl = null;
    if (filled($page->image_path)) {
        $imagePath = (string) $page->image_path;
        $imageUrl  = \Illuminate\Support\Str::startsWith($imagePath, ['http://', 'https://', '/']) ? $imagePath : asset('storage/'.$imagePath);
    }
@endphp

{{-- ═══ NAVIGATION ═══ --}}
<nav class="sticky top-0 z-40 border-b border-slate-200/60 bg-white/80"
     style="backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);">
    <div class="max-w-5xl mx-auto px-6 h-14 flex items-center justify-between">
        <a href="{{ rtrim($site->url, '/') }}"
           class="text-sm font-black text-slate-900 tracking-tight hover:text-indigo-600 transition-colors">
            {{ $site->name }}
        </a>
        @if($page->cluster)
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-widest">{{ $page->cluster }}</span>
        @endif
    </div>
</nav>

<article>

    {{-- ═══ HERO ═══ --}}
    <header class="max-w-3xl mx-auto px-6 pt-14 pb-10">
        @if($page->cluster)
        <div class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-3.5 py-1.5 text-xs font-bold text-indigo-600 mb-6 uppercase tracking-widest">
            {{ $page->cluster }}
        </div>
        @endif
        <h1 class="text-4xl sm:text-5xl font-black text-slate-900 leading-tight tracking-tight mb-6">
            {{ $page->h1 ?: $page->title ?: $page->keyword }}
        </h1>
        @if($page->meta_description)
        <p class="text-lg sm:text-xl text-slate-500 leading-relaxed font-normal">{{ $page->meta_description }}</p>
        @endif
    </header>

    {{-- ═══ FEATURED IMAGE ═══ --}}
    @if($imageUrl)
    <div class="max-w-5xl mx-auto px-6 mb-14">
        <div class="overflow-hidden rounded-3xl border border-slate-200/60 bg-white"
             style="box-shadow:0 8px 40px rgba(0,0,0,0.08);">
            <img src="{{ $imageUrl }}"
                 alt="{{ $page->image_alt ?: $page->keyword }}"
                 class="w-full aspect-16/7 object-cover">
        </div>
    </div>
    @endif

    {{-- ═══ CONTENT ═══ --}}
    <div class="max-w-3xl mx-auto px-6 mb-16">
        @if($page->content)
        <div class="prose-article bg-white rounded-3xl border border-slate-200/60 px-8 py-10"
             style="box-shadow:0 2px 16px rgba(0,0,0,0.04);">
            {!! \Illuminate\Support\Str::markdown((string) $page->content) !!}
        </div>
        @endif
    </div>

    {{-- ═══ FAQ ═══ --}}
    @if(!empty($page->faq_json))
    <section class="max-w-3xl mx-auto px-6 mb-16">
        <div class="bg-white rounded-3xl border border-slate-200/60 px-8 py-10"
             style="box-shadow:0 2px 16px rgba(0,0,0,0.04);">
            <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-8">Questions fréquentes</h2>
            <div class="space-y-3">
                @foreach($page->faq_json as $item)
                @if(!empty($item['question']))
                <details class="faq-item group rounded-2xl border border-slate-200 px-5 py-4 transition-all hover:border-indigo-200"
                         style="box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                    <summary class="flex cursor-pointer items-start justify-between gap-4 list-none font-semibold text-slate-900 text-sm">
                        <span class="leading-relaxed">{{ $item['question'] }}</span>
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-400 group-open:rotate-180 group-open:text-indigo-500 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    @if(!empty($item['answer']))
                    <p class="mt-4 text-sm leading-relaxed text-slate-600">{{ $item['answer'] }}</p>
                    @endif
                </details>
                @endif
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══ INTERNAL LINKS ═══ --}}
    @if(!empty($page->internal_links_json))
    <section class="max-w-3xl mx-auto px-6 mb-16">
        <h2 class="text-xl font-black text-slate-900 tracking-tight mb-5">Articles liés</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($page->internal_links_json as $link)
            @if(!empty($link['url']))
            <a href="{{ $link['url'] }}"
               class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 hover:border-indigo-200 hover:shadow-md transition-all"
               style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold text-slate-700 group-hover:text-indigo-700 flex-1 transition-colors">
                    {{ $link['label'] ?? $link['url'] }}
                </span>
                <svg class="h-4 w-4 shrink-0 text-slate-300 group-hover:text-indigo-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            @endif
            @endforeach
        </div>
    </section>
    @endif

</article>

{{-- ═══ FOOTER ═══ --}}
<footer class="border-t border-slate-200 mt-20">
    <div class="max-w-5xl mx-auto px-6 py-10 flex items-center justify-between text-sm text-slate-400">
        <span class="font-semibold text-slate-600">{{ $site->name }}</span>
        <span>© {{ date('Y') }}</span>
    </div>
</footer>

</body>
</html>
