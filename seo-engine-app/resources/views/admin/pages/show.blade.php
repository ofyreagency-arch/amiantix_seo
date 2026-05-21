@extends('admin.layout')

@section('title', $page->keyword)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700">Sites</a>
    <span class="mx-2">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700">{{ $site->name }}</a>
    <span class="mx-2">›</span>
    <span class="font-medium text-gray-900 truncate max-w-xs">{{ $page->keyword }}</span>
@endsection

@section('content')

@php
    $statusColors = ['published' => 'bg-green-100 text-green-700', 'draft' => 'bg-gray-100 text-gray-600', 'review' => 'bg-yellow-100 text-yellow-700', 'error' => 'bg-red-100 text-red-700'];
    $sc = $statusColors[$page->status] ?? 'bg-gray-100 text-gray-600';
    $observedRewriteContext = session('observed_rewrite_context', $observedRewriteContext ?? null);
    $pendingSuggestions = $pendingSuggestions ?? collect();
    $latestPendingSuggestion = $pendingSuggestions->first();
    $latestMetric = $latestMetric ?? null;
    $pageIsHealthy = (float) ($page->seo_score ?? 0) >= 70 && (float) ($page->quality_score ?? 0) >= 80 && (float) ($page->indexability_score ?? 0) >= 65;
    $pageIsApproved = $pendingSuggestions->isEmpty() && empty($page->review_issues_json);
    $imageApproved = ($page->image_status ?? null) === 'approved' || (float) ($page->image_quality_score ?? 0) >= 80;
    $workflowStates = [
        ['label' => 'Draft', 'active' => in_array($page->status, ['draft', 'review', 'published'], true)],
        ['label' => 'Preview', 'active' => filled($page->content)],
        ['label' => 'Review', 'active' => in_array($page->status, ['review', 'published'], true)],
        ['label' => 'Publish', 'active' => $page->status === 'published' || filled($page->published_at)],
        ['label' => 'Monitor', 'active' => $latestMetric !== null || (($observedRewriteContext['matched'] ?? false) === true)],
    ];
    $heroBadges = array_filter([
        $page->status === 'published' || filled($page->published_at) ? ['label' => 'Published', 'tone' => 'bg-emerald-100 text-emerald-700'] : null,
        $pageIsHealthy ? ['label' => 'Healthy', 'tone' => 'bg-emerald-100 text-emerald-700'] : null,
        $pageIsApproved ? ['label' => 'Approved', 'tone' => 'bg-emerald-100 text-emerald-700'] : null,
        ($page->is_indexed || ($latestMetric?->is_indexed ?? false)) ? ['label' => 'Indexée', 'tone' => 'bg-sky-100 text-sky-700'] : null,
        filled($page->cluster) ? ['label' => Str::upper((string) $page->cluster), 'tone' => 'bg-slate-100 text-slate-700'] : null,
    ]);
    $imageUrl = null;
    if (filled($page->image_path)) {
        $imagePath = (string) $page->image_path;
        $imageUrl = Str::startsWith($imagePath, ['http://', 'https://', '/']) ? $imagePath : asset('storage/'.$imagePath);
    }
    $liveCards = [
        ['label' => 'SEO score', 'value' => (int) ($page->seo_score ?? 0), 'suffix' => '/100'],
        ['label' => 'Indexability', 'value' => (int) ($page->indexability_score ?? 0), 'suffix' => '/100'],
        ['label' => 'Quality gate', 'value' => (int) ($page->quality_score ?? 0), 'suffix' => '/100'],
        ['label' => 'Image quality', 'value' => (int) ($page->image_quality_score ?? 0), 'suffix' => '/100'],
    ];
@endphp

