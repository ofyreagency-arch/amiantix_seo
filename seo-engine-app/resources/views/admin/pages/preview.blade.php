<!doctype html>
<html lang="{{ $site->locale ?? 'fr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page->title ?: $page->keyword }} — Prévisualisation</title>
    @if($page->meta_description)
    <meta name="description" content="{{ $page->meta_description }}">
    @endif
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .prose-blog h2 { font-size: 1.5rem; font-weight: 800; color: #111827; margin-top: 2.5rem; margin-bottom: 1rem; line-height: 1.3; letter-spacing: -0.02em; }
        .prose-blog h3 { font-size: 1.15rem; font-weight: 700; color: #1f2937; margin-top: 2rem; margin-bottom: 0.75rem; line-height: 1.4; }
        .prose-blog p  { color: #374151; line-height: 1.9; margin-bottom: 1.35rem; font-size: 1.0625rem; }
        .prose-blog ul { list-style: none; padding-left: 0; color: #374151; margin-bottom: 1.35rem; }
        .prose-blog ul li { padding-left: 1.5rem; position: relative; margin-bottom: 0.5rem; line-height: 1.8; }
        .prose-blog ul li::before { content: '—'; position: absolute; left: 0; color: #9ca3af; font-weight: 700; }
        .prose-blog ol { list-style: decimal; padding-left: 1.5rem; color: #374151; margin-bottom: 1.35rem; }
        .prose-blog ol li { margin-bottom: 0.5rem; line-height: 1.8; }
        .prose-blog strong { font-weight: 700; color: #111827; }
        .prose-blog a { color: #4f46e5; text-decoration: underline; text-decoration-color: rgba(79,70,229,0.3); }
        .prose-blog blockquote { border-left: 3px solid #6366f1; padding: 1rem 1.25rem; color: #6b7280; font-style: italic; margin: 1.75rem 0; background: #f8f9ff; border-radius: 0 0.75rem 0.75rem 0; }
        .faq-item summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="bg-gray-50 antialiased min-h-screen">

{{-- ═══ PREVIEW BANNER ═══ --}}
<div class="sticky top-0 z-50 flex items-center justify-between px-6 py-3 text-sm font-semibold shadow-sm"
     style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#78350f;">
    <div class="flex items-center gap-3">
        <div class="w-6 h-6 rounded-full bg-amber-900/20 flex items-center justify-center">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        </div>
        <span>Prévisualisation <strong class="font-black">{{ $site->name }}</strong></span>
        @if($page->status !== 'published')
        <span class="inline-flex items-center rounded-full bg-amber-900/15 px-2.5 py-0.5 text-xs font-bold">Non publié · {{ $page->status }}</span>
        @endif
    </div>
    <a href="{{ route('admin.pages.show', [$site->site_id, $page->id]) }}"
       class="text-xs font-bold underline hover:no-underline opacity-80 hover:opacity-100 transition-opacity">
        ← Retour à l'éditeur
    </a>
</div>

<main class="max-w-2xl mx-auto px-6 py-14">

    {{-- SEO meta strip --}}
    <div class="mb-10 rounded-2xl border border-dashed border-gray-200 bg-white px-5 py-4 text-xs space-y-2"
         style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
        <div class="flex items-start gap-3">
            <span class="font-bold text-gray-400 w-20 shrink-0">Title</span>
            <span class="text-gray-800 font-semibold">{{ $page->title ?: '—' }}</span>
        </div>
        <div class="flex items-start gap-3">
            <span class="font-bold text-gray-400 w-20 shrink-0">Meta desc</span>
            <span class="text-gray-600">{{ $page->meta_description ?: '—' }}</span>
        </div>
        <div class="flex items-start gap-3">
            <span class="font-bold text-gray-400 w-20 shrink-0">Slug</span>
            <span class="font-mono text-gray-500">{{ $page->canonicalPath() }}</span>
        </div>
        <div class="flex items-start gap-3">
            <span class="font-bold text-gray-400 w-20 shrink-0">Cluster</span>
            <span class="text-gray-600">{{ $page->cluster ?: '—' }}</span>
        </div>
    </div>

    {{-- Featured image --}}
    @php
        $imageUrl = null;
        if (filled($page->image_path)) {
            $imagePath = (string) $page->image_path;
            $imageUrl  = Str::startsWith($imagePath, ['http://', 'https://', '/']) ? $imagePath : asset('storage/'.$imagePath);
        }
    @endphp
    @if($imageUrl)
    <div class="mb-10 rounded-3xl overflow-hidden aspect-video bg-gray-100 border border-gray-100"
         style="box-shadow:0 4px 24px rgba(0,0,0,0.07);">
        <img src="{{ $imageUrl }}" alt="{{ $page->image_alt ?: $page->keyword }}" class="w-full h-full object-cover">
    </div>
    @endif

    {{-- Score badges --}}
    <div class="flex flex-wrap gap-2.5 mb-8 pb-8 border-b border-gray-100">
        @foreach([
            ['label' => 'SEO',        'value' => $page->seo_score],
            ['label' => 'Qualité',    'value' => $page->quality_score],
            ['label' => 'Topical',    'value' => $page->topical_score],
            ['label' => 'Indexabilité','value' => $page->indexability_score],
        ] as $score)
        @if($score['value'])
        @php $v = (float) $score['value']; @endphp
        @if($v >= 70)
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-100">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>{{ $score['label'] }} {{ number_format($v, 0) }}
        </span>
        @elseif($v >= 40)
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold text-amber-700 bg-amber-50 border border-amber-100">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span>{{ $score['label'] }} {{ number_format($v, 0) }}
        </span>
        @else
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold text-rose-700 bg-rose-50 border border-rose-100">
            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 inline-block"></span>{{ $score['label'] }} {{ number_format($v, 0) }}
        </span>
        @endif
        @endif
        @endforeach
    </div>

    {{-- H1 --}}
    <h1 class="text-4xl font-black text-gray-900 leading-tight tracking-tight mb-10">
        {{ $page->h1 ?: $page->title ?: $page->keyword }}
    </h1>

    {{-- Content --}}
    @if($page->content)
    <div class="prose-blog mb-14">
        {!! Str::markdown((string) $page->content) !!}
    </div>
    @else
    <div class="mb-14 rounded-2xl border border-dashed border-gray-200 px-6 py-12 text-center text-gray-400 text-sm">
        Aucun contenu généré pour cette page.
    </div>
    @endif

    {{-- FAQ --}}
    @if(!empty($page->faq_json))
    <section class="mb-14">
        <h2 class="text-2xl font-black text-gray-900 tracking-tight mb-6">Questions fréquentes</h2>
        <div class="space-y-3">
            @foreach($page->faq_json as $item)
            @if(!empty($item['question']))
            <details class="faq-item group rounded-2xl border border-gray-200 bg-white px-5 py-4 open:border-indigo-200 open:bg-indigo-50/20 transition-all"
                     style="box-shadow:0 1px 4px rgba(0,0,0,0.03);">
                <summary class="flex cursor-pointer items-start justify-between gap-4 font-semibold text-gray-900 text-sm list-none">
                    <span>{{ $item['question'] }}</span>
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                @if(!empty($item['answer']))
                <p class="mt-3 text-sm text-gray-600 leading-relaxed">{{ $item['answer'] }}</p>
                @endif
            </details>
            @endif
            @endforeach
        </div>
    </section>
    @endif

    {{-- Internal links --}}
    @if(!empty($page->internal_links_json))
    <section class="mb-14">
        <h2 class="text-xl font-black text-gray-900 tracking-tight mb-5">Articles liés</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($page->internal_links_json as $link)
            @if(!empty($link['url']))
            <a href="{{ $link['url'] }}"
               class="group flex items-center gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-4 hover:border-indigo-200 hover:bg-indigo-50/30 transition-all"
               style="box-shadow:0 1px 4px rgba(0,0,0,0.03);">
                <span class="text-sm font-semibold text-gray-800 group-hover:text-indigo-700 flex-1">
                    {{ $link['label'] ?? $link['url'] }}
                </span>
                <svg class="h-4 w-4 shrink-0 text-gray-300 group-hover:text-indigo-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            @endif
            @endforeach
        </div>
    </section>
    @endif

    {{-- Review issues (preview only) --}}
    @if(!empty($page->review_issues_json))
    <div class="mt-10 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-5">
        <p class="text-xs font-bold uppercase tracking-widest text-amber-700 mb-3">Notes de révision (visible en preview seulement)</p>
        <ul class="space-y-2 text-sm text-amber-800">
            @foreach($page->review_issues_json as $issue)
            <li class="flex items-start gap-2">
                <span class="mt-0.5 text-amber-400 shrink-0">•</span>
                <span>{{ is_array($issue) ? ($issue['message'] ?? json_encode($issue)) : $issue }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

</main>

</body>
</html>
