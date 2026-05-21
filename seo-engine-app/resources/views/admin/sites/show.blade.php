@extends('admin.layout')

@section('title', $site->name)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700 transition-colors">Sites</a>
    <span class="mx-2">›</span>
    <span class="font-medium text-gray-900">{{ $site->name }}</span>
@endsection

@section('content')

{{-- Site header --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-5 mb-6 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
            <span class="text-indigo-700 font-bold text-sm">{{ strtoupper(substr($site->name, 0, 2)) }}</span>
        </div>
        <div>
            <h1 class="text-lg font-bold text-gray-900">{{ $site->name }}</h1>
            <div class="flex items-center gap-3 mt-0.5">
                <span class="text-xs text-gray-400">{{ $site->url }}</span>
                <span class="text-xs text-gray-300">•</span>
                <span class="text-xs text-gray-400">{{ $site->niche }}</span>
                <span class="text-xs text-gray-300">•</span>
                <span class="text-xs text-gray-400">{{ $site->preset ?? 'generic' }}</span>
                <span class="text-xs text-gray-300">•</span>
                <span class="text-xs text-gray-400">{{ $site->locale }}</span>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <a href="{{ route('admin.sites.crawler', $site->site_id) }}"
           class="px-4 py-2 border border-gray-200 hover:border-gray-300 text-gray-700 text-sm font-medium rounded-lg transition-colors">
            Crawler
        </a>
        <a href="{{ route('admin.sites.strategy', $site->site_id) }}"
           class="px-4 py-2 border border-gray-200 hover:border-gray-300 text-gray-700 text-sm font-medium rounded-lg transition-colors">
            Strategy
        </a>
        <a href="{{ route('admin.sites.semantic', $site->site_id) }}"
           class="px-4 py-2 border border-gray-200 hover:border-gray-300 text-gray-700 text-sm font-medium rounded-lg transition-colors">
            Semantic
        </a>
        <a href="{{ route('admin.sites.health', $site->site_id) }}"
           class="px-4 py-2 border border-gray-200 hover:border-gray-300 text-gray-700 text-sm font-medium rounded-lg transition-colors">
            Health
        </a>
        {{-- Autopilot --}}
        <form method="POST" action="{{ route('admin.pages.autopilot', $site->site_id) }}">
            @csrf
            <button type="submit" onclick="return confirm('Lancer l\'autopilot sur tous les pages ?')"
                class="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Autopilot
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
    <a href="{{ route('admin.sites.crawler', $site->site_id) }}"
       class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 hover:border-indigo-200 hover:bg-indigo-50/40 transition-colors">
        <div class="text-sm font-semibold text-gray-900">Crawler</div>
        <div class="mt-1 text-xs text-gray-500">Lancer un crawl réel et remplir la couche observée.</div>
    </a>
    <a href="{{ route('admin.sites.semantic', $site->site_id) }}"
       class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 hover:border-indigo-200 hover:bg-indigo-50/40 transition-colors">
        <div class="text-sm font-semibold text-gray-900">Semantic Graph</div>
        <div class="mt-1 text-xs text-gray-500">Voir les liens, overlaps et relations structurelles.</div>
    </a>
    <a href="{{ route('admin.sites.strategy', $site->site_id) }}"
       class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 hover:border-indigo-200 hover:bg-indigo-50/40 transition-colors">
        <div class="text-sm font-semibold text-gray-900">Strategy</div>
        <div class="mt-1 text-xs text-gray-500">Lire les opportunités et le backlog priorisé du moteur.</div>
    </a>
    <a href="{{ route('admin.sites.health', $site->site_id) }}"
       class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 hover:border-indigo-200 hover:bg-indigo-50/40 transition-colors">
        <div class="text-sm font-semibold text-gray-900">Health</div>
        <div class="mt-1 text-xs text-gray-500">Contrôler qualité, fragilité et état SEO du site.</div>
    </a>
    <a href="{{ route('admin.sites.autopilot', $site->site_id) }}"
       class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 hover:border-purple-200 hover:bg-purple-50/40 transition-colors">
        <div class="text-sm font-semibold text-gray-900">Autopilot Queue</div>
        <div class="mt-1 text-xs text-gray-500">Voir les suggestions pending avant toute action.</div>
    </a>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[1.15fr_0.85fr] gap-6 mb-6">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-gray-900">Couche observée</h2>
                <div class="mt-1 text-xs text-gray-500">La santé et le monitoring du site sont maintenant lus depuis les pages crawlées réelles.</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-semibold {{ ($observedHealth['score'] ?? 0) >= 70 ? 'text-emerald-600' : (($observedHealth['score'] ?? 0) >= 50 ? 'text-amber-600' : 'text-rose-600') }}">
                    {{ $observedHealth['score'] ?? 0 }}
                </div>
                <div class="text-xs text-gray-500">health observed</div>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-gray-100">
            @foreach([
                ['label' => 'Observées', 'value' => $observedMetrics['observed_pages']],
                ['label' => 'Healthy', 'value' => $observedMetrics['healthy_pages']],
                ['label' => 'Warning', 'value' => $observedMetrics['warning_pages']],
                ['label' => 'Critical', 'value' => $observedMetrics['critical_pages']],
                ['label' => 'Publiées', 'value' => $observedMetrics['published_pages']],
                ['label' => 'Erreurs', 'value' => $observedMetrics['error_pages']],
                ['label' => 'Générées', 'value' => $observedMetrics['generated_pages']],
                ['label' => 'Crawl récent', 'value' => $latestCrawl ? ($latestCrawl->completed_at ?? $latestCrawl->started_at)?->diffForHumans() : '—'],
            ] as $metric)
            <div class="bg-white px-5 py-4">
                <div class="text-xs uppercase tracking-wider text-gray-400">{{ $metric['label'] }}</div>
                <div class="mt-2 text-xl font-semibold text-gray-900">{{ $metric['value'] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Alertes observed</h2>
            <div class="mt-1 text-xs text-gray-500">Les pages observées que le runtime juge les plus fragiles à ce stade.</div>
        </div>

        <div class="divide-y divide-gray-50">
            @forelse($observedAlerts as $alert)
            <div class="px-6 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $alert['title'] ?: $alert['path'] }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $alert['cluster_label'] ?: 'cluster inconnu' }} · {{ $alert['path'] }}</div>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ ($alert['state'] ?? 'warning') === 'critical' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ $alert['state'] }}
                    </span>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-500">
                    <span>priorité {{ (int) ($alert['priority'] ?? 0) }}</span>
                    <span>•</span>
                    <span>santé {{ (int) ($alert['health_score'] ?? 0) }}</span>
                    <span>•</span>
                    <span>{{ (int) ($alert['latest_word_count'] ?? 0) }} mots</span>
                </div>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-sm text-gray-400">
                Aucune alerte observed active pour ce site.
            </div>
            @endforelse
        </div>
    </div>
</div>

<div class="flex items-start gap-6">

    {{-- Pages table --}}
    <div class="flex-1 bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Pages moteur</h2>
                <div class="mt-1 text-xs text-gray-500">Couche action historique : pages générées, réécritures et workflow éditorial.</div>
            </div>
            <span class="text-xs text-gray-400">{{ $pages->total() }} page(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Keyword</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Slug</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Statut</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">SEO</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Qualité</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Modifié</th>
                        <th class="text-right px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($pages as $page)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-3.5 font-medium text-gray-900 max-w-xs truncate">{{ $page->keyword }}</td>
                        <td class="px-6 py-3.5 text-gray-400 text-xs font-mono">{{ $page->slug }}</td>
                        <td class="px-6 py-3.5">
                            @php
                                $colors = ['published' => 'bg-green-100 text-green-700', 'draft' => 'bg-gray-100 text-gray-600', 'review' => 'bg-yellow-100 text-yellow-700', 'error' => 'bg-red-100 text-red-700'];
                                $c = $colors[$page->status] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c }}">{{ $page->status }}</span>
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            @if($page->seo_score)
                                @php $score = (float)$page->seo_score; $scoreColor = $score >= 70 ? 'text-green-600' : ($score >= 40 ? 'text-yellow-600' : 'text-red-500'); @endphp
                                <span class="font-semibold {{ $scoreColor }}">{{ number_format($score, 0) }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right">
                            @if($page->quality_score)
                                @php $qs = (float)$page->quality_score; $qsColor = $qs >= 70 ? 'text-green-600' : ($qs >= 40 ? 'text-yellow-600' : 'text-red-500'); @endphp
                                <span class="font-semibold {{ $qsColor }}">{{ number_format($qs, 0) }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right text-gray-400 text-xs">{{ $page->updated_at?->diffForHumans() }}</td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="{{ route('admin.pages.show', [$site->site_id, $page->id]) }}"
                               class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                            Aucune page. Générez votre première page ci-contre.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pages->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $pages->links() }}
        </div>
        @endif
    </div>

    {{-- Generate form --}}
    <div class="w-72 flex-shrink-0 space-y-4">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">Générer une page</h2>
            </div>
            <form method="POST" action="{{ route('admin.pages.generate', $site->site_id) }}" class="px-6 py-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Mot-clé cible</label>
                    <input type="text" name="keyword" placeholder="ex: diagnostic amiante Paris"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Statut initial</label>
                    <select name="status"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="draft">Brouillon</option>
                        <option value="review">En révision</option>
                        <option value="published">Publié</option>
                    </select>
                </div>
                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg px-4 py-2.5 text-sm transition-colors">
                    Générer
                </button>
            </form>
        </div>

        {{-- Site info --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-4 space-y-2 text-xs text-gray-500">
            <div class="flex justify-between"><span>Site ID</span><span class="font-mono text-gray-700">{{ $site->site_id }}</span></div>
            <div class="flex justify-between"><span>Preset</span><span>{{ $site->preset ?? 'generic' }}</span></div>
            <div class="flex justify-between"><span>Créé le</span><span>{{ $site->created_at?->format('d/m/Y') }}</span></div>
            <div class="flex justify-between"><span>Webhook</span><span>{{ $site->webhook_url ? '✓' : '—' }}</span></div>
            <div class="flex justify-between"><span>GSC mode</span><span>{{ $site->resolvedGscConnectionMode() ?? '—' }}</span></div>
            <div class="flex justify-between"><span>GSC statut</span><span>{{ str_replace('_', ' ', $site->resolvedGscConnectionStatus()) }}</span></div>
            <div class="flex justify-between"><span>GSC propriété</span><span class="text-right max-w-[160px] truncate">{{ $site->resolvedGscSiteUrl() ?: '—' }}</span></div>
            <div class="flex justify-between"><span>GSC credentials</span><span class="text-right max-w-[160px] truncate">{{ $site->resolvedGscCredentialsPath() ?: '—' }}</span></div>
        </div>
    </div>
</div>
@endsection
