@extends('admin.layout')

@section('title', 'Dashboard')

@section('breadcrumb')
    <span class="font-semibold text-gray-900">Dashboard</span>
@endsection

@section('content')
@php
    $hasObserved        = ($stats['observed_pages'] ?? 0) > 0;
    $hasCrawls          = $recentCrawls->isNotEmpty();
    $hasRecommendations = $priorityRecommendations->isNotEmpty();
    $hasFeedback        = $feedbackQueue->isNotEmpty();
    $hasRewrites        = $rewriteQueue->isNotEmpty();
    $hasGraph           = $overlapHotspots->isNotEmpty();
    $hasQueries         = $queryHotspots->isNotEmpty();
    $hasWeakObserved    = $weakObservedPages->isNotEmpty();
    $isColdStart        = ! $hasObserved && ! $hasCrawls && ! $hasRecommendations && ! $hasGraph && ! $hasQueries && ! $hasWeakObserved;

    $topActions = collect()
        ->merge($priorityRecommendations->take(3)->map(fn ($item): array => [
            'title' => $item->title,
            'meta'  => trim($item->site_id.' · '.str_replace('_', ' ', (string) $item->type).($item->cluster ? ' · '.$item->cluster : '')),
            'badge' => 'P'.$item->priority,
            'tone'  => 'bg-emerald-50 text-emerald-700',
            'dot'   => 'bg-emerald-400',
        ]))
        ->merge($rewriteQueue->take(3)->map(fn ($item): array => [
            'title' => $item->page?->keyword ?? 'Rewrite pending',
            'meta'  => trim(($item->page?->site_id ?? 'n/a').' · '.$item->source),
            'badge' => 'rewrite',
            'tone'  => 'bg-violet-50 text-violet-700',
            'dot'   => 'bg-violet-400',
        ]))
        ->take(5);

    $queueRows = [
        ['label' => 'Feedback loop',   'value' => $queue['feedback'],         'color' => 'bg-indigo-500',  'hex' => '#6366f1'],
        ['label' => 'Signal queue',    'value' => $queue['signals'],          'color' => 'bg-cyan-500',    'hex' => '#06b6d4'],
        ['label' => 'Rewrites',        'value' => $queue['rewrites'],         'color' => 'bg-violet-500',  'hex' => '#8b5cf6'],
        ['label' => 'Recommandations', 'value' => $queue['recommendations'],  'color' => 'bg-emerald-500', 'hex' => '#10b981'],
        ['label' => 'Bloqués',         'value' => $queue['rewrite_blocked'],  'color' => 'bg-rose-400',    'hex' => '#fb7185'],
    ];

    $kpis = [
        ['label' => 'Sites actifs',            'value' => $stats['total_sites'],                      'icon' => 'globe',  'color' => 'text-indigo-600', 'bg' => 'bg-indigo-50'],
        ['label' => 'Pages observées',         'value' => $stats['observed_pages'],                   'icon' => 'eye',    'color' => 'text-sky-600',    'bg' => 'bg-sky-50'],
        ['label' => 'Suggestions en attente',  'value' => $stats['editorial_suggestions_pending'],    'icon' => 'edit',   'color' => 'text-violet-600', 'bg' => 'bg-violet-50'],
        ['label' => 'Recommandations',         'value' => $stats['observed_recommendations_pending'], 'icon' => 'spark',  'color' => 'text-emerald-600','bg' => 'bg-emerald-50'],
    ];

    $tensions = [
        ['label' => 'Pages orphelines',  'value' => $intelligence['orphan_pages'],          'color' => 'text-amber-600',  'dot' => 'bg-amber-400'],
        ['label' => 'Pages faibles',     'value' => $intelligence['weak_pages'],            'color' => 'text-rose-600',   'dot' => 'bg-rose-400'],
        ['label' => 'Cannibalisation',   'value' => $intelligence['cannibalization_risks'], 'color' => 'text-orange-600', 'dot' => 'bg-orange-400'],
        ['label' => 'Piliers potentiels','value' => $intelligence['pillar_candidates'],     'color' => 'text-emerald-600','dot' => 'bg-emerald-400'],
    ];
@endphp

