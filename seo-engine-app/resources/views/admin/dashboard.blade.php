@extends('admin.layout')

@section('title', 'Dashboard')

@section('breadcrumb')
    <span class="font-medium text-gray-900">Dashboard intelligence</span>
@endsection

@section('content')
@php
    $hasObserved = ($stats['observed_pages'] ?? 0) > 0;
    $hasCrawls = $recentCrawls->isNotEmpty();
    $hasRecommendations = $priorityRecommendations->isNotEmpty();
    $hasFeedback = $feedbackQueue->isNotEmpty();
    $hasRewrites = $rewriteQueue->isNotEmpty();
    $hasGraph = $overlapHotspots->isNotEmpty();
    $hasQueries = $queryHotspots->isNotEmpty();
    $hasWeakObserved = $weakObservedPages->isNotEmpty();
    $isColdStart = ! $hasObserved && ! $hasCrawls && ! $hasRecommendations && ! $hasGraph && ! $hasQueries && ! $hasWeakObserved;

    $topActions = collect()
        ->merge($priorityRecommendations->take(3)->map(fn ($item): array => [
            'title' => $item->title,
            'meta' => trim($item->site_id.' · '.str_replace('_', ' ', (string) $item->type).($item->cluster ? ' · '.$item->cluster : '')),
            'badge' => 'P'.$item->priority,
            'tone' => 'bg-emerald-50 text-emerald-700',
        ]))
        ->merge($rewriteQueue->take(3)->map(fn ($item): array => [
            'title' => $item->page?->keyword ?? 'Rewrite pending',
            'meta' => trim(($item->page?->site_id ?? 'n/a').' · '.$item->source),
            'badge' => 'rewrite',
            'tone' => 'bg-fuchsia-50 text-fuchsia-700',
        ]))
        ->take(4);

    $queueRows = [
        ['label' => 'Feedback loop', 'value' => $queue['feedback'], 'color' => 'bg-indigo-500'],
        ['label' => 'Signal queue', 'value' => $queue['signals'], 'color' => 'bg-cyan-500'],
        ['label' => 'Rewrites pending', 'value' => $queue['rewrites'], 'color' => 'bg-fuchsia-500'],
        ['label' => 'Recommendations', 'value' => $queue['recommendations'], 'color' => 'bg-emerald-500'],
        ['label' => 'Rewrites bloqués', 'value' => $queue['rewrite_blocked'], 'color' => 'bg-rose-500'],
    ];

    $topTensions = collect([
        ['label' => 'Pages orphelines', 'value' => $intelligence['orphan_pages'], 'hint' => 'structure'],
        ['label' => 'Pages faibles', 'value' => $intelligence['weak_pages'], 'hint' => 'refresh'],
        ['label' => 'Risques cannibalisation', 'value' => $intelligence['cannibalization_risks'], 'hint' => 'overlap'],
        ['label' => 'Piliers potentiels', 'value' => $intelligence['pillar_candidates'], 'hint' => 'cluster'],
    ])->sortByDesc('value')->values();

    $compactEmptyCards = [
        ['show' => ! $hasGraph, 'title' => 'Hotspots du graph', 'text' => 'Aucune tension de graph active.'],
        ['show' => ! $hasQueries, 'title' => 'Query hotspots', 'text' => 'Aucune query prioritaire détectée.'],
        ['show' => ! $hasWeakObserved, 'title' => 'Pages observées sous tension', 'text' => 'Aucune page observée sous tension.'],
    ];
@endphp

