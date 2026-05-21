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
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .prose-blog h2 { font-size: 1.5rem; font-weight: 700; color: #111827; margin-top: 2.5rem; margin-bottom: 1rem; line-height: 1.3; }
        .prose-blog h3 { font-size: 1.2rem; font-weight: 600; color: #1f2937; margin-top: 2rem; margin-bottom: 0.75rem; line-height: 1.4; }
        .prose-blog p  { color: #374151; line-height: 1.85; margin-bottom: 1.25rem; font-size: 1.0625rem; }
        .prose-blog ul { list-style: disc; padding-left: 1.5rem; color: #374151; margin-bottom: 1.25rem; }
        .prose-blog ul li { margin-bottom: 0.4rem; line-height: 1.75; }
        .prose-blog ol { list-style: decimal; padding-left: 1.5rem; color: #374151; margin-bottom: 1.25rem; }
        .prose-blog ol li { margin-bottom: 0.4rem; line-height: 1.75; }
        .prose-blog strong { font-weight: 600; color: #111827; }
        .prose-blog a { color: #4f46e5; text-decoration: underline; }
        .prose-blog blockquote { border-left: 4px solid #e5e7eb; padding-left: 1rem; color: #6b7280; font-style: italic; margin: 1.5rem 0; }
    </style>
</head>
<body class="bg-gray-50 antialiased">

{{-- Preview banner --}}
<div class="sticky top-0 z-50 bg-amber-400 text-amber-900 text-sm font-medium px-6 py-2.5 flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-3">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        <span>Prévisualisation — <strong>{{ $page->status }}</strong> · {{ $site->name }}</span>
        @if($page->status !== 'published')
        <span class="inline-flex items-center rounded-full bg-amber-200 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Non publié</span>
        @endif
    </div>
    <a href="{{ route('admin.pages.show', [$site->site_id, $page->id]) }}"
       class="text-xs font-semibold underline hover:no-underline">
        ← Retour à l'éditeur
    </a>
</div>

<article class="max-w-3xl mx-auto px-6 py-14">

    {{-- Meta SEO preview --}}
    <div class="mb-10 rounded-xl border border-dashed border-gray-300 bg-white px-5 py-4 text-xs text-gray-500 space-y-1.5">
        <div class="flex items-start gap-2">
            <span class="font-semibold text-gray-400 w-20 flex-shrink-0">Title</span>
            <span class="text-gray-700 font-medium">{{ $page->title ?: '—' }}</span>
        </div>
        <div class="flex items-start gap-2">
            <span class="font-semibold text-gray-400 w-20 flex-shrink-0">Meta desc</span>
            <span class="text-gray-600">{{ $page->meta_description ?: '—' }}</span>
        </div>
        <div class="flex items-start gap-2">
            <span class="font-semibold text-gray-400 w-20 flex-shrink-0">Slug</span>
            <span class="font-mono text-gray-500">{{ $page->canonicalPath() }}</span>
        </div>
        <div class="flex items-start gap-2">
            <span class="font-semibold text-gray-400 w-20 flex-shrink-0">Cluster</span>
            <span>{{ $page->cluster ?: '—' }}</span>
        </div>
    </div>

    {{-- Image --}}
    @php
        $imageUrl = null;
        if (filled($page->image_path)) {
            $imagePath = (string) $page->image_path;
            $imageUrl = Str::startsWith($imagePath, ['http://', 'https://', '/']) ? $imagePath : asset('storage/'.$imagePath);
        }
    @endphp
    @if($imageUrl)
    <div class="mb-10 rounded-2xl overflow-hidden aspect-video bg-gray-100">
        <img src="{{ $imageUrl }}" alt="{{ $page->image_alt ?: $page->keyword }}" class="w-full h-full object-cover">
    </div>
    @endif

    {{-- H1 --}}
    <h1 class="text-4xl font-extrabold text-gray-900 leading-tight mb-6">
        {{ $page->h1 ?: $page->title ?: $page->keyword }}
    </h1>

    {{-- Scores ribbon --}}
    <div class="flex flex-wrap gap-3 mb-10 pb-8 border-b border-gray-100">
        @foreach([
            ['label' => 'SEO', 'value' => $page->seo_score],
            ['label' => 'Qualité', 'value' => $page->quality_score],
            ['label' => 'Topical', 'value' => $page->topical_score],
            ['label' => 'Indexabilité', 'value' => $page->indexability_score],
        ] as $score)
        @if($score['value'])
        @php $v = (float) $score['value']; $color = $v >= 70 ? 'text-emerald-700 bg-emerald-50' : ($v >= 40 ? 'text-amber-700 bg-amber-50' : 'text-rose-700 bg-rose-50'); @endphp
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $color }}">
            {{ $score['label'] }} {{ number_format($v, 0) }}
        </span>
        @endif
        @endforeach
    </div>

    {{-- Content --}}
    @if($page->content)
    <div class="prose-blog mb-12">
        {!! $page->content !!}
    </div>
    @else
    <div class="mb-12 rounded-xl border border-dashed border-gray-200 px-6 py-10 text-center text-gray-400 text-sm">
        Aucun contenu généré pour cette page.
    </div>
    @endif

    {{-- FAQ --}}
    @if(!empty($page->faq_json))
    <section class="mb-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Questions fréquentes</h2>
        <div class="space-y-4">
            @foreach($page->faq_json as $item)
            @if(!empty($item['question']))
            <details class="group rounded-xl border border-gray-200 bg-white px-5 py-4 open:border-indigo-200">
                <summary class="flex cursor-pointer items-start justify-between gap-4 font-semibold text-gray-900 text-sm list-none">
                    <span>{{ $item['question'] }}</span>
                    <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    <section class="mb-12">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Articles liés</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($page->internal_links_json as $link)
            @if(!empty($link['url']))
            <a href="{{ $link['url'] }}"
               class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3.5 hover:border-indigo-300 hover:bg-indigo-50/30 transition-colors">
                <span class="text-sm font-medium text-gray-800 group-hover:text-indigo-700">
                    {{ $link['label'] ?? $link['url'] }}
                </span>
                <svg class="ml-auto h-4 w-4 flex-shrink-0 text-gray-300 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            @endif
            @endforeach
        </div>
    </section>
    @endif

    {{-- Review issues (only visible in preview, not on real site) --}}
    @if(!empty($page->review_issues_json))
    <div class="mt-10 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
        <div class="text-xs font-semibold uppercase tracking-wider text-amber-700 mb-2">Notes de révision (visible en preview seulement)</div>
        <ul class="space-y-1.5 text-sm text-amber-800">
            @foreach($page->review_issues_json as $issue)
            <li class="flex items-start gap-2">
                <span class="mt-0.5 text-amber-400">•</span>
                <span>{{ is_array($issue) ? ($issue['message'] ?? json_encode($issue)) : $issue }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif

</article>

</body>
</html>