<div class="admin-dashboard-shell">
<div class="space-y-5 pb-4">

    {{-- ═══ PAGE HEADER ═══ --}}
    <div class="flex items-start justify-between gap-4 anim-fade-up">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Dashboard Intelligence</h1>
            <p class="text-sm text-gray-500 mt-0.5">Vue globale du moteur SEO — observation, scoring, queues et recommandations.</p>
        </div>
        <div class="hidden sm:flex items-center gap-2 shrink-0">
            <div class="rounded-lg border border-gray-100 bg-white px-3 py-2 text-center" style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div class="text-2xl font-bold text-gray-900 stat-number">{{ $stats['action_queue'] }}</div>
                <div class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mt-0.5">Queue active</div>
            </div>
        </div>
    </div>

    {{-- ═══ COLD START ═══ --}}
    @if($isColdStart)
    <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-5 py-4 anim-fade-up">
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <div class="text-sm font-semibold text-indigo-900 mb-1">État initial du moteur</div>
                <div class="text-sm text-indigo-700 mb-3">Le runtime n'a pas encore de couche observée. Lancez un crawl pour démarrer.</div>
                <div class="flex flex-wrap gap-2">
                    @foreach(['Lancer un crawl','Alimenter le graph','Générer les opportunités'] as $i => $step)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white border border-indigo-100 px-3 py-1 text-xs font-medium text-indigo-700">
                        <span class="w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-[9px] font-bold">{{ $i+1 }}</span>
                        {{ $step }}
                    </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══ KPI CARDS ═══ --}}
    <section class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach($kpis as $i => $m)
        <div class="bg-white rounded-xl border border-gray-100 p-4 card-hover anim-fade-up delay-{{ ($i+1)*50 }}"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="flex items-center justify-between mb-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center {{ $m['bg'] }} {{ $m['color'] }}">
                    @if($m['icon'] === 'globe')
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    @elseif($m['icon'] === 'eye')
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    @elseif($m['icon'] === 'edit')
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    @endif
                </div>
                <span class="text-[10px] text-gray-400 font-medium uppercase tracking-wide">Live</span>
            </div>
            <div class="text-2xl font-bold text-gray-900 stat-number">{{ $m['value'] }}</div>
            <div class="text-xs text-gray-500 mt-0.5 font-medium">{{ $m['label'] }}</div>
        </div>
        @endforeach
    </section>

    {{-- ═══ TENSION SIGNALS ═══ --}}
    <section class="grid grid-cols-2 sm:grid-cols-4 gap-3 anim-fade-up delay-200">
        @foreach($tensions as $t)
        <div class="bg-white rounded-xl border border-gray-100 px-4 py-3.5" style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="flex items-center gap-1.5 mb-2">
                <span class="w-1.5 h-1.5 rounded-full {{ $t['dot'] }} shrink-0"></span>
                <span class="text-[10px] text-gray-400 font-semibold uppercase tracking-wide">{{ $t['label'] }}</span>
            </div>
            <div class="text-2xl font-bold {{ $t['color'] }}">{{ $t['value'] }}</div>
        </div>
        @endforeach
    </section>

    {{-- ═══ QUEUES + BACKLOG ═══ --}}
    <section class="grid grid-cols-1 xl:grid-cols-[1.4fr_0.6fr] gap-4">

        {{-- Queue chart --}}
        <div class="bg-white rounded-xl border border-gray-100 p-5 anim-fade-up delay-100"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-sm font-bold text-gray-900">Queues du moteur</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Suggestions éditoriales vs recommandations observées.</p>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['action_queue'] }}</div>
                    <div class="text-[10px] text-gray-400 mt-0.5">éléments actifs</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-5 items-center">
                {{-- Donut --}}
                <div class="flex items-center justify-center">
                    <div class="relative w-40 h-40">
                        <canvas id="queueChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <div class="text-xl font-bold text-gray-900">{{ $stats['action_queue'] }}</div>
                            <div class="text-[10px] text-gray-400">total</div>
                        </div>
                    </div>
                </div>

                {{-- Bars --}}
                <div class="space-y-2.5">
                    @php $total = max(1, array_sum(array_column($queueRows, 'value'))); @endphp
                    @foreach($queueRows as $i => $row)
                    <div class="anim-fade-left delay-{{ ($i+1)*100 }}">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $row['color'] }} shrink-0"></span>
                                <span class="text-xs text-gray-700 font-medium">{{ $row['label'] }}</span>
                            </div>
                            <span class="text-xs font-bold text-gray-900">{{ $row['value'] }}</span>
                        </div>
                        <div class="h-1 bg-gray-100 rounded-full overflow-hidden">
                            <div class="{{ $row['color'] }} h-full rounded-full grow-bar"
                                 style="--bar-width: {{ $total > 0 ? round($row['value']/$total*100) : 0 }}%;"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Backlog prioritaire --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden anim-fade-up delay-150"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="px-4 py-3.5 border-b border-gray-50">
                <h2 class="text-sm font-bold text-gray-900">Backlog prioritaire</h2>
                <p class="text-xs text-gray-400 mt-0.5">Recommandations + rewrites en cours.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($topActions as $item)
                <div class="px-4 py-3 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2 min-w-0">
                            <span class="w-1.5 h-1.5 rounded-full {{ $item['dot'] }} shrink-0 mt-1.5 status-dot"></span>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-gray-900 leading-snug truncate">{{ $item['title'] }}</div>
                                <div class="text-[10px] text-gray-400 mt-0.5 truncate">{{ $item['meta'] }}</div>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $item['tone'] }} shrink-0">
                            {{ $item['badge'] }}
                        </span>
                    </div>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-xs text-gray-400">Aucune recommandation pending.</div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ═══ LIFECYCLE + REWRITES + SANTÉ ═══ --}}
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        <div class="xl:col-span-3 flex items-center gap-2 mb-[-0.25rem]">
            <h2 class="text-sm font-bold text-gray-900">Queues réelles du moteur</h2>
            <span class="text-xs text-gray-400">— Suggestions, rewrites et recommandations en circulation.</span>
        </div>

        {{-- Lifecycle --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden anim-fade-up delay-100"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="px-4 py-3.5 border-b border-gray-50">
                <h3 class="text-sm font-bold text-gray-900">Lifecycle des actions</h3>
                <p class="text-xs text-gray-400 mt-0.5">Ce que le moteur garde vivant, applique ou clôture.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($actionLifecycle as $row)
                <div class="px-4 py-3.5 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs font-semibold text-gray-700 capitalize">{{ str_replace('_', ' ', $row['status']) }}</div>
                        <div class="text-lg font-bold text-gray-900">{{ $row['total'] }}</div>
                    </div>
                    <div class="mt-1 flex items-center gap-3 text-[10px] text-gray-400">
                        <span class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 shrink-0"></span>
                            {{ $row['suggestions'] }} suggestions
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                            {{ $row['recommendations'] }} recommandations
                        </span>
                    </div>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-xs text-gray-400">Aucun état d'action à afficher.</div>
                @endforelse
            </div>
        </div>

        {{-- Rewrite queue --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden anim-fade-up delay-150"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="px-4 py-3.5 border-b border-gray-50 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Queue de rewrite</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Réécritures en attente de traitement.</p>
                </div>
                @if($hasRewrites)
                <span class="inline-flex items-center rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-bold text-violet-700">
                    {{ $rewriteQueue->count() }}
                </span>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($rewriteQueue->take(4) as $suggestion)
                <div class="px-4 py-3.5 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3 mb-1.5">
                        <div class="text-xs font-semibold text-gray-900 leading-snug">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</div>
                        <span class="inline-flex items-center rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-medium text-violet-700 shrink-0">pending</span>
                    </div>
                    <div class="text-[10px] text-gray-400">{{ $suggestion->page?->site_id ?? 'n/a' }} · {{ $suggestion->source }}</div>
                    <div class="mt-1.5 text-[10px] text-gray-400 line-clamp-2 italic">
                        {{ collect(\Illuminate\Support\Arr::wrap($suggestion->suggestions_json['rationale'] ?? []))
                            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                            ->take(2)
                            ->implode(' · ') ?: 'Aucune rationale.' }}
                    </div>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-xs text-gray-400">Aucun rewrite pending.</div>
                @endforelse
            </div>
        </div>

        {{-- Santé multi-sites --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden anim-fade-up delay-200"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="px-4 py-3.5 border-b border-gray-50">
                <h3 class="text-sm font-bold text-gray-900">Santé multi-sites</h3>
                <p class="text-xs text-gray-400 mt-0.5">Vue hybride par site avec observation réelle.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($sites->take(4) as $row)
                @php
                    $score = $row['health_score'];
                    $healthColor = $score >= 70 ? 'text-emerald-600' : ($score >= 50 ? 'text-amber-600' : 'text-rose-600');
                    $healthBar   = $score >= 70 ? 'bg-emerald-500' : ($score >= 50 ? 'bg-amber-400' : 'bg-rose-400');
                @endphp
                <div class="px-4 py-3.5 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <div class="min-w-0">
                            <a href="{{ route('admin.sites.show', $row['site']->site_id) }}"
                               class="text-xs font-bold text-gray-900 hover:text-indigo-600 transition-colors truncate block">
                                {{ $row['site']->name }}
                            </a>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ $row['site']->niche }} · {{ $row['site']->locale }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-lg font-bold {{ $healthColor }}">{{ $score }}</div>
                            <div class="text-[10px] text-gray-400">score</div>
                        </div>
                    </div>
                    <div class="h-1 bg-gray-100 rounded-full overflow-hidden mb-2">
                        <div class="{{ $healthBar }} h-full rounded-full transition-all duration-700"
                             style="width: {{ min(100, $score) }}%"></div>
                    </div>
                    <div class="flex flex-wrap gap-1 text-[10px]">
                        <span class="rounded-full bg-amber-50 text-amber-700 px-2 py-0.5">{{ $row['orphan_pages'] }} orphelines</span>
                        <span class="rounded-full bg-rose-50 text-rose-600 px-2 py-0.5">{{ $row['weak_pages'] }} faibles</span>
                        <span class="rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5">{{ $row['pending_actions'] }} actions</span>
                    </div>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-xs text-gray-400">Aucun site actif.</div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ═══ RECOMMANDATIONS + CRAWLS + PAGES ═══ --}}
    <section class="grid grid-cols-1 xl:grid-cols-[1.2fr_0.8fr] gap-4">

        {{-- Recommandations observed --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden anim-fade-up delay-100"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="px-4 py-3.5 border-b border-gray-50 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Recommandations observed</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Depuis le crawl, le graph et les tensions.</p>
                </div>
                @if($hasRecommendations)
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                    {{ $priorityRecommendations->count() }}
                </span>
                @endif
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($priorityRecommendations->take(5) as $item)
                <div class="px-4 py-3.5 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2 min-w-0">
                            <div class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-gray-900 leading-snug">{{ $item->title }}</div>
                                <div class="text-[10px] text-gray-400 mt-0.5">{{ $item->site_id }} · {{ str_replace('_', ' ', (string) $item->type) }}@if($item->cluster) · {{ $item->cluster }}@endif</div>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 shrink-0">
                            P{{ $item->priority }}
                        </span>
                    </div>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-xs text-gray-400">Aucune recommandation observed pending.</div>
                @endforelse
            </div>
        </div>

        <div class="space-y-4">

            {{-- Crawls récents --}}
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden anim-fade-up delay-150"
                 style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div class="px-4 py-3.5 border-b border-gray-50">
                    <h3 class="text-sm font-bold text-gray-900">Crawls récents</h3>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($recentCrawls->take(4) as $crawl)
                    <div class="px-4 py-3 flex items-start justify-between gap-3 hover:bg-gray-50/60 transition-colors">
                        <div class="flex items-start gap-2 min-w-0">
                            @php
                                $cStatus = $crawl->status;
                                $cColor  = $cStatus === 'completed' ? 'bg-emerald-50 text-emerald-600' : ($cStatus === 'running' ? 'bg-amber-50 text-amber-600' : 'bg-gray-100 text-gray-500');
                            @endphp
                            <div class="w-7 h-7 rounded-lg {{ $cColor }} flex items-center justify-center shrink-0 mt-0.5">
                                @if($cStatus === 'completed')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @elseif($cStatus === 'running')
                                <svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                @else
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-gray-900 truncate">{{ $crawl->site_id }}</div>
                                <div class="text-[10px] text-gray-400 mt-0.5">{{ $crawl->crawled_url_count }}/{{ $crawl->discovered_url_count }} URLs · {{ ($crawl->completed_at ?? $crawl->started_at)?->diffForHumans() ?? 'en attente' }}</div>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold shrink-0
                                     {{ $crawl->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($crawl->status === 'running' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500') }}">
                            {{ $crawl->status }}
                        </span>
                    </div>
                    @empty
                    <div class="px-4 py-6 text-center text-xs text-gray-400">Aucun crawl récent.</div>
                    @endforelse
                </div>
            </div>

            {{-- Pages moteur récentes --}}
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden anim-fade-up delay-200"
                 style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
                <div class="px-4 py-3.5 border-b border-gray-50">
                    <h3 class="text-sm font-bold text-gray-900">Pages moteur récentes</h3>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($recent->take(4) as $page)
                    @php
                        $sCls = match($page->status) {
                            'published' => 'bg-emerald-50 text-emerald-700',
                            'generated' => 'bg-sky-50 text-sky-700',
                            default     => 'bg-gray-100 text-gray-500',
                        };
                    @endphp
                    <div class="px-4 py-3 flex items-start justify-between gap-3 hover:bg-gray-50/60 transition-colors">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-gray-900 leading-snug truncate">{{ $page->keyword }}</div>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ $page->site_id }} · {{ $page->updated_at?->diffForHumans() }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-sm font-bold text-gray-900">{{ $page->seo_score ?: '—' }}</div>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $sCls }}">{{ $page->status }}</span>
                        </div>
                    </div>
                    @empty
                    <div class="px-4 py-6 text-center text-xs text-gray-400">Aucune page récente.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    {{-- ═══ HOTSPOTS ═══ --}}
    @if($hasGraph || $hasQueries || $hasWeakObserved)
    <section class="space-y-4 anim-fade-up delay-200">

        @if($hasGraph)
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="px-4 py-3.5 border-b border-gray-50 flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Hotspots du graph</h3>
                    <p class="text-xs text-gray-400">Tensions et collisions sémantiques détectées.</p>
                </div>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($overlapHotspots->take(4) as $edge)
                <div class="px-4 py-3.5 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold text-gray-900">{{ $edge['source'] }} → {{ $edge['target'] }}</div>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ $edge['site_id'] }} · {{ str_replace('_', ' ', $edge['type']) }}</div>
                            <div class="text-[10px] text-gray-400 mt-1 italic">{{ str_replace('_', ' ', $edge['reason']) }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 shrink-0">{{ $edge['score'] }}%</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($hasQueries)
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="px-4 py-3.5 border-b border-gray-50 flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-cyan-50 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Query hotspots</h3>
                    <p class="text-xs text-gray-400">Queries observées poussant une création ou un refresh.</p>
                </div>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($queryHotspots->take(4) as $item)
                <div class="px-4 py-3.5 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold text-gray-900">{{ $item['query'] }}</div>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ $item['site_id'] }} · cible {{ $item['page'] }}</div>
                            <div class="mt-1.5 flex flex-wrap gap-2 text-[10px] text-gray-400">
                                <span>{{ str_replace('_', ' ', $item['action']) }}</span>
                                <span>{{ $item['impressions'] }} impressions</span>
                                <span>pos. {{ $item['position'] }}</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-bold text-cyan-700 shrink-0">{{ $item['score'] }}%</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($hasWeakObserved)
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="px-4 py-3.5 border-b border-gray-50 flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-rose-50 flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Pages observées sous tension</h3>
                    <p class="text-xs text-gray-400">Pages les plus fragiles dans la couche observée.</p>
                </div>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($weakObservedPages->take(4) as $page)
                <div class="px-4 py-3.5 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold text-gray-900">{{ $page->title ?: $page->path }}</div>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ $page->site_id }}@if($page->cluster_label) · {{ $page->cluster_label }}@endif</div>
                            <div class="mt-1.5 flex flex-wrap gap-2 text-[10px] text-gray-400">
                                <span>santé {{ (int) $page->health_score }}</span>
                                <span>autorité {{ round(((float) $page->authority_score) * 100) }}%</span>
                                <span>{{ (int) $page->latest_word_count }} mots</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold shrink-0
                                     {{ $page->indexability_state === 'indexable' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600' }}">
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
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden"
             style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <div class="px-4 py-3.5 border-b border-gray-50 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Queue feedback & signaux</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Suggestions éditoriales issues des boucles legacy.</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
                    {{ $feedbackQueue->count() }}
                </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-50">
                @foreach($feedbackQueue->take(4) as $suggestion)
                <div class="px-4 py-3.5 hover:bg-gray-50/60 transition-colors">
                    <div class="flex items-start justify-between gap-3 mb-1.5">
                        <div class="text-xs font-semibold text-gray-900 truncate">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</div>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700 shrink-0">pending</span>
                    </div>
                    <div class="text-[10px] text-gray-400">{{ $suggestion->page?->site_id ?? 'n/a' }} · {{ $suggestion->source }}</div>
                    <div class="mt-1.5 text-[10px] text-gray-400 line-clamp-2 italic">
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
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Donut Queue Chart ──────────────────────────
    const queueCtx = document.getElementById('queueChart');
    if (queueCtx) {
        new Chart(queueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Feedback loop', 'Signal queue', 'Rewrites', 'Recommandations', 'Bloqués'],
                datasets: [{
                    data: [
                        {{ $queue['feedback'] }},
                        {{ $queue['signals'] }},
                        {{ $queue['rewrites'] }},
                        {{ $queue['recommendations'] }},
                        {{ $queue['rewrite_blocked'] }},
                    ],
                    backgroundColor: ['#6366f1','#06b6d4','#8b5cf6','#10b981','#fb7185'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6,
                    borderRadius: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '74%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#f8fafc',
                        bodyColor: '#94a3b8',
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.raw}`
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 700,
                    easing: 'easeOutQuart',
                }
            }
        });
    }

    // ── Animate stat numbers ───────────────────────
    document.querySelectorAll('.stat-number').forEach(el => {
        const target = parseInt(el.textContent.replace(/\D/g, ''), 10);
        if (isNaN(target) || target === 0) return;
        let start = 0;
        const duration = 700;
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
});
</script>
@endpush