<div class="space-y-6">
    <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-8 py-7 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-900 text-white">
            <div class="grid grid-cols-1 xl:grid-cols-[1.6fr_0.9fr] gap-6 items-start">
                <div class="max-w-3xl">
                    <div class="text-xs uppercase tracking-[0.24em] text-indigo-200/80 mb-3">SEO Brain Runtime</div>
                    <h1 class="text-2xl font-semibold tracking-tight">Le moteur pilote maintenant des signaux réels, pas juste des pages.</h1>
                    <p class="mt-3 text-sm text-slate-200 leading-6">
                        Observation, scoring, monitoring, feedback, rewrite et recommandations sont déjà stabilisés.
                        Ce cockpit donne la priorité à l’action utile et masque le bruit tant que le site n’a pas encore été crawlé.
                    </p>

                    @if($isColdStart)
                    <div class="mt-5 rounded-2xl bg-white/8 border border-white/10 p-4">
                        <div class="text-sm font-semibold text-white">État initial du moteur</div>
                        <div class="mt-2 text-sm text-slate-200">Le runtime n’a pas encore de couche observée pour ce site. La prochaine action utile est de lancer un crawl, puis de générer la stratégie.</div>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-white/10 px-3 py-1">1. Lancer un crawl</span>
                            <span class="rounded-full bg-white/10 px-3 py-1">2. Alimenter le graph</span>
                            <span class="rounded-full bg-white/10 px-3 py-1">3. Générer les opportunités</span>
                        </div>
                    </div>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-white/10 border border-white/10 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wider text-slate-300">Sites actifs</div>
                        <div class="text-2xl font-semibold mt-1">{{ $stats['total_sites'] }}</div>
                    </div>
                    <div class="rounded-xl bg-white/10 border border-white/10 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wider text-slate-300">Pages observées</div>
                        <div class="text-2xl font-semibold mt-1">{{ $stats['observed_pages'] }}</div>
                    </div>
                    <div class="rounded-xl bg-white/10 border border-white/10 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wider text-slate-300">Actions en attente</div>
                        <div class="text-2xl font-semibold mt-1">{{ $stats['action_queue'] }}</div>
                    </div>
                    <div class="rounded-xl bg-white/10 border border-white/10 px-4 py-3">
                        <div class="text-[11px] uppercase tracking-wider text-slate-300">Crawls aujourd'hui</div>
                        <div class="text-2xl font-semibold mt-1">{{ $stats['crawls_today'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 xl:grid-cols-4 divide-y xl:divide-y-0 xl:divide-x divide-gray-100">
            @foreach([
                ['label' => 'Pages orphelines', 'value' => $intelligence['orphan_pages'], 'tone' => 'text-amber-600'],
                ['label' => 'Pages faibles', 'value' => $intelligence['weak_pages'], 'tone' => 'text-rose-600'],
                ['label' => 'Risques cannibalisation', 'value' => $intelligence['cannibalization_risks'], 'tone' => 'text-orange-600'],
                ['label' => 'Piliers potentiels', 'value' => $intelligence['pillar_candidates'], 'tone' => 'text-emerald-600'],
            ] as $item)
            <div class="px-6 py-4">
                <div class="text-xs uppercase tracking-wider text-gray-400">{{ $item['label'] }}</div>
                <div class="mt-2 text-3xl font-semibold {{ $item['tone'] }}">{{ $item['value'] }}</div>
            </div>
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-[1.2fr_0.8fr] gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Files d’action du moteur</h2>
                    <p class="text-xs text-gray-500 mt-1">Ce que le cerveau a réellement décidé de garder en attente.</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-semibold text-gray-900">{{ $stats['action_queue'] }}</div>
                    <div class="text-xs text-gray-500">actions actives</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-[260px_1fr] gap-6 items-center">
                <div class="relative h-60">
                    <canvas id="queueChart"></canvas>
                </div>
                <div class="space-y-3">
                    @foreach($queueRows as $row)
                    <div class="flex items-center justify-between rounded-xl border border-gray-100 px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full {{ $row['color'] }}"></span>
                            <span class="text-sm text-gray-700">{{ $row['label'] }}</span>
                        </div>
                        <span class="text-sm font-semibold text-gray-900">{{ $row['value'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Backlog prioritaire</h2>
                        <p class="text-xs text-gray-500 mt-1">La prochaine poignée d’actions utiles à traiter.</p>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse($topActions as $item)
                    <div class="rounded-xl border border-gray-100 px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $item['title'] }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $item['meta'] }}</div>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $item['tone'] }}">
                                {{ $item['badge'] }}
                            </span>
                        </div>
                    </div>
                    @empty
                    <div class="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-400">
                        Aucune recommandation pending.
                    </div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-sm font-semibold text-gray-900">Lifecycle des actions</h2>
                <p class="text-xs text-gray-500 mt-1">Ce que le moteur garde vivant, applique, rejette ou clôture réellement.</p>

                <div class="mt-4 space-y-3">
                    @forelse($actionLifecycle as $row)
                    <div class="rounded-xl border border-gray-100 px-4 py-3">
                        <div class="flex items-center justify-between gap-4">
                            <div class="text-sm font-medium text-gray-900">{{ str_replace('_', ' ', $row['status']) }}</div>
                            <div class="text-lg font-semibold text-gray-900">{{ $row['total'] }}</div>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">{{ $row['suggestions'] }} suggestions • {{ $row['recommendations'] }} recommandations</div>
                    </div>
                    @empty
                    <div class="rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-400">
                        Aucun état d’action à afficher.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-[1.1fr_0.9fr] gap-6">
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Queue de rewrite</h2>
                        <p class="text-xs text-gray-500 mt-1">Réécritures déjà jugées utiles et encore en attente.</p>
                    </div>
                    @if($hasRewrites)
                    <span class="inline-flex items-center rounded-full bg-fuchsia-50 px-3 py-1 text-xs font-medium text-fuchsia-700">
                        {{ $rewriteQueue->count() }} visibles
                    </span>
                    @endif
                </div>

                <div class="divide-y divide-gray-50">
                    @forelse($rewriteQueue->take(4) as $suggestion)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ $suggestion->page?->site_id ?? 'n/a' }} · {{ $suggestion->source }}</div>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-fuchsia-50 px-2.5 py-1 text-xs font-medium text-fuchsia-700">pending</span>
                        </div>
                        <div class="mt-3 text-xs text-gray-500 line-clamp-2">
                            {{ collect(\Illuminate\Support\Arr::wrap($suggestion->suggestions_json['rationale'] ?? []))
                                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                                ->take(2)
                                ->implode(' · ') ?: 'Aucune rationale.' }}
                        </div>
                    </div>
                    @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-400">Aucun rewrite pending.</div>
                    @endforelse
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($compactEmptyCards as $card)
                    @if($card['show'])
                    <div class="bg-white rounded-2xl border border-dashed border-gray-200 px-5 py-5">
                        <div class="text-sm font-semibold text-gray-900">{{ $card['title'] }}</div>
                        <div class="mt-2 text-sm text-gray-400">{{ $card['text'] }}</div>
                    </div>
                    @endif
                @endforeach

                @if($hasGraph)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm md:col-span-3 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Hotspots du graph</h2>
                        <p class="text-xs text-gray-500 mt-1">Là où le graphe sémantique remonte le plus de tension ou de collision.</p>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($overlapHotspots->take(4) as $edge)
                        <div class="px-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $edge['source'] }} → {{ $edge['target'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $edge['site_id'] }} · {{ str_replace('_', ' ', $edge['type']) }}</div>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">{{ $edge['score'] }}%</span>
                            </div>
                            <div class="mt-2 text-xs text-gray-500">{{ str_replace('_', ' ', $edge['reason']) }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($hasQueries)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm md:col-span-3 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Query hotspots</h2>
                        <p class="text-xs text-gray-500 mt-1">Les queries observées qui poussent une création, un refresh ou une revue d’intention.</p>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($queryHotspots->take(4) as $item)
                        <div class="px-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $item['query'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $item['site_id'] }} · cible {{ $item['page'] }}</div>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700">{{ $item['score'] }}%</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                <span>{{ str_replace('_', ' ', $item['action']) }}</span>
                                <span>•</span>
                                <span>{{ $item['impressions'] }} impressions</span>
                                <span>•</span>
                                <span>position {{ $item['position'] }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($hasWeakObserved)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm md:col-span-3 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Pages observées sous tension</h2>
                        <p class="text-xs text-gray-500 mt-1">Les pages que le moteur juge les plus fragiles dans la couche observée.</p>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($weakObservedPages->take(4) as $page)
                        <div class="px-6 py-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $page->title ?: $page->path }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $page->site_id }} @if($page->cluster_label) · {{ $page->cluster_label }} @endif</div>
                                </div>
                                <span class="inline-flex items-center rounded-full {{ $page->indexability_state === 'indexable' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }} px-2.5 py-1 text-xs font-medium">
                                    {{ $page->indexability_state }}
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                <span>autorité {{ round(((float) $page->authority_score) * 100) }}%</span>
                                <span>•</span>
                                <span>orphan {{ round(((float) $page->orphan_score) * 100) }}%</span>
                                <span>•</span>
                                <span>{{ (int) $page->latest_word_count }} mots</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Santé multi-sites</h2>
                    <p class="text-xs text-gray-500 mt-1">Vue d’ensemble par site : observation, faiblesse, tension et backlog.</p>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($sites->take(4) as $row)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <a href="{{ route('admin.sites.show', $row['site']->site_id) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">{{ $row['site']->name }}</a>
                                <div class="mt-1 text-xs text-gray-500">{{ $row['site']->niche }} · {{ $row['site']->locale }} · {{ $row['site']->resolvedPreset() }}</div>
                            </div>
                            @php
                                $healthTone = $row['health_score'] >= 70 ? 'text-emerald-600' : ($row['health_score'] >= 50 ? 'text-amber-600' : 'text-rose-600');
                            @endphp
                            <div class="text-right">
                                <div class="text-2xl font-semibold {{ $healthTone }}">{{ $row['health_score'] }}</div>
                                <div class="text-[11px] text-gray-500">score runtime</div>
                            </div>
                        </div>
                        <div class="mt-3 text-sm text-gray-900">{{ $row['observed_pages'] }} observées · {{ $row['generated_pages'] }} générées</div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">{{ $row['orphan_pages'] }} orphelines</span>
                            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-rose-700">{{ $row['weak_pages'] }} faibles</span>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">{{ $row['pending_actions'] }} actions</span>
                        </div>
                    </div>
                    @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-400">Aucun site actif.</div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Crawls récents</h2>
                    <p class="text-xs text-gray-500 mt-1">Derniers passages de la couche observée.</p>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($recentCrawls->take(4) as $crawl)
                    <div class="px-6 py-4 flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $crawl->site_id }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $crawl->crawled_url_count }}/{{ $crawl->discovered_url_count }} URLs · {{ ($crawl->completed_at ?? $crawl->started_at)?->diffForHumans() ?? 'en attente' }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $crawl->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($crawl->status === 'running' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-700') }}">
                            {{ $crawl->status }}
                        </span>
                    </div>
                    @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-400">Aucun crawl récent.</div>
                    @endforelse
                </div>
            </div>

            @if($hasFeedback)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Queue feedback & signaux</h2>
                    <p class="text-xs text-gray-500 mt-1">Boucle faible bruit : uniquement ce que le moteur garde utile.</p>
                </div>
                <div class="divide-y divide-gray-50">
                    @foreach($feedbackQueue->take(4) as $suggestion)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</div>
                                <div class="text-xs text-gray-500 mt-1">{{ $suggestion->page?->site_id ?? 'n/a' }} · {{ $suggestion->source }}</div>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">pending</span>
                        </div>
                        <div class="mt-3 text-xs text-gray-500 line-clamp-2">
                            {{ collect(\Illuminate\Support\Arr::wrap($suggestion->suggestions_json['rationale'] ?? []))
                                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                                ->take(2)
                                ->implode(' · ') ?: 'Aucune rationale.' }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Pages moteur récentes</h2>
                    <p class="text-xs text-gray-500 mt-1">Activité récente de la couche action/génération.</p>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($recent->take(4) as $page)
                    <div class="px-6 py-4 flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $page->keyword }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $page->site_id }} · {{ $page->updated_at?->diffForHumans() }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold text-gray-900">{{ $page->seo_score ?: '—' }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $page->status }}</div>
                        </div>
                    </div>
                    @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-400">Aucune page récente.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    const queueCtx = document.getElementById('queueChart');

    if (queueCtx) {
        new Chart(queueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Feedback loop', 'Signal queue', 'Rewrites', 'Recommendations', 'Rewrite blocked'],
                datasets: [{
                    data: [
                        {{ $queue['feedback'] }},
                        {{ $queue['signals'] }},
                        {{ $queue['rewrites'] }},
                        {{ $queue['recommendations'] }},
                        {{ $queue['rewrite_blocked'] }},
                    ],
                    backgroundColor: ['#6366f1', '#06b6d4', '#d946ef', '#10b981', '#f43f5e'],
                    borderWidth: 0,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: '#111827',
                        titleColor: '#fff',
                        bodyColor: '#e5e7eb',
                        padding: 12,
                    }
                }
            }
        });
    }
</script>
@endpush