{{-- Header --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-5 mb-6">
    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ $page->status }}</span>
                <span class="text-xs text-gray-400 font-mono">{{ $page->slug }}</span>
            </div>
            <h1 class="text-xl font-bold text-gray-900">{{ $page->keyword }}</h1>
            @if($page->title)
                <p class="text-sm text-gray-500 mt-1">{{ $page->title }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('admin.pages.analyze', [$site->site_id, $page->id]) }}">
                @csrf
                <button type="submit"
                    class="flex items-center gap-2 px-4 py-2 border border-gray-200 text-gray-600 hover:border-gray-300 hover:text-gray-900 text-sm rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Analyser
                </button>
            </form>
        </div>
    </div>

    {{-- Scores --}}
    <div class="grid grid-cols-4 gap-4 mt-5 pt-5 border-t border-gray-100">
        @foreach([
            ['label' => 'Score SEO',         'value' => $page->seo_score],
            ['label' => 'Qualité',           'value' => $page->quality_score],
            ['label' => 'Topical',           'value' => $page->topical_score],
            ['label' => 'Indexabilité',      'value' => $page->indexability_score],
        ] as $score)
        <div class="text-center">
            @if($score['value'])
                @php $v = (float)$score['value']; $color = $v >= 70 ? 'text-green-600' : ($v >= 40 ? 'text-yellow-600' : 'text-red-500'); @endphp
                <div class="text-2xl font-bold {{ $color }}">{{ number_format($v, 0) }}</div>
            @else
                <div class="text-2xl font-bold text-gray-200">—</div>
            @endif
            <div class="text-xs text-gray-400 mt-1">{{ $score['label'] }}</div>
        </div>
        @endforeach
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-8 py-7 mb-6">
    <div class="flex flex-wrap items-center gap-2 mb-5">
        @foreach($heroBadges as $badge)
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $badge['tone'] }}">{{ $badge['label'] }}</span>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[0.42fr_0.58fr] gap-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-900 leading-tight">{{ $page->title ?: $page->keyword }}</h2>
            <div class="mt-2 text-sm text-gray-500">{{ $page->canonicalPath() }}</div>

            <div class="mt-6 rounded-3xl overflow-hidden border border-gray-100 bg-slate-50 min-h-[260px] flex items-center justify-center">
                @if($imageUrl)
                    <img src="{{ $imageUrl }}" alt="{{ $page->image_alt ?: $page->keyword }}" class="w-full h-full object-cover">
                @else
                    <div class="px-8 py-10 text-center">
                        <div class="mx-auto w-16 h-16 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-slate-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2 1.586-1.586a2 2 0 012.828 0L20 14m-6-8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="mt-4 text-lg font-semibold text-slate-700">Image en attente</div>
                        <div class="mt-2 text-sm text-slate-500">Le contenu est là, mais il manque encore le visuel qui aide la page à ressembler à un vrai objet éditorial publié.</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-6">
                <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Statut live</div>
                <div class="mt-2 text-4xl font-semibold text-slate-900">{{ filled($page->published_at) || $page->status === 'published' ? 'En ligne' : 'En préparation' }}</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                    @foreach($liveCards as $card)
                    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-slate-600">{{ $card['label'] }}</div>
                            <div class="text-2xl font-semibold text-slate-900">{{ $card['value'] }}{{ $card['suffix'] }}</div>
                        </div>
                        <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $card['value'] >= 80 ? 'bg-emerald-500' : ($card['value'] >= 60 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ max(4, min(100, (int) $card['value'])) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-3xl border border-slate-200 bg-white px-5 py-5">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Google</div>
                    <div class="mt-3 text-4xl font-semibold text-slate-900">{{ (int) round((float) ($latestMetric?->impressions ?? 0)) }}</div>
                    <div class="mt-1 text-sm text-slate-500">impressions</div>
                    <div class="mt-6 text-sm text-slate-600">
                        CTR {{ number_format((float) ($latestMetric?->ctr ?? 0) * 100, 2) }}% · Pos {{ number_format((float) ($latestMetric?->position ?? 0), 1) }}
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white px-5 py-5">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Workflow</div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($workflowStates as $item)
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $item['active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $item['label'] }}
                            </span>
                        @endforeach
                    </div>
                    <div class="mt-5 text-sm text-slate-600">
                        {{ $pendingSuggestions->count() }} suggestion(s) pending · image {{ $imageApproved ? 'validée' : 'à revoir' }} · indexation {{ ($page->is_indexed || ($latestMetric?->is_indexed ?? false)) ? 'ok' : 'à confirmer' }}
                    </div>
                </div>
            </div>

            @if($latestPendingSuggestion)
            <div class="rounded-3xl border border-purple-100 bg-purple-50 px-5 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.25em] text-purple-500">Suggestion active</div>
                        <div class="mt-2 text-lg font-semibold text-purple-900">{{ $latestPendingSuggestion->source }}</div>
                        <div class="mt-2 text-sm text-purple-700">
                            {{ collect(\Illuminate\Support\Arr::wrap($latestPendingSuggestion->suggestions_json['rationale'] ?? []))->take(2)->implode(' ') ?: 'Une suggestion éditoriale est prête à être revue.' }}
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-purple-600">{{ $latestPendingSuggestion->created_at?->diffForHumans() }}</span>
                </div>

                @if(!empty($latestPendingSuggestion->suggestions_json['sections']))
                    <div class="mt-4 space-y-2">
                        @foreach(array_slice($latestPendingSuggestion->suggestions_json['sections'], 0, 4) as $section)
                            <div class="flex items-start gap-2 text-sm text-purple-800">
                                <span class="mt-0.5 text-purple-400">•</span>
                                <span>{{ $section }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('admin.pages.suggestions.apply', [$site->site_id, $page->id, $latestPendingSuggestion->id]) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-full bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800 transition-colors">
                            Appliquer à la page
                        </button>
                    </form>
                    <a href="{{ route('admin.sites.autopilot', $site->site_id) }}" class="inline-flex items-center rounded-full border border-purple-200 bg-white px-5 py-2.5 text-sm font-medium text-purple-700 hover:bg-purple-100 transition-colors">
                        Voir toute la file
                    </a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-3 gap-6">

    {{-- Content + meta --}}
    <div class="col-span-2 space-y-4">

        {{-- Meta --}}
        @if($page->meta_description)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-4">
            <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Meta description</div>
            <p class="text-sm text-gray-700">{{ $page->meta_description }}</p>
        </div>
        @endif

        {{-- Content --}}
        @if($page->content)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-4">
            <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-3">Contenu</div>
            <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap max-h-64 overflow-y-auto">{{ Str::limit($page->content, 2000) }}</div>
        </div>
        @endif

        {{-- Review issues --}}
        @if($page->review_issues_json)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-4">
            <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-3">Problèmes détectés</div>
            <div class="space-y-2">
                @foreach($page->review_issues_json as $issue)
                <div class="flex items-start gap-2 text-sm">
                    <span class="text-red-400 mt-0.5">•</span>
                    <span class="text-gray-700">{{ is_array($issue) ? ($issue['message'] ?? json_encode($issue)) : $issue }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Analysis result --}}
        @if(session('analysis'))
        @php $analysis = session('analysis'); @endphp
        <div class="bg-blue-50 border border-blue-200 rounded-xl px-6 py-5">
            <div class="text-sm font-semibold text-blue-800 mb-3">Résultat d'analyse</div>
            @if(!empty($analysis['status_report']))
            <div class="space-y-1.5">
                @foreach((array)$analysis['status_report'] as $key => $value)
                <div class="flex items-start gap-2 text-xs">
                    <span class="text-blue-500 font-medium min-w-32">{{ $key }}</span>
                    <span class="text-blue-800">{{ is_array($value) ? json_encode($value) : $value }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- Rewrite result --}}
        @if(session('rewrite_suggestion'))
        @php $suggestion = session('rewrite_suggestion'); @endphp
        <div class="bg-purple-50 border border-purple-200 rounded-xl px-6 py-5">
            <div class="text-sm font-semibold text-purple-800 mb-3">Suggestion de réécriture</div>
            @if(!empty($suggestion['proposed_content']))
            <div class="bg-white rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap max-h-64 overflow-y-auto">{{ Str::limit($suggestion['proposed_content'], 2000) }}</div>
            @else
            <pre class="text-xs text-purple-700 whitespace-pre-wrap">{{ json_encode($suggestion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @endif
        </div>
        @endif

    </div>

    {{-- Actions sidebar --}}
    <div class="space-y-4">

        {{-- Rewrite --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-5">
            <h3 class="font-semibold text-gray-900 text-sm mb-4">Réécrire</h3>
            @if(($observedRewriteContext['matched'] ?? false) === true)
                @php
                    $state = $observedRewriteContext['state'] ?? 'unknown';
                    $stateTone = $state === 'critical'
                        ? 'bg-red-50 text-red-700 border-red-100'
                        : ($state === 'warning' ? 'bg-amber-50 text-amber-700 border-amber-100' : 'bg-emerald-50 text-emerald-700 border-emerald-100');
                @endphp
                <div class="mb-4 rounded-lg border px-3 py-3 {{ $stateTone }}">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide">Contexte observed</div>
                            <div class="text-sm mt-1">
                                {{ ucfirst($state) }} · score {{ $observedRewriteContext['health']['health_score'] ?? '—' }}
                            </div>
                        </div>
                        <div class="text-xs text-right">
                            <div>{{ count($observedRewriteContext['flags'] ?? []) }} flag(s)</div>
                            <div>{{ count($observedRewriteContext['recommendations'] ?? []) }} reco(s)</div>
                        </div>
                    </div>

                    @if(!empty($observedRewriteContext['flags']))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach(($observedRewriteContext['flags'] ?? []) as $flag)
                                <span class="inline-flex items-center rounded-full bg-white/80 px-2.5 py-1 text-[11px] font-medium text-gray-700">{{ $flag }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            <form method="POST" action="{{ route('admin.pages.rewrite', [$site->site_id, $page->id]) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Mode</label>
                    <select name="mode"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="enrich">Enrichir</option>
                        <option value="rewrite">Réécriture complète</option>
                        <option value="freshen">Actualiser</option>
                        <option value="shorten">Raccourcir</option>
                    </select>
                </div>
                <button type="submit"
                    class="w-full bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg px-4 py-2 text-sm transition-colors">
                    Créer suggestion
                </button>
            </form>
        </div>

        @if(($observedRewriteContext['matched'] ?? false) === true && (!empty($observedRewriteContext['recommendations']) || !empty($observedRewriteContext['sections'])))
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-5">
            <h3 class="font-semibold text-gray-900 text-sm mb-4">Backlog observed</h3>

            @if(!empty($observedRewriteContext['sections']))
                <div class="space-y-2 mb-4">
                    @foreach(array_slice($observedRewriteContext['sections'], 0, 3) as $section)
                        <div class="flex items-start gap-2 text-sm text-gray-700">
                            <span class="text-purple-400 mt-0.5">•</span>
                            <span>{{ $section }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($observedRewriteContext['recommendations']))
                <div class="space-y-3">
                    @foreach(array_slice($observedRewriteContext['recommendations'], 0, 3) as $item)
                        <div class="rounded-lg border border-gray-100 px-3 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="text-sm font-medium text-gray-900">{{ $item['title'] }}</div>
                                <span class="text-[11px] rounded-full bg-gray-100 px-2 py-0.5 text-gray-500">P{{ $item['priority'] }}</span>
                            </div>
                            @if(!empty($item['suggested_action']))
                                <div class="text-xs text-gray-500 mt-1">{{ $item['suggested_action'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        @endif

        {{-- Page info --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-4 space-y-2.5 text-xs">
            <div class="flex justify-between text-gray-500">
                <span>Site</span>
                <span class="font-medium text-gray-700">{{ $site->name }}</span>
            </div>
            @if($page->cluster)
            <div class="flex justify-between text-gray-500">
                <span>Cluster</span>
                <span class="font-medium text-gray-700">{{ $page->cluster }}</span>
            </div>
            @endif
            @if($page->duplicate_risk_score)
            <div class="flex justify-between text-gray-500">
                <span>Risque doublon</span>
                <span class="font-medium {{ $page->duplicate_risk_score > 0.7 ? 'text-red-600' : 'text-gray-700' }}">{{ number_format((float)$page->duplicate_risk_score * 100, 0) }}%</span>
            </div>
            @endif
            <div class="flex justify-between text-gray-500">
                <span>Créé</span>
                <span class="text-gray-700">{{ $page->created_at?->format('d/m/Y') }}</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Modifié</span>
                <span class="text-gray-700">{{ $page->updated_at?->diffForHumans() }}</span>
            </div>
            @if($page->published_at)
            <div class="flex justify-between text-gray-500">
                <span>Publié</span>
                <span class="text-gray-700">{{ $page->published_at?->format('d/m/Y') }}</span>
            </div>
            @endif
        </div>

        <a href="{{ route('admin.sites.show', $site->site_id) }}"
           class="block text-center text-sm text-gray-500 hover:text-gray-700 py-2">
            ← Retour aux pages
        </a>
    </div>

</div>
@endsection
