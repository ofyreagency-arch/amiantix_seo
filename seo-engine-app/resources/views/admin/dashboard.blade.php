@extends('admin.layout')

@section('title', 'Dashboard')

@section('breadcrumb')
    <span class="font-semibold text-gray-900">Dashboard Intelligence</span>
@endsection

@section('content')
@php
    $hasObserved       = ($stats['observed_pages'] ?? 0) > 0;
    $hasCrawls         = $recentCrawls->isNotEmpty();
    $hasRecommendations = $priorityRecommendations->isNotEmpty();
    $hasFeedback       = $feedbackQueue->isNotEmpty();
    $hasRewrites       = $rewriteQueue->isNotEmpty();
    $hasGraph          = $overlapHotspots->isNotEmpty();
    $hasQueries        = $queryHotspots->isNotEmpty();
    $hasWeakObserved   = $weakObservedPages->isNotEmpty();
    $isColdStart       = ! $hasObserved && ! $hasCrawls && ! $hasRecommendations && ! $hasGraph && ! $hasQueries && ! $hasWeakObserved;

    $topActions = collect()
        ->merge($priorityRecommendations->take(3)->map(fn ($item): array => [
            'title' => $item->title,
            'meta'  => trim($item->site_id.' · '.str_replace('_', ' ', (string) $item->type).($item->cluster ? ' · '.$item->cluster : '')),
            'badge' => 'P'.$item->priority,
            'tone'  => 'bg-emerald-50 text-emerald-700 border-emerald-100',
            'dot'   => 'bg-emerald-400',
        ]))
        ->merge($rewriteQueue->take(3)->map(fn ($item): array => [
            'title' => $item->page?->keyword ?? 'Rewrite pending',
            'meta'  => trim(($item->page?->site_id ?? 'n/a').' · '.$item->source),
            'badge' => 'rewrite',
            'tone'  => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-100',
            'dot'   => 'bg-fuchsia-400',
        ]))
        ->take(4);

    $queueRows = [
        ['label' => 'Feedback loop',   'value' => $queue['feedback'],         'color' => 'bg-indigo-500',  'hex' => '#6366f1'],
        ['label' => 'Signal queue',    'value' => $queue['signals'],          'color' => 'bg-cyan-500',    'hex' => '#06b6d4'],
        ['label' => 'Rewrites',        'value' => $queue['rewrites'],         'color' => 'bg-fuchsia-500', 'hex' => '#d946ef'],
        ['label' => 'Recommendations', 'value' => $queue['recommendations'],  'color' => 'bg-emerald-500', 'hex' => '#10b981'],
        ['label' => 'Bloqués',         'value' => $queue['rewrite_blocked'],  'color' => 'bg-rose-500',    'hex' => '#f43f5e'],
    ];

    $heroMetrics = [
        ['label' => 'Sites actifs',           'value' => $stats['total_sites'],                        'icon' => 'globe',   'color' => 'from-indigo-500 to-violet-600',  'bg' => 'bg-indigo-50',  'text' => 'text-indigo-600'],
        ['label' => 'Pages observées',        'value' => $stats['observed_pages'],                     'icon' => 'eye',     'color' => 'from-sky-500 to-cyan-600',       'bg' => 'bg-sky-50',     'text' => 'text-sky-600'],
        ['label' => 'Suggestions éditoriales','value' => $stats['editorial_suggestions_pending'],       'icon' => 'edit',    'color' => 'from-fuchsia-500 to-pink-600',   'bg' => 'bg-fuchsia-50', 'text' => 'text-fuchsia-600'],
        ['label' => 'Recommandations',        'value' => $stats['observed_recommendations_pending'],   'icon' => 'spark',   'color' => 'from-emerald-500 to-teal-600',   'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
    ];

    $tensionMetrics = [
        ['label' => 'Pages orphelines',       'value' => $intelligence['orphan_pages'],         'tone' => 'text-amber-600',  'bg' => 'bg-amber-50',  'border' => 'border-amber-100'],
        ['label' => 'Pages faibles',          'value' => $intelligence['weak_pages'],           'tone' => 'text-rose-600',   'bg' => 'bg-rose-50',   'border' => 'border-rose-100'],
        ['label' => 'Cannibalisation',        'value' => $intelligence['cannibalization_risks'],'tone' => 'text-orange-600', 'bg' => 'bg-orange-50', 'border' => 'border-orange-100'],
        ['label' => 'Piliers potentiels',     'value' => $intelligence['pillar_candidates'],    'tone' => 'text-emerald-600','bg' => 'bg-emerald-50','border' => 'border-emerald-100'],
    ];
@endphp

<div class="space-y-7 pb-4">

    {{-- ═══════════════════════════════════════════
         HERO SECTION
    ════════════════════════════════════════════ --}}
    <section class="relative rounded-3xl overflow-hidden anim-fade-up"
             style="background: linear-gradient(135deg, #0f0c29 0%, #1a1a3e 40%, #24243e 70%, #0d1117 100%);
                    box-shadow: 0 20px 60px rgba(99,102,241,0.15);">

        {{-- Decorative orbs --}}
        <div class="absolute top-0 left-1/4 w-96 h-96 rounded-full opacity-10 pointer-events-none"
             style="background: radial-gradient(circle, #6366f1 0%, transparent 70%); transform: translate(-50%,-50%);"></div>
        <div class="absolute bottom-0 right-1/4 w-80 h-80 rounded-full opacity-8 pointer-events-none"
             style="background: radial-gradient(circle, #8b5cf6 0%, transparent 70%); transform: translate(50%,50%);"></div>

        <div class="relative px-8 py-8">
            <div class="grid grid-cols-1 xl:grid-cols-[1fr_auto] gap-8 items-start">

                {{-- Left: title block --}}
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full mb-4 border border-indigo-500/20"
                         style="background:rgba(99,102,241,0.1);">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 status-dot"></span>
                        <span class="text-[11px] uppercase tracking-[0.18em] text-indigo-300/80 font-semibold">SEO Brain Runtime</span>
                    </div>

                    <h1 class="text-2xl lg:text-3xl font-bold text-white leading-snug mb-3">
                        Le moteur pilote des signaux<br class="hidden sm:block">
                        <span class="gradient-text">réels, pas juste des pages.</span>
                    </h1>
                    <p class="text-sm text-slate-300/70 leading-relaxed max-w-xl">
                        Observation, scoring, monitoring, feedback, rewrite et recommandations stabilisés.
                        Ce cockpit distingue la couche éditoriale interne, la couche observée réelle et les recommandations runtime.
                    </p>

                    @if($isColdStart)
                    <div class="mt-5 rounded-2xl border border-white/8 p-5" style="background:rgba(255,255,255,0.05);">
                        <div class="text-sm font-semibold text-white mb-1.5">État initial du moteur</div>
                        <div class="text-sm text-slate-300/70 mb-4">Le runtime n'a pas encore de couche observée. Lancez un crawl pour démarrer.</div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            @foreach(['1. Lancer un crawl','2. Alimenter le graph','3. Générer les opportunités'] as $i => $step)
                            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 border border-white/10 text-white/60 font-medium"
                                  style="background:rgba(255,255,255,0.06);">
                                <span class="w-4 h-4 rounded-full bg-indigo-500/40 text-indigo-300 flex items-center justify-center text-[9px] font-bold">{{ $i+1 }}</span>
                                {{ trim(preg_replace('/^\d+\. /', '', $step)) }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Right: action queue total --}}
                <div class="flex flex-col items-center justify-center rounded-2xl border border-white/8 px-8 py-6 text-center"
                     style="background:rgba(255,255,255,0.05); min-width:160px;">
                    <div class="text-[11px] uppercase tracking-widest text-slate-300/50 mb-1">Queue totale</div>
                    <div class="text-5xl font-bold text-white stat-number delay-200">{{ $stats['action_queue'] }}</div>
                    <div class="text-xs text-slate-300/50 mt-1">éléments actifs</div>
                    <div class="w-12 h-0.5 rounded-full bg-indigo-500/50 mx-auto mt-3"></div>
                    <div class="text-xs text-indigo-300/70 mt-2">Runtime live</div>
                </div>
            </div>

            {{-- Tension metrics bar --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-7">
                @foreach($tensionMetrics as $idx => $item)
                <div class="rounded-2xl border border-white/6 px-4 py-4 anim-fade-up delay-{{ ($idx+1)*100 }}"
                     style="background:rgba(255,255,255,0.04);">
                    <div class="text-[10px] uppercase tracking-wider text-slate-300/50 mb-2">{{ $item['label'] }}</div>
                    <div class="text-3xl font-bold {{ $item['tone'] }}">{{ $item['value'] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         KPI CARDS ROW
    ════════════════════════════════════════════ --}}
    <section class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach($heroMetrics as $i => $m)
        <div class="bg-white rounded-2xl border border-gray-100 p-5 card-hover anim-fade-up delay-{{ ($i+1)*100 }}"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="flex items-start justify-between mb-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                     style="background:linear-gradient(135deg, {{ explode(' to-', $m['color'])[0] === 'from-indigo-500' ? '#6366f1' : (str_contains($m['color'],'sky') ? '#0ea5e9' : (str_contains($m['color'],'fuchsia') ? '#d946ef' : '#10b981')) }}, {{ str_contains($m['color'],'violet') ? '#7c3aed' : (str_contains($m['color'],'cyan') ? '#0891b2' : (str_contains($m['color'],'pink') ? '#db2777' : '#0d9488')) }})">
                    @if($m['icon'] === 'globe')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                    </svg>
                    @elseif($m['icon'] === 'eye')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    @elseif($m['icon'] === 'edit')
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    @else
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                    @endif
                </div>
                <span class="text-xs text-gray-400 font-medium">Live</span>
            </div>
            <div class="text-3xl font-bold text-gray-900 stat-number">{{ $m['value'] }}</div>
            <div class="text-xs text-gray-500 mt-1 font-medium">{{ $m['label'] }}</div>
        </div>
        @endforeach
    </section>

    {{-- ═══════════════════════════════════════════
         SOURCES DE VÉRITÉ
    ════════════════════════════════════════════ --}}
    <section class="anim-fade-up delay-200">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base font-bold text-gray-900">Sources de vérité du cockpit</h2>
                <p class="text-xs text-gray-400 mt-0.5">Quatre couches distinctes, chacune avec son rôle précis.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @php
            $sources = [
                ['tag' => 'SeoPage',           'title' => 'Workflow éditorial',    'desc' => 'Généré, pending, review, publié côté moteur.',              'color' => 'from-indigo-500 to-violet-600', 'accent' => 'bg-indigo-50 text-indigo-600'],
                ['tag' => 'SeoSitePage',       'title' => 'Réalité observée',      'desc' => 'Crawlé, visible, structure, indexabilité, tensions réelles.','color' => 'from-sky-500 to-cyan-600',      'accent' => 'bg-sky-50 text-sky-600'],
                ['tag' => 'SeoSuggestion',     'title' => 'Suggestions éditoriales','desc' => 'Rewrites et actions legacy liées aux pages moteur.',         'color' => 'from-fuchsia-500 to-pink-600',  'accent' => 'bg-fuchsia-50 text-fuchsia-600'],
                ['tag' => 'SeoRecommendation', 'title' => 'Recommandations',       'desc' => 'Opportunités runtime issues du crawl, graph et signaux.',    'color' => 'from-emerald-500 to-teal-600',  'accent' => 'bg-emerald-50 text-emerald-600'],
            ];
            @endphp
            @foreach($sources as $i => $src)
            <div class="bg-white rounded-2xl border border-gray-100 p-5 card-hover anim-fade-up delay-{{ ($i+1)*100 }}"
                 style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold mb-3 {{ $src['accent'] }}">
                    {{ $src['tag'] }}
                </span>
                <div class="text-sm font-semibold text-gray-900 mb-1.5">{{ $src['title'] }}</div>
                <div class="text-xs text-gray-400 leading-relaxed">{{ $src['desc'] }}</div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         QUEUES + BACKLOG
    ════════════════════════════════════════════ --}}
    <section class="grid grid-cols-1 xl:grid-cols-[1.3fr_0.7fr] gap-6">

        {{-- Queue Chart Card --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-6 anim-fade-up delay-100"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Queues du moteur</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Séparation honnête suggestions éditoriales vs recommandations observed.</p>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-3xl font-bold text-gray-900">{{ $stats['action_queue'] }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">éléments actifs</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-[200px_1fr] gap-6 items-center">
                {{-- Donut chart --}}
                <div class="relative flex items-center justify-center">
                    <div class="relative w-48 h-48">
                        <canvas id="queueChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <div class="text-2xl font-bold text-gray-900">{{ $stats['action_queue'] }}</div>
                            <div class="text-xs text-gray-400">total</div>
                        </div>
                    </div>
                </div>

                {{-- Legend bars --}}
                <div class="space-y-2.5">
                    @php $total = max(1, array_sum(array_column($queueRows, 'value'))); @endphp
                    @foreach($queueRows as $i => $row)
                    <div class="anim-fade-left delay-{{ ($i+1)*100 }}">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $row['color'] }} shrink-0"></span>
                                <span class="text-sm text-gray-700 font-medium">{{ $row['label'] }}</span>
                            </div>
                            <span class="text-sm font-bold text-gray-900">{{ $row['value'] }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="{{ $row['color'] }} h-full rounded-full transition-all duration-700 grow-bar"
                                 style="--bar-width: {{ $total > 0 ? round($row['value']/$total*100) : 0 }}%; width: {{ $total > 0 ? round($row['value']/$total*100) : 0 }}%;"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Backlog prioritaire --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-200"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50">
                <h2 class="text-base font-bold text-gray-900">Backlog prioritaire</h2>
                <p class="text-xs text-gray-400 mt-0.5">Recommandations observed + rewrites éditoriaux.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($topActions as $idx => $item)
                <div class="px-6 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="w-1.5 h-1.5 rounded-full {{ $item['dot'] }} shrink-0 mt-1.5 status-dot"></span>
                            <div>
                                <div class="text-sm font-semibold text-gray-900 leading-snug">{{ $item['title'] }}</div>
                                <div class="mt-0.5 text-xs text-gray-400">{{ $item['meta'] }}</div>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold border {{ $item['tone'] }} shrink-0 badge-float">
                            {{ $item['badge'] }}
                        </span>
                    </div>
                </div>
                @empty
                <div class="px-6 py-10 text-center">
                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <div class="text-sm text-gray-400">Aucune recommandation pending.</div>
                </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         LIFECYCLE + REWRITES + SANTÉ SITES
    ════════════════════════════════════════════ --}}
    <section class="grid grid-cols-1 xl:grid-cols-[1fr_1fr_1.1fr] gap-6">
        <div class="xl:col-span-3 flex items-center justify-between mb-[-0.5rem]">
            <div>
                <h2 class="text-base font-bold text-gray-900">Queues réelles du moteur</h2>
                <p class="text-xs text-gray-400 mt-0.5">Suggestions, rewrites et recommandations réellement en circulation dans le runtime.</p>
            </div>
        </div>

        {{-- Lifecycle --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-100"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50">
                <h2 class="text-base font-bold text-gray-900">Lifecycle des actions</h2>
                <p class="text-xs text-gray-400 mt-0.5">Ce que le moteur garde vivant, applique ou clôture.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($actionLifecycle as $i => $row)
                <div class="px-6 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-sm font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $row['status']) }}</div>
                        <div class="text-xl font-bold text-gray-900">{{ $row['total'] }}</div>
                    </div>
                    <div class="mt-1.5 flex items-center gap-3 text-xs text-gray-400">
                        <span class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                            {{ $row['suggestions'] }} suggestions
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            {{ $row['recommendations'] }} recommandations
                        </span>
                    </div>
                </div>
                @empty
                <div class="px-6 py-10 text-center text-sm text-gray-400">Aucun état d'action à afficher.</div>
                @endforelse
            </div>
        </div>

        {{-- Rewrite queue --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-150"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Queue de rewrite</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Réécritures jugées utiles, encore en attente.</p>
                </div>
                @if($hasRewrites)
                <span class="inline-flex items-center rounded-full bg-fuchsia-50 border border-fuchsia-100 px-2.5 py-1 text-xs font-semibold text-fuchsia-700">
                    {{ $rewriteQueue->count() }}
                </span>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($rewriteQueue->take(4) as $suggestion)
                <div class="px-6 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="text-sm font-semibold text-gray-900 leading-snug">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</div>
                        <span class="inline-flex items-center rounded-full bg-fuchsia-50 border border-fuchsia-100 px-2 py-0.5 text-xs font-medium text-fuchsia-700 shrink-0">pending</span>
                    </div>
                    <div class="text-xs text-gray-400">{{ $suggestion->page?->site_id ?? 'n/a' }} · {{ $suggestion->source }}</div>
                    <div class="mt-2 text-xs text-gray-400 line-clamp-2 italic">
                        {{ collect(\Illuminate\Support\Arr::wrap($suggestion->suggestions_json['rationale'] ?? []))
                            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                            ->take(2)
                            ->implode(' · ') ?: 'Aucune rationale.' }}
                    </div>
                </div>
                @empty
                <div class="px-6 py-10 text-center">
                    <div class="text-sm text-gray-400">Aucun rewrite pending.</div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Santé multi-sites --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-200"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50">
                <h2 class="text-base font-bold text-gray-900">Santé multi-sites</h2>
                <p class="text-xs text-gray-400 mt-0.5">Vue hybride par site avec observation réelle.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($sites->take(4) as $row)
                @php
                    $score = $row['health_score'];
                    $healthColor = $score >= 70 ? 'text-emerald-600' : ($score >= 50 ? 'text-amber-600' : 'text-rose-600');
                    $healthBg    = $score >= 70 ? 'bg-emerald-500' : ($score >= 50 ? 'bg-amber-500' : 'bg-rose-500');
                @endphp
                <div class="px-6 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div>
                            <a href="{{ route('admin.sites.show', $row['site']->site_id) }}"
                               class="text-sm font-bold text-gray-900 hover:text-indigo-600 transition-colors">
                                {{ $row['site']->name }}
                            </a>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $row['site']->niche }} · {{ $row['site']->locale }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-2xl font-bold {{ $healthColor }}">{{ $score }}</div>
                            <div class="text-[10px] text-gray-400">score</div>
                        </div>
                    </div>
                    {{-- Score bar --}}
                    <div class="h-1 bg-gray-100 rounded-full overflow-hidden mb-3">
                        <div class="{{ $healthBg }} h-full rounded-full transition-all duration-700"
                             style="width: {{ min(100, $score) }}%"></div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 text-[11px]">
                        <span class="rounded-full bg-amber-50 border border-amber-100 px-2 py-0.5 text-amber-700">{{ $row['orphan_pages'] }} orphelines</span>
                        <span class="rounded-full bg-rose-50 border border-rose-100 px-2 py-0.5 text-rose-700">{{ $row['weak_pages'] }} faibles</span>
                        <span class="rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-emerald-700">{{ $row['pending_actions'] }} actions</span>
                    </div>
                </div>
                @empty
                <div class="px-6 py-10 text-center text-sm text-gray-400">Aucun site actif.</div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         RECOMMANDATIONS + CRAWLS + FEEDBACK
    ════════════════════════════════════════════ --}}
    <section class="grid grid-cols-1 xl:grid-cols-[1.2fr_0.8fr] gap-6">

        {{-- Recommandations observed --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-100"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Recommandations observed</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Actions proposées depuis le crawl, le graph et les tensions.</p>
                </div>
                @if($hasRecommendations)
                <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    {{ $priorityRecommendations->count() }}
                </span>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($priorityRecommendations->take(5) as $item)
                <div class="px-6 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-900 leading-snug">{{ $item->title }}</div>
                                <div class="mt-0.5 text-xs text-gray-400">{{ $item->site_id }} · {{ str_replace('_', ' ', (string) $item->type) }}@if($item->cluster) · {{ $item->cluster }}@endif</div>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700 shrink-0">
                            P{{ $item->priority }}
                        </span>
                    </div>
                </div>
                @empty
                <div class="px-6 py-10 text-center text-sm text-gray-400">Aucune recommandation observed pending.</div>
                @endforelse
            </div>
        </div>

        <div class="space-y-5">

            {{-- Crawls récents --}}
            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-150"
                 style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
                <div class="px-6 py-5 border-b border-gray-50">
                    <h2 class="text-base font-bold text-gray-900">Crawls récents</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Derniers passages de la couche observée.</p>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($recentCrawls->take(4) as $crawl)
                    <div class="px-6 py-4 flex items-start justify-between gap-3 hover:bg-gray-50/60 transition-colors">
                        <div class="flex items-start gap-3">
                            @php
                                $cStatus = $crawl->status;
                                $cIcon   = $cStatus === 'completed' ? 'check' : ($cStatus === 'running' ? 'spin' : 'clock');
                                $cColor  = $cStatus === 'completed' ? 'bg-emerald-100 text-emerald-600' : ($cStatus === 'running' ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-500');
                            @endphp
                            <div class="w-8 h-8 rounded-lg {{ $cColor }} flex items-center justify-center shrink-0">
                                @if($cIcon === 'check')
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                @elseif($cIcon === 'spin')
                                <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                @else
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-900">{{ $crawl->site_id }}</div>
                                <div class="text-xs text-gray-400 mt-0.5">{{ $crawl->crawled_url_count }}/{{ $crawl->discovered_url_count }} URLs · {{ ($crawl->completed_at ?? $crawl->started_at)?->diffForHumans() ?? 'en attente' }}</div>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold border shrink-0
                                     {{ $crawl->status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : ($crawl->status === 'running' ? 'bg-amber-50 text-amber-700 border-amber-100' : 'bg-gray-50 text-gray-600 border-gray-100') }}">
                            {{ $crawl->status }}
                        </span>
                    </div>
                    @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-400">Aucun crawl récent.</div>
                    @endforelse
                </div>
            </div>

            {{-- Pages moteur récentes --}}
            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-200"
                 style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
                <div class="px-6 py-5 border-b border-gray-50">
                    <h2 class="text-base font-bold text-gray-900">Pages moteur récentes</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Activité récente de la couche éditoriale.</p>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($recent->take(4) as $page)
                    @php
                        $pageStatusCls = match($page->status) {
                            'published' => 'bg-emerald-50 text-emerald-600',
                            'generated' => 'bg-sky-50 text-sky-600',
                            default     => 'bg-gray-100 text-gray-500',
                        };
                    @endphp
                    <div class="px-6 py-4 flex items-start justify-between gap-4 hover:bg-gray-50/60 transition-colors">
                        <div>
                            <div class="text-sm font-semibold text-gray-900 leading-snug">{{ $page->keyword }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $page->site_id }} · {{ $page->updated_at?->diffForHumans() }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-lg font-bold text-gray-900">{{ $page->seo_score ?: '—' }}</div>
                            <div class="mt-0.5 text-xs px-2 py-0.5 rounded-full inline-block {{ $pageStatusCls }}">
                                {{ $page->status }}
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-400">Aucune page récente.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         HOTSPOTS (graph, queries, pages faibles)
    ════════════════════════════════════════════ --}}
    @if($hasGraph || $hasQueries || $hasWeakObserved)
    <section class="space-y-5 anim-fade-up delay-200">

        @if($hasGraph)
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900">Hotspots du graph</h2>
                    <p class="text-xs text-gray-400">Tensions et collisions sémantiques détectées.</p>
                </div>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($overlapHotspots->take(4) as $edge)
                <div class="px-6 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ $edge['source'] }} → {{ $edge['target'] }}</div>
                            <div class="mt-0.5 text-xs text-gray-400">{{ $edge['site_id'] }} · {{ str_replace('_', ' ', $edge['type']) }}</div>
                            <div class="mt-1.5 text-xs text-gray-400 italic">{{ str_replace('_', ' ', $edge['reason']) }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700 shrink-0">{{ $edge['score'] }}%</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($hasQueries)
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900">Query hotspots</h2>
                    <p class="text-xs text-gray-400">Queries observées poussant une création ou un refresh.</p>
                </div>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($queryHotspots->take(4) as $item)
                <div class="px-6 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ $item['query'] }}</div>
                            <div class="mt-0.5 text-xs text-gray-400">{{ $item['site_id'] }} · cible {{ $item['page'] }}</div>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-400">
                                <span>{{ str_replace('_', ' ', $item['action']) }}</span>
                                <span>{{ $item['impressions'] }} impressions</span>
                                <span>position {{ $item['position'] }}</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-cyan-50 border border-cyan-100 px-2.5 py-1 text-xs font-bold text-cyan-700 shrink-0">{{ $item['score'] }}%</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($hasWeakObserved)
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900">Pages observées sous tension</h2>
                    <p class="text-xs text-gray-400">Pages jugées les plus fragiles dans la couche observée.</p>
                </div>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($weakObservedPages->take(4) as $page)
                <div class="px-6 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ $page->title ?: $page->path }}</div>
                            <div class="mt-0.5 text-xs text-gray-400">{{ $page->site_id }}@if($page->cluster_label) · {{ $page->cluster_label }}@endif</div>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-400">
                                <span>santé {{ (int) $page->health_score }}</span>
                                <span>autorité {{ round(((float) $page->authority_score) * 100) }}%</span>
                                <span>{{ (int) $page->latest_word_count }} mots</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold border shrink-0
                                     {{ $page->indexability_state === 'indexable' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100' }}">
                            {{ $page->indexability_state }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </section>
    @endif

    {{-- Feedback queue --}}
    @if($hasFeedback)
    <section class="anim-fade-up delay-200">
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
             style="box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Queue feedback & signaux</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Suggestions éditoriales issues des boucles legacy.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                    {{ $feedbackQueue->count() }}
                </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-50">
                @foreach($feedbackQueue->take(4) as $suggestion)
                <div class="px-6 py-4 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="text-sm font-semibold text-gray-900">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</div>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 shrink-0">pending</span>
                    </div>
                    <div class="text-xs text-gray-400">{{ $suggestion->page?->site_id ?? 'n/a' }} · {{ $suggestion->source }}</div>
                    <div class="mt-2 text-xs text-gray-400 line-clamp-2 italic">
                        {{ collect(\Illuminate\Support\Arr::wrap($suggestion->suggestions_json['rationale'] ?? []))
                            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                            ->take(2)
                            ->implode(' · ') ?: 'Aucune rationale.' }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Donut Queue Chart ─────────────────────────────
    const queueCtx = document.getElementById('queueChart');
    if (queueCtx) {
        new Chart(queueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Feedback loop', 'Signal queue', 'Rewrites', 'Recommendations', 'Bloqués'],
                datasets: [{
                    data: [
                        {{ $queue['feedback'] }},
                        {{ $queue['signals'] }},
                        {{ $queue['rewrites'] }},
                        {{ $queue['recommendations'] }},
                        {{ $queue['rewrite_blocked'] }},
                    ],
                    backgroundColor: ['#6366f1','#06b6d4','#d946ef','#10b981','#f43f5e'],
                    borderWidth: 0,
                    hoverOffset: 8,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111827',
                        titleColor: '#f9fafb',
                        bodyColor: '#d1d5db',
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.raw}`
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 800,
                    easing: 'easeOutQuart',
                }
            }
        });
    }

    // ── Animate numbers on scroll ────────────────────
    const animNumbers = () => {
        document.querySelectorAll('.stat-number').forEach(el => {
            const target = parseInt(el.textContent.replace(/\D/g, ''), 10);
            if (isNaN(target) || target === 0) return;
            let start = 0;
            const duration = 800;
            const step = timestamp => {
                if (!start) start = timestamp;
                const progress = Math.min((timestamp - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.floor(eased * target);
                if (progress < 1) requestAnimationFrame(step);
                else el.textContent = target;
            };
            requestAnimationFrame(step);
        });
    };

    // Intersection observer for entrance animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.anim-fade-up, .anim-fade-left, .anim-scale-in').forEach(el => {
        observer.observe(el);
    });

    animNumbers();
});
</script>
@endpush
