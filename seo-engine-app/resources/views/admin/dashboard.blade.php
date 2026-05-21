@extends('admin.layout')

@section('title', 'Dashboard')

@section('breadcrumb')
    <span class="font-medium text-gray-900">Dashboard intelligence</span>
@endsection

@section('content')
<div class="space-y-8">
    <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-8 py-7 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-900 text-white">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="text-xs uppercase tracking-[0.24em] text-indigo-200/80 mb-3">SEO Brain Runtime</div>
                    <h1 class="text-2xl font-semibold tracking-tight">Le moteur pilote maintenant des signaux réels, pas juste des pages.</h1>
                    <p class="mt-3 text-sm text-slate-200 leading-6">
                        Observation, scoring, monitoring, feedback, rewrite et recommandations sont déjà stabilisés.
                        Ce cockpit montre les tensions réelles du graph, les files d'action actives et la santé multi-sites.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3 min-w-[320px]">
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
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-0 divide-y lg:divide-y-0 lg:divide-x divide-gray-100">
            @foreach([
                ['label' => 'Pages orphelines', 'value' => $intelligence['orphan_pages'], 'tone' => 'text-amber-600', 'hint' => 'Tension structurelle détectée'],
                ['label' => 'Pages faibles', 'value' => $intelligence['weak_pages'], 'tone' => 'text-rose-600', 'hint' => 'Cibles monitoring + refresh'],
                ['label' => 'Risques cannibalisation', 'value' => $intelligence['cannibalization_risks'], 'tone' => 'text-orange-600', 'hint' => 'Conflits d’intention actifs'],
                ['label' => 'Piliers potentiels', 'value' => $intelligence['pillar_candidates'], 'tone' => 'text-emerald-600', 'hint' => 'Ancrages de cluster identifiés'],
            ] as $item)
            <div class="px-6 py-5">
                <div class="text-xs uppercase tracking-wider text-gray-400">{{ $item['label'] }}</div>
                <div class="mt-2 text-3xl font-semibold {{ $item['tone'] }}">{{ $item['value'] }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ $item['hint'] }}</div>
            </div>
            @endforeach
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Files d’action du moteur</h2>
                    <p class="text-xs text-gray-500 mt-1">Ce que le cerveau a réellement décidé de garder en attente.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                <div class="relative h-72">
                    <canvas id="queueChart"></canvas>
                </div>
                <div class="space-y-3">
                    @foreach([
                        ['label' => 'Feedback loop', 'value' => $queue['feedback'], 'color' => 'bg-indigo-500'],
                        ['label' => 'Signal queue', 'value' => $queue['signals'], 'color' => 'bg-cyan-500'],
                        ['label' => 'Rewrites pending', 'value' => $queue['rewrites'], 'color' => 'bg-fuchsia-500'],
                        ['label' => 'Recommendations', 'value' => $queue['recommendations'], 'color' => 'bg-emerald-500'],
                        ['label' => 'Rewrites bloqués', 'value' => $queue['rewrite_blocked'], 'color' => 'bg-rose-500'],
                    ] as $row)
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

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Backlog prioritaire</h2>
                    <p class="text-xs text-gray-500 mt-1">Les actions les plus urgentes que le moteur garde ouvertes.</p>
                </div>
            </div>
            <div class="space-y-3">
                @forelse($priorityRecommendations as $item)
                <a href="{{ route('admin.sites.strategy', $item->site_id) }}" class="block rounded-xl border border-gray-100 px-4 py-3 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $item->title }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $item->site_id }} · {{ $item->type }} @if($item->cluster) · {{ $item->cluster }} @endif</div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">
                            P{{ $item->priority }}
                        </span>
                    </div>
                    <div class="mt-3 flex items-center gap-2 text-[11px] uppercase tracking-wide text-gray-400">
                        <span>{{ $item->estimated_impact }}</span>
                        <span>•</span>
                        <span>{{ $item->difficulty }}</span>
                        @if($item->generated_at)
                            <span>•</span>
                            <span>{{ $item->generated_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </a>
                @empty
                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-400">
                    Aucune recommandation pending.
                </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Lifecycle des actions</h2>
                <p class="text-xs text-gray-500 mt-1">Ce que le moteur garde vivant, applique, rejette ou clôture réellement.</p>
            </div>
            <div class="p-6 space-y-3">
                @forelse($actionLifecycle as $row)
                <div class="rounded-xl border border-gray-100 px-4 py-3">
                    <div class="flex items-center justify-between gap-4">
                        <div class="text-sm font-medium text-gray-900">{{ str_replace('_', ' ', $row['status']) }}</div>
                        <div class="text-sm font-semibold text-gray-900">{{ $row['total'] }}</div>
                    </div>
                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                        <span>{{ $row['suggestions'] }} suggestions</span>
                        <span>•</span>
                        <span>{{ $row['recommendations'] }} recommandations</span>
                    </div>
                </div>
                @empty
                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-400">
                    Aucun état d’action à afficher.
                </div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Opportunités par type</h2>
                <p class="text-xs text-gray-500 mt-1">La forme actuelle du backlog intelligent, groupée par décision moteur.</p>
            </div>
            <div class="p-6 space-y-4">
                @php $maxOpportunity = max(1, (int) ($opportunityMix->max('total') ?? 1)); @endphp
                @forelse($opportunityMix as $item)
                <div>
                    <div class="flex items-center justify-between gap-4 text-sm">
                        <span class="font-medium text-gray-900">{{ $item['type'] }}</span>
                        <span class="text-gray-500">{{ $item['total'] }}</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-cyan-500" style="width: {{ max(12, (int) round(($item['total'] / $maxOpportunity) * 100)) }}%"></div>
                    </div>
                </div>
                @empty
                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-400">
                    Aucune opportunité pending.
                </div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Hotspots du graph</h2>
                <p class="text-xs text-gray-500 mt-1">Là où le graphe sémantique remonte le plus de tension ou de collision.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($overlapHotspots as $edge)
                <div class="px-6 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $edge['source'] }} → {{ $edge['target'] }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $edge['site_id'] }} · {{ str_replace('_', ' ', $edge['type']) }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                            {{ $edge['score'] }}%
                        </span>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">{{ str_replace('_', ' ', $edge['reason']) }}</div>
                </div>
                @empty
                <div class="px-6 py-8 text-center text-sm text-gray-400">Aucune tension de graph active.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Queue de rewrite</h2>
                <p class="text-xs text-gray-500 mt-1">Réécritures déjà jugées utiles et encore en attente.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($rewriteQueue as $suggestion)
                <div class="px-6 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $suggestion->page?->site_id ?? 'n/a' }} · {{ $suggestion->source }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-fuchsia-50 px-2.5 py-1 text-xs font-medium text-fuchsia-700">pending</span>
                    </div>
                    <div class="mt-3 text-xs text-gray-500 line-clamp-2">
                        {{ implode(' · ', array_slice($suggestion->suggestions_json['rationale'] ?? [], 0, 3)) ?: 'Aucune rationale.' }}
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center text-sm text-gray-400">Aucun rewrite pending.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Queue feedback & signaux</h2>
                <p class="text-xs text-gray-500 mt-1">Boucle faible bruit : uniquement ce que le moteur garde utile.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($feedbackQueue as $suggestion)
                <div class="px-6 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $suggestion->page?->site_id ?? 'n/a' }} · {{ $suggestion->source }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">pending</span>
                    </div>
                    <div class="mt-3 text-xs text-gray-500 line-clamp-2">
                        {{ implode(' · ', array_slice($suggestion->suggestions_json['rationale'] ?? [], 0, 3)) ?: 'Aucune rationale.' }}
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center text-sm text-gray-400">Aucun feedback pending.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Query hotspots</h2>
                <p class="text-xs text-gray-500 mt-1">Les queries observées qui poussent une création, un refresh ou une revue d’intention.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($queryHotspots as $item)
                <div class="px-6 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $item['query'] }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $item['site_id'] }} · cible {{ $item['page'] }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700">
                            {{ $item['score'] }}%
                        </span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                        <span>{{ str_replace('_', ' ', $item['action']) }}</span>
                        <span>•</span>
                        <span>{{ $item['impressions'] }} impressions</span>
                        <span>•</span>
                        <span>position {{ $item['position'] }}</span>
                    </div>
                </div>
                @empty
                <div class="px-6 py-8 text-center text-sm text-gray-400">Aucune query prioritaire détectée.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Pages observées sous tension</h2>
                <p class="text-xs text-gray-500 mt-1">Les pages que le moteur juge les plus fragiles dans la couche observée.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($weakObservedPages as $page)
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
                @empty
                <div class="px-6 py-8 text-center text-sm text-gray-400">Aucune page observée sous tension.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Santé multi-sites</h2>
                <p class="text-xs text-gray-500 mt-1">Vue d’ensemble par site : observation, faiblesse, tension et backlog.</p>
            </div>
            <a href="{{ route('admin.sites.index') }}" class="text-xs text-indigo-600 hover:text-indigo-700">Voir les sites →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Site</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Observation</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Tensions</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Crawl</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Santé</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($sites as $row)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 align-top">
                            <a href="{{ route('admin.sites.show', $row['site']->site_id) }}" class="font-medium text-gray-900 hover:text-indigo-600">
                                {{ $row['site']->name }}
                            </a>
                            <div class="mt-1 text-xs text-gray-500">{{ $row['site']->niche }} · {{ $row['site']->locale }} · {{ $row['site']->resolvedPreset() }}</div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="text-sm text-gray-900">{{ $row['observed_pages'] }} observées · {{ $row['generated_pages'] }} générées</div>
                            <div class="mt-2 h-2 rounded-full bg-gray-100 overflow-hidden max-w-40">
                                <div class="h-full bg-indigo-500 rounded-full" style="width: {{ min(100, $row['avg_authority']) }}%"></div>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">Autorité moyenne {{ $row['avg_authority'] }}%</div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">{{ $row['orphan_pages'] }} orphelines</span>
                                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700">{{ $row['weak_pages'] }} faibles</span>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">{{ $row['pending_actions'] }} actions</span>
                            </div>
                            <div class="mt-2 text-xs text-gray-500">Orphan score moyen {{ $row['avg_orphan'] }}%</div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            @if($row['latest_crawl'])
                                <div class="text-sm text-gray-900">{{ $row['latest_crawl']->status }}</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $row['latest_crawl']->crawled_url_count }}/{{ $row['latest_crawl']->discovered_url_count }} URLs
                                </div>
                                <div class="mt-1 text-xs text-gray-400">
                                    {{ ($row['latest_crawl']->completed_at ?? $row['latest_crawl']->created_at)?->diffForHumans() }}
                                </div>
                            @else
                                <div class="text-sm text-gray-400">Aucun crawl</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 align-top text-right">
                            @php
                                $healthTone = $row['health_score'] >= 70
                                    ? 'text-emerald-600'
                                    : ($row['health_score'] >= 50 ? 'text-amber-600' : 'text-rose-600');
                            @endphp
                            <div class="text-2xl font-semibold {{ $healthTone }}">{{ $row['health_score'] }}</div>
                            <div class="mt-1 text-xs text-gray-500">score runtime</div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-400">Aucun site actif.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Crawls récents</h2>
                <p class="text-xs text-gray-500 mt-1">Derniers passages de la couche observée.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($recentCrawls as $crawl)
                <div class="px-6 py-4 flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $crawl->site_id }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $crawl->crawled_url_count }}/{{ $crawl->discovered_url_count }} URLs ·
                            {{ ($crawl->completed_at ?? $crawl->started_at)?->diffForHumans() ?? 'en attente' }}
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                        {{ $crawl->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($crawl->status === 'running' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-700') }}">
                        {{ $crawl->status }}
                    </span>
                </div>
                @empty
                <div class="px-6 py-8 text-center text-sm text-gray-400">Aucun crawl récent.</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Pages moteur récentes</h2>
                <p class="text-xs text-gray-500 mt-1">Activité récente de la couche action/génération.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($recent as $page)
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
