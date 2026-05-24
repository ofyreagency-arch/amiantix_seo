@extends('admin.layout')

@section('title', $site->name)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700 transition-colors">Sites</a>
    <span class="mx-2 text-gray-300">›</span>
    <span class="font-semibold text-gray-900">{{ $site->name }}</span>
@endsection

@section('content')

@php
$scoreColor = ($observedHealth['score'] ?? 0) >= 70 ? 'text-emerald-600' : (($observedHealth['score'] ?? 0) >= 50 ? 'text-amber-600' : 'text-rose-600');
$scoreBg    = ($observedHealth['score'] ?? 0) >= 70 ? 'bg-emerald-50 border-emerald-100' : (($observedHealth['score'] ?? 0) >= 50 ? 'bg-amber-50 border-amber-100' : 'bg-rose-50 border-rose-100');
$inputCls   = 'w-full border border-gray-200 bg-gray-50 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 focus:bg-white transition-all';
$labelCls   = 'block text-xs font-semibold text-gray-500 mb-1.5';
@endphp

{{-- ═══ SITE HEADER ═══ --}}
<div class="rounded-2xl border border-gray-100 overflow-hidden mb-6 anim-fade-up"
     style="background:linear-gradient(135deg,#f8f9ff 0%,#ffffff 100%);box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="px-6 py-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 font-bold text-sm shadow-lg"
                 style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;">
                {{ strtoupper(substr($site->name, 0, 2)) }}
            </div>
            <div>
                <h1 class="text-xl font-black text-gray-900">{{ $site->name }}</h1>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                    <span class="text-xs text-gray-400 font-mono">{{ $site->url }}</span>
                    <span class="text-gray-200">·</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700">{{ $site->niche }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-purple-50 text-purple-700">{{ $site->preset ?? 'generic' }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-gray-100 text-gray-600">{{ $site->locale }}</span>
                    @if($site->is_active)
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 status-dot"></span>
                        Actif
                    </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.sites.crawler', $site->site_id) }}"
               class="text-xs font-semibold text-gray-600 hover:text-indigo-600 px-3 py-1.5 border border-gray-200 bg-white rounded-lg hover:border-indigo-200 hover:bg-indigo-50 transition-all">
                Crawler
            </a>
            <a href="{{ route('admin.sites.strategy', $site->site_id) }}"
               class="text-xs font-semibold text-gray-600 hover:text-indigo-600 px-3 py-1.5 border border-gray-200 bg-white rounded-lg hover:border-indigo-200 hover:bg-indigo-50 transition-all">
                Stratégie
            </a>
            <a href="{{ route('admin.sites.semantic', $site->site_id) }}"
               class="text-xs font-semibold text-gray-600 hover:text-indigo-600 px-3 py-1.5 border border-gray-200 bg-white rounded-lg hover:border-indigo-200 hover:bg-indigo-50 transition-all">
                Sémantique
            </a>
            <a href="{{ route('admin.sites.health', $site->site_id) }}"
               class="text-xs font-semibold text-gray-600 hover:text-indigo-600 px-3 py-1.5 border border-gray-200 bg-white rounded-lg hover:border-indigo-200 hover:bg-indigo-50 transition-all">
                Santé
            </a>
            <form method="POST" action="{{ route('admin.pages.autopilot', $site->site_id) }}" class="inline">
                @csrf
                <button type="submit"
                        onclick="return confirm('Lancer l\'autopilot sur toutes les pages ?')"
                        class="text-xs font-bold text-white px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
                        style="background:linear-gradient(135deg,#7c3aed,#6d28d9);box-shadow:0 2px 8px rgba(124,58,237,0.3);">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Autopilot
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ═══ MODULE CARDS ═══ --}}
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-6 anim-fade-up delay-50">
    @foreach([
        ['route' => 'admin.sites.crawler',  'label' => 'Crawler',       'desc' => 'Crawl réel et couche observée', 'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'color' => 'indigo'],
        ['route' => 'admin.sites.semantic', 'label' => 'Sémantique',     'desc' => 'Liens, overlaps et structures', 'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1', 'color' => 'blue'],
        ['route' => 'admin.sites.strategy', 'label' => 'Stratégie',      'desc' => 'Opportunités et backlog', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color' => 'violet'],
        ['route' => 'admin.sites.health',   'label' => 'Santé',          'desc' => 'Qualité et état SEO', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'color' => 'emerald'],
        ['route' => 'admin.sites.autopilot','label' => 'Autopilot',      'desc' => 'Suggestions pending', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'color' => 'purple'],
    ] as $module)
    @php
        $clrMap = [
            'indigo'  => ['bg' => 'bg-indigo-50', 'icon' => 'text-indigo-600', 'border' => 'hover:border-indigo-200', 'label' => 'text-indigo-700'],
            'blue'    => ['bg' => 'bg-blue-50',   'icon' => 'text-blue-600',   'border' => 'hover:border-blue-200',   'label' => 'text-blue-700'],
            'violet'  => ['bg' => 'bg-violet-50', 'icon' => 'text-violet-600', 'border' => 'hover:border-violet-200', 'label' => 'text-violet-700'],
            'emerald' => ['bg' => 'bg-emerald-50','icon' => 'text-emerald-600','border' => 'hover:border-emerald-200','label' => 'text-emerald-700'],
            'purple'  => ['bg' => 'bg-purple-50', 'icon' => 'text-purple-600', 'border' => 'hover:border-purple-200', 'label' => 'text-purple-700'],
        ];
        $clr = $clrMap[$module['color']];
    @endphp
    <a href="{{ route($module['route'], $site->site_id) }}"
       class="bg-white rounded-2xl border border-gray-100 px-5 py-4 {{ $clr['border'] }} transition-all hover:-translate-y-0.5 group"
       style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
        <div class="w-8 h-8 {{ $clr['bg'] }} rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
            <svg class="w-4 h-4 {{ $clr['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $module['icon'] }}"/>
            </svg>
        </div>
        <div class="text-sm font-bold text-gray-900">{{ $module['label'] }}</div>
        <div class="mt-0.5 text-xs text-gray-400">{{ $module['desc'] }}</div>
    </a>
    @endforeach
</div>

{{-- ═══ OBSERVED LAYER + ALERTS ═══ --}}
<div class="grid grid-cols-1 xl:grid-cols-[1.15fr_0.85fr] gap-6 mb-6 anim-fade-up delay-100">

    {{-- Observed metrics --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden" style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <h2 class="font-bold text-gray-900">Couche observée</h2>
                <p class="text-xs text-gray-400 mt-0.5">Santé et monitoring lus depuis les pages crawlées réelles.</p>
            </div>
            <div class="text-right shrink-0">
                <div class="text-2xl font-black {{ $scoreColor }}">{{ $observedHealth['score'] ?? 0 }}</div>
                <div class="text-xs text-gray-400">health score</div>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-px" style="background:#f3f4f6;">
            @foreach([
                ['label' => 'Observées',    'value' => $observedMetrics['observed_pages'], 'cls' => 'text-gray-900'],
                ['label' => 'Healthy',      'value' => $observedMetrics['healthy_pages'],  'cls' => 'text-emerald-600'],
                ['label' => 'Warning',      'value' => $observedMetrics['warning_pages'],  'cls' => 'text-amber-600'],
                ['label' => 'Critical',     'value' => $observedMetrics['critical_pages'], 'cls' => 'text-rose-600'],
                ['label' => 'Publiées',     'value' => $observedMetrics['published_pages'],'cls' => 'text-indigo-600'],
                ['label' => 'Erreurs',      'value' => $observedMetrics['error_pages'],    'cls' => 'text-red-500'],
                ['label' => 'Générées',     'value' => $observedMetrics['generated_pages'],'cls' => 'text-purple-600'],
                ['label' => 'Crawl récent', 'value' => $latestCrawl ? ($latestCrawl->completed_at ?? $latestCrawl->started_at)?->diffForHumans() : '—', 'cls' => 'text-gray-700'],
            ] as $metric)
            <div class="bg-white px-5 py-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">{{ $metric['label'] }}</div>
                <div class="text-xl font-black {{ $metric['cls'] }}">{{ $metric['value'] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Alerts --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden" style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-900">Alertes observed</h2>
            <p class="text-xs text-gray-400 mt-0.5">Pages les plus fragiles selon le runtime.</p>
        </div>

        <div class="divide-y divide-gray-50 max-h-[360px] overflow-y-auto">
            @forelse($observedAlerts as $alert)
            @php
                $isCritical = ($alert['state'] ?? 'warning') === 'critical';
                $alertCls   = $isCritical ? 'bg-rose-50 text-rose-700 border-rose-100' : 'bg-amber-50 text-amber-700 border-amber-100';
            @endphp
            <div class="px-5 py-4 hover:bg-gray-50/60 transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 truncate">{{ $alert['title'] ?: $alert['path'] }}</div>
                        <div class="mt-0.5 text-xs text-gray-400 truncate">{{ $alert['cluster_label'] ?: 'cluster inconnu' }} · {{ $alert['path'] }}</div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold border {{ $alertCls }} shrink-0">
                        {{ $alert['state'] }}
                    </span>
                </div>
                <div class="mt-2 flex gap-3 text-xs text-gray-400">
                    <span>P{{ (int)($alert['priority'] ?? 0) }}</span>
                    <span>·</span>
                    <span>santé {{ (int)($alert['health_score'] ?? 0) }}</span>
                    <span>·</span>
                    <span>{{ (int)($alert['latest_word_count'] ?? 0) }} mots</span>
                </div>
            </div>
            @empty
            <div class="px-6 py-12 text-center">
                <div class="w-10 h-10 bg-emerald-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="text-sm font-semibold text-gray-400">Aucune alerte active</div>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ═══ GSC OPPORTUNITIES ═══ --}}
<div class="grid grid-cols-1 xl:grid-cols-[1.2fr_0.8fr] gap-6 mb-6 anim-fade-up delay-125">
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden" style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <h2 class="font-bold text-gray-900">Google Search Console</h2>
                <p class="text-xs text-gray-400 mt-0.5">Ce que Google remonte vraiment et ce qui mérite une action.</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $gscOpportunitySummary['connected'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
                {{ $gscOpportunitySummary['connected'] ? 'Connecté' : 'Non connecté' }}
            </span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-px" style="background:#f3f4f6;">
            @foreach([
                ['label' => 'CTR à relancer', 'value' => $gscOpportunitySummary['summary']['low_ctr'], 'cls' => 'text-amber-600'],
                ['label' => 'Proches du top 10', 'value' => $gscOpportunitySummary['summary']['near_top_10'], 'cls' => 'text-blue-600'],
                ['label' => 'Requêtes émergentes', 'value' => $gscOpportunitySummary['summary']['emerging_queries'], 'cls' => 'text-violet-600'],
                ['label' => 'Baisses durables', 'value' => $gscOpportunitySummary['summary']['sustained_drop'], 'cls' => 'text-rose-600'],
            ] as $metric)
            <div class="bg-white px-5 py-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">{{ $metric['label'] }}</div>
                <div class="text-xl font-black {{ $metric['cls'] }}">{{ $metric['value'] }}</div>
            </div>
            @endforeach
        </div>

        <div class="divide-y divide-gray-50">
            @forelse($gscOpportunitySummary['items'] as $item)
            <div class="px-6 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            @php
                                $badgeCls = match ($item['type']) {
                                    'low_ctr' => 'bg-amber-50 text-amber-700 border-amber-100',
                                    'near_top_10' => 'bg-blue-50 text-blue-700 border-blue-100',
                                    'emerging_query' => 'bg-violet-50 text-violet-700 border-violet-100',
                                    'sustained_drop' => 'bg-rose-50 text-rose-700 border-rose-100',
                                    default => 'bg-gray-100 text-gray-600 border-gray-200',
                                };
                                $badgeLabel = match ($item['type']) {
                                    'low_ctr' => 'CTR à relancer',
                                    'near_top_10' => 'Proche du top 10',
                                    'emerging_query' => 'Requête émergente',
                                    'sustained_drop' => 'Baisse récente',
                                    default => 'À revoir',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold border {{ $badgeCls }}">{{ $badgeLabel }}</span>
                            @if(!empty($item['query']))
                                <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">{{ $item['query'] }}</span>
                            @else
                                <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">/{{ $item['slug'] }}</span>
                            @endif
                        </div>
                        <div class="text-sm font-semibold text-gray-900">{{ $item['label'] }}</div>
                        <div class="mt-1 text-sm text-gray-500">{{ $item['reason'] }}</div>
                        <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-400">
                            @if(isset($item['metrics']['impressions']))
                                <span>{{ $item['metrics']['impressions'] }} impressions</span>
                            @endif
                            @if(isset($item['metrics']['ctr']))
                                <span>CTR {{ number_format((float) $item['metrics']['ctr'], 2, ',', ' ') }}%</span>
                            @endif
                            @if(isset($item['metrics']['position']))
                                <span>Pos {{ number_format((float) $item['metrics']['position'], 1, ',', ' ') }}</span>
                            @endif
                            @if(isset($item['metrics']['previous_impressions']))
                                <span>Avant {{ $item['metrics']['previous_impressions'] }}</span>
                            @endif
                        </div>
                    </div>
                    @if(!empty($item['page_id']))
                    <a href="{{ route('admin.pages.show', [$site->site_id, $item['page_id']]) }}"
                       class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-100 shrink-0">
                        Ouvrir
                    </a>
                    @endif
                </div>
                    <div class="mt-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Action suggérée</div>
                    <div class="text-sm font-semibold text-gray-800">{{ ucfirst((string) $item['action']) }}</div>
                    @if(!empty($item['page_id']))
                    <form method="POST" action="{{ route('admin.sites.gsc-opportunities.run', $site->site_id) }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="page_id" value="{{ $item['page_id'] }}">
                        <input type="hidden" name="type" value="{{ $item['type'] }}">
                        <button type="submit"
                                class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100">
                            Lancer cette action
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @empty
            <div class="px-6 py-10 text-center">
                <div class="text-sm font-semibold text-gray-500">Aucune opportunité GSC prioritaire.</div>
                <div class="text-xs text-gray-400 mt-1">
                    @if($gscOpportunitySummary['connected'])
                        Le site est connecté, mais aucune page ne remonte un signal assez fort pour rouvrir une action.
                    @else
                        Connectez ce site à Google Search Console pour alimenter l’autogestion avec des vraies données.
                    @endif
                </div>
            </div>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden" style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-900">Connexion Google</h2>
            <p class="text-xs text-gray-400 mt-0.5">Chaque site peut avoir sa propre propriété et ses propres credentials.</p>
        </div>
        <form method="POST" action="{{ route('admin.sites.google-connection.update', $site->site_id) }}" class="px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="{{ $labelCls }}">Mode de connexion</label>
                <select name="gsc_connection_mode" class="{{ $inputCls }}">
                    <option value="">Aucune connexion</option>
                    <option value="service_account" @selected($site->resolvedGscConnectionMode() === 'service_account')>Service account</option>
                    <option value="oauth_google" @selected($site->resolvedGscConnectionMode() === 'oauth_google')>OAuth Google</option>
                </select>
            </div>
            <div>
                <label class="{{ $labelCls }}">Propriété GSC</label>
                <input type="text" name="gsc_property_url" value="{{ old('gsc_property_url', $site->resolvedGscSiteUrl()) }}" placeholder="sc-domain:monsite.com" class="{{ $inputCls }}">
            </div>
            <div>
                <label class="{{ $labelCls }}">Chemin credentials</label>
                <input type="text" name="gsc_credentials_path" value="{{ old('gsc_credentials_path', $site->resolvedGscCredentialsPath()) }}" placeholder="/var/www/seo-engine-app/storage/google/service-account.json" class="{{ $inputCls }}">
            </div>
            <div>
                <label class="{{ $labelCls }}">Compte Google</label>
                <input type="email" name="gsc_account_email" value="{{ old('gsc_account_email', $site->resolvedGoogleConnection()?->google_account_email) }}" placeholder="service-account@project.iam.gserviceaccount.com" class="{{ $inputCls }}">
            </div>
            <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">État actuel</div>
                <div class="text-sm font-semibold text-gray-800">{{ str_replace('_', ' ', $site->resolvedGscConnectionStatus()) }}</div>
                @if($site->resolvedGoogleConnection()?->last_error)
                    <div class="text-xs text-rose-600 mt-2">{{ $site->resolvedGoogleConnection()?->last_error }}</div>
                @endif
            </div>
            <button type="submit"
                    class="w-full font-bold rounded-xl px-4 py-3 text-sm text-white transition-all hover:-translate-y-0.5"
                    style="background:linear-gradient(135deg,#0f172a,#1e293b);box-shadow:0 4px 14px rgba(15,23,42,0.25);">
                Enregistrer la connexion Google
            </button>
        </form>
    </div>
</div>

{{-- ═══ PAGES TABLE + SIDEBAR ═══ --}}
<div class="flex items-start gap-6 anim-fade-up delay-150">

    {{-- Pages table --}}
    <div class="flex-1 min-w-0 bg-white rounded-2xl border border-gray-100 overflow-hidden"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-gray-900">Articles</h2>
                <p class="text-xs text-gray-400 mt-0.5">Tous les contenus générés pour ce site.</p>
            </div>
            <span class="text-xs font-semibold text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full">{{ $pages->total() }} article(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background:#f8f9fc;">
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Sujet</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden md:table-cell">Slug</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Statut</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden lg:table-cell">SEO</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden lg:table-cell">Qualité</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden xl:table-cell">Modifié</th>
                        <th class="text-right px-6 py-3 border-b border-gray-100"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($pages as $page)
                    @php
                        $statusMap = [
                            'published' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            'draft'     => 'bg-gray-100 text-gray-600 border-gray-200',
                            'review'    => 'bg-amber-50 text-amber-700 border-amber-100',
                            'error'     => 'bg-rose-50 text-rose-700 border-rose-100',
                        ];
                        $statusCls = $statusMap[$page->status] ?? 'bg-gray-100 text-gray-600 border-gray-200';
                        $seo = (float)($page->seo_score ?? 0);
                        $qs  = (float)($page->quality_score ?? 0);
                        $seoColor = $seo >= 70 ? 'text-emerald-600' : ($seo >= 40 ? 'text-amber-600' : 'text-rose-500');
                        $qsColor  = $qs  >= 70 ? 'text-emerald-600' : ($qs  >= 40 ? 'text-amber-600' : 'text-rose-500');
                    @endphp
                    <tr class="hover:bg-gray-50/60 transition-colors group">
                        <td class="px-6 py-3.5 font-semibold text-gray-900 max-w-[200px] truncate">{{ $page->keyword }}</td>
                        <td class="px-6 py-3.5 text-gray-400 text-xs font-mono hidden md:table-cell max-w-[140px] truncate">{{ $page->slug }}</td>
                        <td class="px-6 py-3.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold border {{ $statusCls }}">
                                {{ $page->status }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right hidden lg:table-cell">
                            @if($page->seo_score)
                                <span class="font-bold {{ $seoColor }}">{{ number_format($seo, 0) }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right hidden lg:table-cell">
                            @if($page->quality_score)
                                <span class="font-bold {{ $qsColor }}">{{ number_format($qs, 0) }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right text-xs text-gray-400 hidden xl:table-cell">{{ $page->updated_at?->diffForHumans() }}</td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="{{ route('admin.pages.show', [$site->site_id, $page->id]) }}"
                               class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-100">
                                Ouvrir
                            </a>
                            <form method="POST"
                                  action="{{ route('admin.pages.destroy', [$site->site_id, $page->id]) }}"
                                  class="inline-block ml-2"
                                  onsubmit="return confirm('Supprimer cet article ? Cette action est definitive.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100">
                                    Supprimer
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="w-10 h-10 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="text-sm font-semibold text-gray-400">Aucun article pour le moment.</div>
                            <div class="text-xs text-gray-300 mt-1">Utilisez le bloc de droite pour en créer un.</div>
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

    {{-- Right sidebar --}}
    <div class="w-72 shrink-0 space-y-4">

        {{-- Generate form --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
             style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-bold text-gray-900">Créer un article</h2>
                <p class="text-xs text-gray-400 mt-0.5">Lancez un nouvel article à partir d un sujet simple.</p>
            </div>
            <form method="POST" action="{{ route('admin.pages.generate', $site->site_id) }}" class="px-6 py-5 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelCls }}">Sujet de l article</label>
                    <input type="text" name="keyword" placeholder="ex: dta amiante copropriete" class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">Étape de départ</label>
                    <select name="status" class="{{ $inputCls }}">
                        <option value="draft">Brouillon</option>
                        <option value="review">En révision</option>
                        <option value="published">Publié</option>
                    </select>
                </div>
                <button type="submit"
                        class="w-full font-bold rounded-xl px-4 py-3 text-sm text-white transition-all hover:-translate-y-0.5"
                        style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 14px rgba(99,102,241,0.35);">
                    Créer l article →
                </button>
            </form>
        </div>

        {{-- Site info --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
             style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-5 py-3.5 border-b border-gray-100">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Informations du site</div>
            </div>
            <div class="px-5 py-3 space-y-2.5">
                @foreach([
                    ['label' => 'Identifiant',   'value' => $site->site_id,                                               'mono' => true],
                    ['label' => 'Mode moteur',   'value' => $site->preset ?? 'generic',                                  'mono' => false],
                    ['label' => 'Créé le',       'value' => $site->created_at?->format('d/m/Y'),                         'mono' => false],
                    ['label' => 'Webhook',       'value' => $site->webhook_url ? '✓ Configuré' : '—',                   'mono' => false],
                    ['label' => 'Connexion Google', 'value' => $site->resolvedGscConnectionMode() ?? '—',               'mono' => false],
                    ['label' => 'État Google',   'value' => str_replace('_', ' ', $site->resolvedGscConnectionStatus()),'mono' => false],
                    ['label' => 'Propriété Google', 'value' => $site->resolvedGscSiteUrl() ?: '—',                      'mono' => false],
                ] as $info)
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-gray-400">{{ $info['label'] }}</span>
                    <span class="text-xs font-semibold {{ $info['mono'] ? 'font-mono' : '' }} text-gray-700 text-right max-w-[140px] truncate">
                        {{ $info['value'] }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>

    </div>
</div>

@endsection
