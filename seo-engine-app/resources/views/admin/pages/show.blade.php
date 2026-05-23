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
    $extractRewriteTargetPlan = function ($payload): array {
        $summary = is_array($payload['signals_summary'] ?? null) ? $payload['signals_summary'] : [];
        return collect(\Illuminate\Support\Arr::wrap($summary['rewrite_target_plan'] ?? []))
            ->filter(fn ($item) => is_array($item) && is_string($item['heading'] ?? null))
            ->map(function (array $item): array {
                return [
                    'heading' => (string) ($item['heading'] ?? ''),
                    'phase' => is_string($item['phase'] ?? null) ? (string) $item['phase'] : null,
                    'patch_intent' => (string) ($item['patch_intent'] ?? 'local_reinforcement'),
                    'replacement_mode' => (string) ($item['replacement_mode'] ?? 'replace_if_better'),
                    'instruction' => (string) ($item['instruction'] ?? ''),
                    'reasons' => collect(\Illuminate\Support\Arr::wrap($item['reasons'] ?? []))
                        ->filter(fn ($reason) => is_string($reason) && trim($reason) !== '')
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    };
    $latestPendingTargetPlan = $latestPendingSuggestion ? $extractRewriteTargetPlan(is_array($latestPendingSuggestion->suggestions_json ?? null) ? $latestPendingSuggestion->suggestions_json : []) : [];
    $latestMetric = $latestMetric ?? null;
    $publicationSummary = session('publication_summary', $publicationSummary ?? null);
    $enginePublished = $page->isPublishedInEngine();
    $livePublished = $page->isPublishedLive();
    $liveUrl = $page->live_url ?: rtrim((string) $site->url, '/').$page->canonicalPath();
    $pageIsHealthy = (float) ($page->seo_score ?? 0) >= 70 && (float) ($page->quality_score ?? 0) >= 80 && (float) ($page->indexability_score ?? 0) >= 65;
    $pageIsApproved = $enginePublished
        || ($page->status === 'review' && $pendingSuggestions->isEmpty() && empty($page->review_issues_json));
    $imageApproved = ($page->image_status ?? null) === 'approved' || (float) ($page->image_quality_score ?? 0) >= 80;
    $workflowStates = [
        ['label' => 'Draft', 'active' => in_array($page->status, ['draft', 'review', 'published'], true)],
        ['label' => 'Preview', 'active' => filled($page->content)],
        ['label' => 'Review', 'active' => in_array($page->status, ['review', 'published'], true)],
        ['label' => 'Publish moteur', 'active' => $enginePublished],
        ['label' => 'Push live', 'active' => $livePublished],
        ['label' => 'Monitor', 'active' => $latestMetric !== null || (($observedRewriteContext['matched'] ?? false) === true)],
    ];
    $heroBadges = array_filter([
        $enginePublished ? ['label' => 'Published engine', 'tone' => 'bg-emerald-100 text-emerald-700'] : null,
        $livePublished ? ['label' => 'Live', 'tone' => 'bg-sky-100 text-sky-700'] : null,
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
            @if($page->content)
            <a href="{{ route('admin.pages.preview', [$site->site_id, $page->id]) }}"
               target="_blank"
               class="flex items-center gap-2 px-4 py-2 border border-indigo-200 text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50 text-sm rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Prévisualiser
            </a>
            @endif
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
                <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Statut éditorial moteur</div>
                <div class="mt-2 text-4xl font-semibold text-slate-900">{{ $enginePublished ? 'Publié côté moteur' : 'En préparation côté moteur' }}</div>
                <div class="mt-2 text-sm text-slate-500">Ce statut décrit l’état interne de `SeoPage`. Il ne prouve pas à lui seul qu’un CMS externe a publié la page ni qu’elle est déjà visible au crawl.</div>

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
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Observed runtime</div>
                    <div class="mt-3 text-4xl font-semibold text-slate-900">{{ (int) round((float) ($latestMetric?->impressions ?? 0)) }}</div>
                    <div class="mt-1 text-sm text-slate-500">impressions observées</div>
                    <div class="mt-6 text-sm text-slate-600">
                        CTR {{ number_format((float) ($latestMetric?->ctr ?? 0) * 100, 2) }}% · Pos {{ number_format((float) ($latestMetric?->position ?? 0), 1) }}
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white px-5 py-5">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Workflow éditorial</div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($workflowStates as $item)
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ $item['active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $item['label'] }}
                            </span>
                        @endforeach
                    </div>
                    <div class="mt-5 text-sm text-slate-600">
                        {{ $pendingSuggestions->count() }} suggestion(s) éditoriales pending · image {{ $imageApproved ? 'validée' : 'à revoir' }} · indexation {{ ($page->is_indexed || ($latestMetric?->is_indexed ?? false)) ? 'observée' : 'à confirmer' }}
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border {{ ($page->generation_source ?? null) === 'fallback' ? 'border-rose-200 bg-rose-50' : (($page->generation_source ?? null) === 'hybrid' ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50') }} px-5 py-5">
                <div class="text-xs uppercase tracking-[0.25em] {{ ($page->generation_source ?? null) === 'fallback' ? 'text-rose-600' : (($page->generation_source ?? null) === 'hybrid' ? 'text-amber-600' : 'text-emerald-600') }}">Source réelle de génération</div>
                <div class="mt-2 flex items-center justify-between gap-4">
                    <div class="text-lg font-semibold {{ ($page->generation_source ?? null) === 'fallback' ? 'text-rose-900' : (($page->generation_source ?? null) === 'hybrid' ? 'text-amber-900' : 'text-emerald-900') }}">
                        {{ $page->generationSourceLabel() }}
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ ($page->generation_source ?? null) === 'fallback' ? 'bg-white text-rose-700' : (($page->generation_source ?? null) === 'hybrid' ? 'bg-white text-amber-700' : 'bg-white text-emerald-700') }}">
                        {{ $page->generation_error ? 'Erreur AI visible' : 'Aucune erreur AI' }}
                    </span>
                </div>
                <div class="mt-2 text-sm {{ ($page->generation_source ?? null) === 'fallback' ? 'text-rose-800' : (($page->generation_source ?? null) === 'hybrid' ? 'text-amber-800' : 'text-emerald-800') }}">
                    @if(($page->generation_source ?? null) === 'fallback')
                        L’article visible sur cette fiche vient du preset de secours, pas d’une génération AI complète.
                    @elseif(($page->generation_source ?? null) === 'hybrid')
                        L’AI a bien répondu, mais le preset a complété une partie du payload avant sauvegarde.
                    @else
                        La page affichée vient d’une génération AI complète.
                    @endif
                </div>
                @if($page->generation_error)
                    <div class="mt-4 rounded-2xl border border-white/70 bg-white/80 px-4 py-3 text-sm text-slate-700">
                        <div class="font-medium text-slate-900">Dernière erreur AI</div>
                        <div class="mt-1">{{ $page->generation_error }}</div>
                        @if($page->generationMissingKeys() !== [])
                            <div class="mt-3">
                                <div class="font-medium text-slate-900">Clés manquantes</div>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @foreach($page->generationMissingKeys() as $key)
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-700">{{ $key }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if($page->generationReturnedKeys() !== [])
                            <div class="mt-3">
                                <div class="font-medium text-slate-900">Clés renvoyées par OpenAI</div>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @foreach($page->generationReturnedKeys() as $key)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $key }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if(filled($page->generation_trace_json['response_excerpt'] ?? null))
                            <div class="mt-3">
                                <div class="font-medium text-slate-900">Extrait brut de réponse</div>
                                <div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-600 whitespace-pre-wrap">{{ $page->generation_trace_json['response_excerpt'] }}</div>
                            </div>
                        @endif
                    </div>
                @endif
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

                @if(!empty($latestPendingTargetPlan))
                    <div class="mt-4 rounded-2xl border border-purple-200 bg-white/80 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-purple-700">Plan de patch ciblé</div>
                        <div class="mt-3 space-y-3">
                            @foreach(array_slice($latestPendingTargetPlan, 0, 3) as $target)
                                <div class="rounded-xl border border-purple-100 bg-purple-50/50 px-3 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="text-sm font-medium text-gray-900">{{ $target['heading'] }}</div>
                                        @if(!empty($target['phase']))
                                            <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-gray-600">phase {{ $target['phase'] }}</span>
                                        @endif
                                        <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-medium text-purple-700">{{ $target['patch_intent'] }}</span>
                                    </div>
                                    @if(!empty($target['reasons']))
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach($target['reasons'] as $reason)
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">{{ $reason }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(!empty($target['instruction']))
                                        <div class="mt-2 text-xs text-gray-600">{{ $target['instruction'] }}</div>
                                    @endif
                                    <div class="mt-1 text-[11px] text-gray-400">{{ $target['replacement_mode'] }}</div>
                                </div>
                            @endforeach
                        </div>
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

            <div class="rounded-3xl border {{ $livePublished ? 'border-sky-100 bg-sky-50' : ($enginePublished ? 'border-emerald-100 bg-emerald-50' : 'border-amber-100 bg-amber-50') }} px-5 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.25em] {{ $livePublished ? 'text-sky-600' : ($enginePublished ? 'text-emerald-600' : 'text-amber-600') }}">Publication live</div>
                        <div class="mt-2 text-lg font-semibold {{ $livePublished ? 'text-sky-900' : ($enginePublished ? 'text-emerald-900' : 'text-amber-900') }}">
                            {{ $livePublished ? 'Publiée sur le site public' : ($enginePublished ? 'Prête à être poussée en live' : 'Publication live indisponible') }}
                        </div>
                        <div class="mt-2 text-sm {{ $livePublished ? 'text-sky-800' : ($enginePublished ? 'text-emerald-800' : 'text-amber-800') }}">
                            @if($livePublished)
                                L’URL publique est maintenant servie par le site. Le sitemap et la couverture observed peuvent s’appuyer sur cette vraie publication live.
                            @elseif($enginePublished)
                                La page est validée dans le moteur. Il reste maintenant à la publier réellement sur le domaine public.
                            @else
                                Il faut d’abord passer la publication moteur avant de pouvoir créer une vraie URL publique.
                            @endif
                        </div>
                    </div>
                    @if($livePublished)
                        <a href="{{ $liveUrl }}" target="_blank" class="inline-flex items-center rounded-full bg-sky-600 hover:bg-sky-700 px-5 py-2.5 text-sm font-medium text-white transition-colors">
                            Ouvrir l’URL live
                        </a>
                    @elseif($enginePublished)
                    <form method="POST" action="{{ route('admin.pages.publish-live', [$site->site_id, $page->id]) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-full bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 text-sm font-medium text-white transition-colors">
                            Publier en live sur le site
                        </button>
                    </form>
                    @endif
                </div>

                @if($livePublished)
                    <div class="mt-4 text-sm text-sky-800">Live URL : <a href="{{ $liveUrl }}" target="_blank" class="font-medium underline">{{ $liveUrl }}</a></div>
                @elseif($enginePublished)
                    <div class="mt-4 text-sm text-emerald-800">Le contenu est prêt. Cette action crée maintenant une vraie URL publique au lieu d’un simple statut en base.</div>
                @else
                    <div class="mt-4 text-sm text-amber-800">La publication live est volontairement séparée de la validation éditoriale moteur.</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Guided publication blockers --}}
@if($page->status !== 'published' && !empty($publicationSummary['failed_rules'] ?? []))
@php
    $failedRules = $publicationSummary['failed_rules'] ?? [];
    $scores      = $publicationSummary['scores'] ?? [];

    $blockerMap = [
        'seo_score_below_threshold' => [
            'label'   => 'Score SEO insuffisant',
            'detail'  => 'Actuel : '.((int)($page->seo_score ?? 0)).'/100 — seuil : 70',
            'type'    => 'diagnostic',
            'next'    => 'Créer une suggestion éditoriale de rewrite/enrichissement si vous voulez traiter ce point automatiquement.',
            'color'   => 'rose',
        ],
        'indexability_below_threshold' => [
            'label'   => 'Indexabilité insuffisante',
            'detail'  => 'Actuel : '.((int)($page->indexability_score ?? 0)).'/100 — seuil : 65',
            'type'    => 'diagnostic',
            'next'    => 'Le cockpit ne corrige pas réellement l’indexation en un clic. Traitez ce point via revue éditoriale ou action CMS réelle.',
            'color'   => 'rose',
        ],
        'faq_count_below_minimum' => [
            'label'   => 'FAQ insuffisante',
            'detail'  => 'Actuel : '.count($page->faq_json ?? []).' question(s) — minimum : 5',
            'type'    => 'diagnostic',
            'next'    => 'Passez par une suggestion éditoriale si vous souhaitez enrichir la FAQ. Le bouton magique a été retiré.',
            'color'   => 'amber',
        ],
        'image_not_approved' => [
            'label'   => 'Image non approuvée',
            'detail'  => 'Statut actuel : '.($page->image_status ?? 'missing'),
            'type'    => 'quickfix',
            'action'  => 'approve_image',
            'btn'     => 'Marquer l\'image comme approuvée',
            'color'   => 'amber',
        ],
        'status_not_pending_review' => [
            'label'   => 'Statut incorrect pour publication',
            'detail'  => 'Statut actuel : '.($page->status ?? 'draft').' — requis : review',
            'type'    => 'quickfix',
            'action'  => 'set_review',
            'btn'     => 'Passer en review',
            'color'   => 'amber',
        ],
        'forced_noindex' => [
            'label'   => 'Forced noindex activé',
            'detail'  => 'La page est forcée en noindex par override manuel.',
            'type'    => 'quickfix',
            'action'  => 'clear_noindex',
            'btn'     => 'Retirer le forced noindex',
            'color'   => 'rose',
        ],
        'duplicate_risk_high' => [
            'label'   => 'Risque de duplication élevé',
            'detail'  => 'Score : '.number_format((float)($page->duplicate_risk_score ?? 0) * 100, 0).'% — seuil max : 70%',
            'type'    => 'diagnostic',
            'next'    => 'Utilisez une suggestion éditoriale de rewrite si vous voulez retravailler le contenu. La déduplication n’est pas automatique.',
            'color'   => 'rose',
        ],
        'spam_risk_high' => [
            'label'   => 'Risque spam détecté',
            'detail'  => 'Le moteur a détecté des signaux spam dans le contenu.',
            'type'    => 'diagnostic',
            'next'    => 'Revue manuelle recommandée. Aucun correctif automatique fiable n’est proposé ici.',
            'color'   => 'rose',
        ],
    ];

    $colorsMap = [
        'rose'  => ['bg' => 'bg-rose-50',  'border' => 'border-rose-200',  'text' => 'text-rose-800',  'sub' => 'text-rose-600',  'dot' => 'bg-rose-500',  'btn' => 'border-rose-300 text-rose-700 hover:bg-rose-100'],
        'amber' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-800', 'sub' => 'text-amber-600', 'dot' => 'bg-amber-400', 'btn' => 'border-amber-300 text-amber-700 hover:bg-amber-100'],
    ];
@endphp

<div class="bg-white rounded-xl border border-rose-200 shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-rose-100 flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-gray-900 text-sm">Diagnostic de publication moteur</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ count($failedRules) }} point(s) bloquants. Les actions ci-dessous sont limitées aux correctifs réellement exécutables.</p>
        </div>
        <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
            {{ count($failedRules) }} bloquant(s)
        </span>
    </div>

    <div class="divide-y divide-gray-50">
        @foreach($failedRules as $rule)
        @php $def = $blockerMap[$rule] ?? null; @endphp
        @if($def)
        @if($rule === 'image_not_approved')
            @php
                $hasGeneratedImage = filled($page->image_path);
                $def['detail'] = $hasGeneratedImage
                    ? 'Statut actuel : '.($page->image_status ?? 'generated').' — le visuel existe, il reste à le valider.'
                    : 'Aucun visuel généré pour cette page. Le moteur a seulement un prompt image.';
                $def['action'] = $hasGeneratedImage ? 'approve_image' : 'generate_image';
                $def['btn'] = $hasGeneratedImage ? 'Approuver l’image' : 'Générer l’image IA';
            @endphp
        @endif
        @php $c = $colorsMap[$def['color']]; @endphp
        <div class="px-6 py-4 flex items-center justify-between gap-6">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-2 h-2 rounded-full {{ $c['dot'] }} mt-1.5 shrink-0"></div>
                <div class="min-w-0">
                    <div class="text-sm font-medium text-gray-900">{{ $def['label'] }}</div>
                    <div class="text-xs {{ $c['sub'] }} mt-0.5">{{ $def['detail'] }}</div>
                    @if(!empty($def['next']))
                    <div class="text-xs text-gray-500 mt-1">{{ $def['next'] }}</div>
                    @endif
                </div>
            </div>

            <div class="shrink-0">
                @if($def['type'] === 'quickfix')
                <form method="POST" action="{{ route('admin.pages.quick-fix', [$site->site_id, $page->id]) }}">
                    @csrf
                    <input type="hidden" name="action" value="{{ $def['action'] }}">
                    <button type="submit"
                        class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors {{ $c['btn'] }}">
                        {{ $def['btn'] }}
                    </button>
                </form>
                @else
                <span class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-500">
                    Diagnostic only
                </span>
                @endif
            </div>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif

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
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm font-semibold text-purple-800">Suggestion créée — en attente d'application</div>
                @if(!empty($suggestion['id']))
                <form method="POST" action="{{ route('admin.pages.suggestions.apply', [$site->site_id, $page->id, $suggestion['id']]) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center rounded-full bg-purple-700 px-4 py-2 text-xs font-semibold text-white hover:bg-purple-800 transition-colors">
                        Appliquer &amp; recalculer les scores →
                    </button>
                </form>
                @endif
            </div>
            @if(!empty($suggestion['proposed_content']) || !empty($suggestion['content']))
            @php $previewContent = $suggestion['proposed_content'] ?? $suggestion['content']; @endphp
            <div class="bg-white rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap max-h-64 overflow-y-auto">{{ Str::limit(strip_tags((string) $previewContent), 2000) }}</div>
            @else
            <div class="bg-white rounded-lg p-4 space-y-4 text-sm text-gray-700">
                @if(!empty($suggestion['title']) || !empty($suggestion['meta_description']))
                    <div>
                        @if(!empty($suggestion['title']))
                            <div class="font-semibold text-gray-900">{{ $suggestion['title'] }}</div>
                        @endif
                        @if(!empty($suggestion['meta_description']))
                            <div class="mt-1 text-sm text-gray-500">{{ $suggestion['meta_description'] }}</div>
                        @endif
                    </div>
                @endif

                @if(!empty($suggestion['sections']))
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-purple-700 mb-2">Passes proposées</div>
                        <div class="space-y-2">
                            @foreach(array_slice($suggestion['sections'], 0, 6) as $section)
                                <div class="flex items-start gap-2">
                                    <span class="text-purple-400 mt-0.5">•</span>
                                    <span>{{ $section }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($suggestion['faq']))
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-purple-700 mb-2">FAQ suggérée</div>
                        <div class="space-y-2">
                            @foreach(array_slice($suggestion['faq'], 0, 3) as $faq)
                                <div>
                                    <div class="font-medium text-gray-900">{{ $faq['question'] ?? 'Question' }}</div>
                                    <div class="text-gray-600">{{ $faq['answer'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($suggestion['rationale']))
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-purple-700 mb-2">Pourquoi</div>
                        <div class="space-y-2">
                            @foreach(array_slice($suggestion['rationale'], 0, 4) as $item)
                                <div class="flex items-start gap-2">
                                    <span class="text-purple-300 mt-0.5">→</span>
                                    <span>{{ $item }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

            @php $rewriteTargetPlan = $extractRewriteTargetPlan(is_array($suggestion) ? $suggestion : []); @endphp
            @if(!empty($rewriteTargetPlan))
                <div class="mt-4 rounded-2xl border border-purple-200 bg-white/80 px-4 py-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-purple-700">Plan de patch ciblé</div>
                    <div class="mt-3 space-y-3">
                        @foreach(array_slice($rewriteTargetPlan, 0, 3) as $target)
                            <div class="rounded-xl border border-purple-100 bg-purple-50/50 px-3 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="text-sm font-medium text-gray-900">{{ $target['heading'] }}</div>
                                    @if(!empty($target['phase']))
                                        <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-gray-600">phase {{ $target['phase'] }}</span>
                                    @endif
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-medium text-purple-700">{{ $target['patch_intent'] }}</span>
                                </div>
                                @if(!empty($target['reasons']))
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach($target['reasons'] as $reason)
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">{{ $reason }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if(!empty($target['instruction']))
                                    <div class="mt-2 text-xs text-gray-600">{{ $target['instruction'] }}</div>
                                @endif
                                <div class="mt-1 text-[11px] text-gray-400">{{ $target['replacement_mode'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        @endif

    </div>

    {{-- Actions sidebar --}}
    <div class="space-y-4">

        {{-- Rewrite --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-5">
            <h3 class="font-semibold text-gray-900 text-sm mb-1">Suggestion éditoriale</h3>
            <p class="text-xs text-gray-500 mb-4">Cette action crée une suggestion de rewrite sur `SeoPage`. Elle ne corrige pas magiquement le site observé ni le CMS.</p>
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
                        <option value="de-duplicate">Dédupliquer</option>
                        <option value="improve-ctr">Améliorer le CTR</option>
                        <option value="improve-indexability">Améliorer l’indexation</option>
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
            <h3 class="font-semibold text-gray-900 text-sm mb-1">Observed runtime</h3>
            <p class="text-xs text-gray-500 mb-4">Ce bloc reflète la réalité observée du site, distincte du workflow éditorial interne.</p>

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
            <div class="text-[11px] font-semibold uppercase tracking-[0.25em] text-gray-400 pb-2 border-b border-gray-100">Vérités et états</div>
            <div class="flex justify-between text-gray-500">
                <span>Site</span>
                <span class="font-medium text-gray-700">{{ $site->name }}</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Source éditoriale</span>
                <span class="font-medium text-gray-700">SeoPage</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Source observée</span>
                <span class="font-medium text-gray-700">{{ ($observedRewriteContext['matched'] ?? false) ? 'SeoSitePage liée' : 'Aucune correspondance observée' }}</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Source génération</span>
                <span class="font-medium text-gray-700">{{ $page->generationSourceLabel() }}</span>
            </div>
            <div class="flex justify-between text-gray-500">
                <span>Publié côté moteur</span>
                <span class="font-medium text-gray-700">{{ $enginePublished ? 'Oui' : 'Non' }}</span>
            </div>
            @if($page->generation_error)
            <div class="text-gray-500">
                <div class="font-medium text-gray-700 mb-1">Erreur AI mémorisée</div>
                <div class="text-xs leading-relaxed">{{ $page->generation_error }}</div>
            </div>
            @endif
            @if($page->generationMissingKeys() !== [])
            <div class="text-gray-500">
                <div class="font-medium text-gray-700 mb-1">Clés manquantes</div>
                <div class="text-xs leading-relaxed">{{ implode(', ', $page->generationMissingKeys()) }}</div>
            </div>
            @endif
            <div class="flex justify-between text-gray-500">
                <span>Publié en live</span>
                <span class="font-medium text-gray-700">{{ $livePublished ? 'Oui' : 'Non' }}</span>
            </div>
            @if($page->live_url)
            <div class="flex justify-between gap-4 text-gray-500">
                <span>Live URL</span>
                <a href="{{ $page->live_url }}" target="_blank" class="font-medium text-blue-700 underline text-right break-all">{{ $page->live_url }}</a>
            </div>
            @endif
            <div class="flex justify-between text-gray-500">
                <span>Règle de mapping</span>
                <span class="font-medium text-gray-700">{{ $page->observed_page_match_rule ?: 'Aucune' }}</span>
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
                <span>Publié côté moteur</span>
                <span class="text-gray-700">{{ $page->published_at?->format('d/m/Y') }}</span>
            </div>
            @endif
            @if($page->published_live_at)
            <div class="flex justify-between text-gray-500">
                <span>Publié en live</span>
                <span class="text-gray-700">{{ $page->published_live_at?->format('d/m/Y H:i') }}</span>
            </div>
            @endif
            @if($page->last_observed_at)
            <div class="flex justify-between text-gray-500">
                <span>Dernière observation</span>
                <span class="text-gray-700">{{ $page->last_observed_at?->format('d/m/Y H:i') }}</span>
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
