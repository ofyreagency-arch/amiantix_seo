@extends('admin.layout')
@section('title', $page->keyword)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700 transition-colors">Sites</a>
    <span class="mx-2 text-gray-300">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700 transition-colors">{{ $site->name }}</a>
    <span class="mx-2 text-gray-300">›</span>
    <span class="font-semibold text-gray-900 truncate max-w-xs">{{ $page->keyword }}</span>
@endsection

@section('content')

@php
    /* ── Status badge ── */
    $scCls = 'bg-gray-100 text-gray-600';
    if ($page->status === 'published') $scCls = 'bg-emerald-100 text-emerald-700';
    elseif ($page->status === 'review') $scCls = 'bg-amber-100 text-amber-700';
    elseif ($page->status === 'error')  $scCls = 'bg-rose-100 text-rose-700';

    /* ── Computed booleans ── */
    $enginePublished = $page->isPublishedInEngine();
    $livePublished   = $page->isPublishedLive();
    $liveUrl         = $page->live_url ?: rtrim((string) $site->url, '/').$page->canonicalPath();

    /* ── Generation source colours ── */
    $genSource = $page->generation_source ?? null;
    $genBorder = 'border-emerald-200'; $genBg = 'bg-emerald-50'; $genLabel = 'text-emerald-600';
    $genTitle  = 'text-emerald-900';   $genText = 'text-emerald-800';
    if ($genSource === 'fallback') {
        $genBorder = 'border-rose-200'; $genBg = 'bg-rose-50'; $genLabel = 'text-rose-600';
        $genTitle  = 'text-rose-900';   $genText = 'text-rose-800';
    } elseif ($genSource === 'hybrid') {
        $genBorder = 'border-amber-200'; $genBg = 'bg-amber-50'; $genLabel = 'text-amber-600';
        $genTitle  = 'text-amber-900';   $genText = 'text-amber-800';
    }

    /* ── Live publication colours ── */
    $pubBorder = 'border-amber-100'; $pubBg = 'bg-amber-50'; $pubLabel = 'text-amber-600';
    $pubTitle  = 'text-amber-900';   $pubText = 'text-amber-800';
    if ($livePublished) {
        $pubBorder = 'border-sky-100'; $pubBg = 'bg-sky-50'; $pubLabel = 'text-sky-600';
        $pubTitle  = 'text-sky-900';   $pubText = 'text-sky-800';
    } elseif ($enginePublished) {
        $pubBorder = 'border-emerald-100'; $pubBg = 'bg-emerald-50'; $pubLabel = 'text-emerald-600';
        $pubTitle  = 'text-emerald-900';   $pubText = 'text-emerald-800';
    }

    /* ── Misc ── */
    $dupCls          = (float) ($page->duplicate_risk_score ?? 0) > 0.7 ? 'text-rose-600 font-semibold' : 'text-gray-700';
    $pageIsHealthy   = (float) ($page->seo_score ?? 0) >= 70 && (float) ($page->quality_score ?? 0) >= 80 && (float) ($page->indexability_score ?? 0) >= 65;
    $latestMetric    = $latestMetric ?? null;
    $publicationSummary = session('publication_summary', $publicationSummary ?? null);

    $observedRewriteContext = session('observed_rewrite_context', $observedRewriteContext ?? null);
    $pendingSuggestions     = $pendingSuggestions ?? collect();
    $latestPendingSuggestion = $pendingSuggestions->first();

    $rwState  = $observedRewriteContext['state'] ?? 'unknown';
    $rwBorder = 'border-emerald-100'; $rwBg = 'bg-emerald-50/60'; $rwText = 'text-emerald-700';
    if ($rwState === 'critical') { $rwBorder = 'border-rose-100'; $rwBg = 'bg-rose-50/60'; $rwText = 'text-rose-700'; }
    elseif ($rwState === 'warning') { $rwBorder = 'border-amber-100'; $rwBg = 'bg-amber-50/60'; $rwText = 'text-amber-700'; }

    $pageIsApproved  = $enginePublished || ($page->status === 'review' && $pendingSuggestions->isEmpty() && empty($page->review_issues_json));
    $imageApproved   = ($page->image_status ?? null) === 'approved' || (float) ($page->image_quality_score ?? 0) >= 80;

    $imageUrl = null;
    if (filled($page->image_path)) {
        $imagePath = (string) $page->image_path;
        $imageUrl  = Str::startsWith($imagePath, ['http://', 'https://', '/']) ? $imagePath : asset('storage/'.$imagePath);
    }

    $workflowStates = [
        ['label' => 'Draft',          'active' => in_array($page->status, ['draft','review','published'], true)],
        ['label' => 'Preview',        'active' => filled($page->content)],
        ['label' => 'Review',         'active' => in_array($page->status, ['review','published'], true)],
        ['label' => 'Publish moteur', 'active' => $enginePublished],
        ['label' => 'Push live',      'active' => $livePublished],
        ['label' => 'Monitor',        'active' => $latestMetric !== null || (($observedRewriteContext['matched'] ?? false) === true)],
    ];

    $extractRewriteTargetPlan = function ($payload): array {
        $summary = is_array($payload['signals_summary'] ?? null) ? $payload['signals_summary'] : [];
        return collect(\Illuminate\Support\Arr::wrap($summary['rewrite_target_plan'] ?? []))
            ->filter(fn ($item) => is_array($item) && is_string($item['heading'] ?? null))
            ->map(function (array $item): array {
                return [
                    'heading'          => (string) ($item['heading'] ?? ''),
                    'phase'            => is_string($item['phase'] ?? null) ? (string) $item['phase'] : null,
                    'patch_intent'     => (string) ($item['patch_intent'] ?? 'local_reinforcement'),
                    'replacement_mode' => (string) ($item['replacement_mode'] ?? 'replace_if_better'),
                    'instruction'      => (string) ($item['instruction'] ?? ''),
                    'reasons'          => collect(\Illuminate\Support\Arr::wrap($item['reasons'] ?? []))
                        ->filter(fn ($r) => is_string($r) && trim($r) !== '')->values()->all(),
                ];
            })->values()->all();
    };
    $rewriteTargetSummary = function (array $targets): array {
        $rc = collect($targets)->flatMap(fn ($t) => $t['reasons'] ?? [])->filter(fn ($r) => is_string($r) && trim($r) !== '')->countBy()->sortDesc();
        $pc = collect($targets)->pluck('phase')->filter(fn ($p) => is_string($p) && trim($p) !== '')->countBy()->sortDesc();
        return ['sections' => count($targets), 'top_reasons' => $rc->keys()->take(3)->values()->all(), 'phases' => $pc->keys()->take(3)->values()->all()];
    };

    $latestPendingTargetPlan    = $latestPendingSuggestion ? $extractRewriteTargetPlan(is_array($latestPendingSuggestion->suggestions_json ?? null) ? $latestPendingSuggestion->suggestions_json : []) : [];
    $latestPendingTargetSummary = $rewriteTargetSummary($latestPendingTargetPlan);

    $liveCards = [
        ['label' => 'Score SEO',     'value' => (int) ($page->seo_score ?? 0)],
        ['label' => 'Indexabilité',  'value' => (int) ($page->indexability_score ?? 0)],
        ['label' => 'Quality gate',  'value' => (int) ($page->quality_score ?? 0)],
        ['label' => 'Image quality', 'value' => (int) ($page->image_quality_score ?? 0)],
    ];
@endphp

<div class="admin-page-shell">

{{-- ═══════════════════════════════════════════
     HEADER CARD
════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-100 px-7 py-6 mb-5 anim-fade-up"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $scCls }}">{{ $page->status }}</span>
                <span class="text-xs text-gray-400 font-mono truncate max-w-xs">{{ $page->slug }}</span>
                @if(filled($page->cluster))
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">{{ Str::upper((string) $page->cluster) }}</span>
                @endif
            </div>
            <h1 class="text-2xl font-black text-gray-900 leading-tight">{{ $page->keyword }}</h1>
            @if($page->title)
            <p class="text-sm text-gray-500 mt-1">{{ $page->title }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if($page->content)
            <a href="{{ route('admin.pages.preview', [$site->site_id, $page->id]) }}"
               target="_blank"
               class="flex items-center gap-1.5 px-4 py-2 border border-indigo-200 text-indigo-600 hover:border-indigo-300 hover:bg-indigo-50 text-sm font-semibold rounded-xl transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Preview
            </a>
            @endif
            <form method="POST" action="{{ route('admin.pages.analyze', [$site->site_id, $page->id]) }}">
                @csrf
                <button type="submit"
                    class="flex items-center gap-1.5 px-4 py-2 text-sm font-bold text-white rounded-xl transition-all hover:-translate-y-0.5"
                    style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 14px rgba(99,102,241,0.3);">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Analyser
                </button>
            </form>
        </div>
    </div>

    {{-- Score gauges --}}
    <div class="grid grid-cols-4 gap-4 mt-5 pt-5 border-t border-gray-100">
        @foreach([
            ['label' => 'Score SEO',    'value' => $page->seo_score],
            ['label' => 'Qualité',      'value' => $page->quality_score],
            ['label' => 'Topical',      'value' => $page->topical_score],
            ['label' => 'Indexabilité', 'value' => $page->indexability_score],
        ] as $score)
        <div class="text-center">
            @if($score['value'])
            @php $v = (float) $score['value']; @endphp
            @if($v >= 70)
            <div class="text-2xl font-black text-emerald-600">{{ number_format($v, 0) }}</div>
            @elseif($v >= 40)
            <div class="text-2xl font-black text-amber-600">{{ number_format($v, 0) }}</div>
            @else
            <div class="text-2xl font-black text-rose-500">{{ number_format($v, 0) }}</div>
            @endif
            @else
            <div class="text-2xl font-black text-gray-200">—</div>
            @endif
            <div class="text-xs font-semibold text-gray-400 mt-1">{{ $score['label'] }}</div>
        </div>
        @endforeach
    </div>
</div>

{{-- ═══════════════════════════════════════════
     HERO SECTION
════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl border border-gray-100 px-8 py-7 mb-5 anim-fade-up"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">

    {{-- Status badges row --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        @if($enginePublished)
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold bg-emerald-100 text-emerald-700">Published engine</span>
        @endif
        @if($livePublished)
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold bg-sky-100 text-sky-700">Live</span>
        @endif
        @if($pageIsHealthy)
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold bg-emerald-100 text-emerald-700">Healthy</span>
        @endif
        @if($pageIsApproved)
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold bg-emerald-100 text-emerald-700">Approved</span>
        @endif
        @if($page->is_indexed || ($latestMetric?->is_indexed ?? false))
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold bg-sky-100 text-sky-700">Indexée</span>
        @endif
        @if(filled($page->cluster))
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold bg-slate-100 text-slate-700">{{ Str::upper((string) $page->cluster) }}</span>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">

        {{-- Left column: image --}}
        <div class="xl:col-span-2">
            <h2 class="text-2xl font-black text-gray-900 leading-tight mb-2">{{ $page->title ?: $page->keyword }}</h2>
            <p class="text-sm text-gray-400 font-mono mb-4">{{ $page->canonicalPath() }}</p>

            <div class="rounded-2xl overflow-hidden border border-gray-100 bg-slate-50 min-h-52 flex items-center justify-center"
                 style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                @if($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $page->image_alt ?: $page->keyword }}" class="w-full h-full object-cover">
                @else
                <div class="px-8 py-10 text-center">
                    <div class="mx-auto w-14 h-14 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-slate-300"
                         style="box-shadow:0 2px 8px rgba(0,0,0,0.04);">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2 1.586-1.586a2 2 0 012.828 0L20 14m-6-8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <p class="mt-4 text-sm font-semibold text-slate-500">Image en attente</p>
                    <p class="mt-1 text-xs text-slate-400">Visuel non encore généré pour cette page.</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Right column: info cards --}}
        <div class="xl:col-span-3 space-y-4">

            {{-- Score micro-cards --}}
            <div class="grid grid-cols-2 gap-3">
                @foreach($liveCards as $card)
                @php
                    $barCls = 'bg-rose-500';
                    if ($card['value'] >= 80) $barCls = 'bg-emerald-500';
                    elseif ($card['value'] >= 60) $barCls = 'bg-amber-500';
                    $barPct = max(4, min(100, $card['value']));
                @endphp
                <div class="rounded-2xl border border-gray-100 bg-white px-4 py-3.5"
                     style="box-shadow:0 1px 4px rgba(0,0,0,0.03);">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-semibold text-gray-500">{{ $card['label'] }}</span>
                        <span class="text-lg font-black text-gray-900">{{ $card['value'] }}</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $barCls }}" data-bar-pct="{{ $barPct }}"></div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Generation source --}}
            <div class="rounded-2xl border {{ $genBorder }} {{ $genBg }} px-5 py-4">
                <div class="text-xs font-bold uppercase tracking-widest {{ $genLabel }} mb-1.5">Source de génération</div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-base font-bold {{ $genTitle }}">{{ $page->generationSourceLabel() }}</span>
                    @if($page->generation_error)
                    <span class="inline-flex items-center rounded-full bg-white/80 px-2.5 py-0.5 text-xs font-bold {{ $genLabel }}">Erreur AI</span>
                    @else
                    <span class="inline-flex items-center rounded-full bg-white/80 px-2.5 py-0.5 text-xs font-bold {{ $genLabel }}">OK</span>
                    @endif
                </div>
                @if($genSource === 'fallback')
                <p class="mt-1.5 text-xs {{ $genText }}">L'article visible vient du preset de secours, pas d'une génération AI complète.</p>
                @elseif($genSource === 'hybrid')
                <p class="mt-1.5 text-xs {{ $genText }}">L'AI a répondu, mais le preset a complété une partie du payload.</p>
                @else
                <p class="mt-1.5 text-xs {{ $genText }}">Page générée par AI complète.</p>
                @endif

                @if($page->generation_error)
                <div class="mt-3 rounded-xl bg-white/70 border border-white/80 px-3 py-2.5">
                    <p class="text-xs font-semibold text-gray-800 mb-1">Dernière erreur AI</p>
                    <p class="text-xs text-gray-600">{{ $page->generation_error }}</p>
                    @if($page->generationMissingKeys() !== [])
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($page->generationMissingKeys() as $key)
                        <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">{{ $key }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- Workflow states --}}
            <div class="rounded-2xl border border-gray-100 bg-white px-5 py-4"
                 style="box-shadow:0 1px 4px rgba(0,0,0,0.03);">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3">Workflow éditorial</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($workflowStates as $item)
                    @if($item['active'])
                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>{{ $item['label'] }}
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold bg-gray-100 text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300 inline-block"></span>{{ $item['label'] }}
                    </span>
                    @endif
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-gray-500">
                    {{ $pendingSuggestions->count() }} suggestion(s) pending ·
                    image {{ $imageApproved ? 'validée' : 'à revoir' }} ·
                    indexation {{ ($page->is_indexed || ($latestMetric?->is_indexed ?? false)) ? 'observée' : 'à confirmer' }}
                </p>
            </div>

            {{-- Live publication --}}
            <div class="rounded-2xl border {{ $pubBorder }} {{ $pubBg }} px-5 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest {{ $pubLabel }} mb-1.5">Publication live</p>
                        @if($livePublished)
                        <p class="text-sm font-bold {{ $pubTitle }}">Publiée sur le site public</p>
                        <p class="mt-1 text-xs {{ $pubText }}">Sitemap et observed coverage actifs sur cette URL.</p>
                        @elseif($enginePublished)
                        <p class="text-sm font-bold {{ $pubTitle }}">Prête à être poussée en live</p>
                        <p class="mt-1 text-xs {{ $pubText }}">Il reste à créer la vraie URL publique sur le domaine.</p>
                        @else
                        <p class="text-sm font-bold {{ $pubTitle }}">Publication live indisponible</p>
                        <p class="mt-1 text-xs {{ $pubText }}">Validez d'abord la publication moteur.</p>
                        @endif
                    </div>
                    @if($livePublished)
                    <a href="{{ $liveUrl }}" target="_blank"
                       class="shrink-0 inline-flex items-center rounded-xl bg-sky-600 hover:bg-sky-700 px-4 py-2 text-xs font-bold text-white transition-colors">
                        Ouvrir live →
                    </a>
                    @elseif($enginePublished)
                    <form method="POST" action="{{ route('admin.pages.publish-live', [$site->site_id, $page->id]) }}">
                        @csrf
                        <button type="submit"
                            class="shrink-0 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-xs font-bold text-white transition-colors">
                            Publier en live
                        </button>
                    </form>
                    @endif
                </div>
                @if($livePublished)
                <p class="mt-3 text-xs {{ $pubText }}">URL live : <a href="{{ $liveUrl }}" target="_blank" class="font-semibold underline">{{ $liveUrl }}</a></p>
                @endif
            </div>

        </div>{{-- /right col --}}
    </div>{{-- /grid --}}
</div>{{-- /hero --}}

{{-- ═══════════════════════════════════════════
     PENDING SUGGESTION
════════════════════════════════════════════ --}}
@if($latestPendingSuggestion)
<div class="bg-white rounded-2xl border border-purple-100 px-7 py-6 mb-5 anim-fade-up"
     style="box-shadow:0 2px 12px rgba(99,102,241,0.06);">
    <div class="flex items-start justify-between gap-4 mb-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-purple-500 mb-1">Suggestion active</p>
            <h3 class="text-base font-bold text-purple-900">{{ $latestPendingSuggestion->source }}</h3>
            <p class="mt-1 text-sm text-purple-700">
                {{ collect(\Illuminate\Support\Arr::wrap($latestPendingSuggestion->suggestions_json['rationale'] ?? []))->take(2)->implode(' ') ?: 'Une suggestion éditoriale est prête à être revue.' }}
            </p>
        </div>
        <span class="shrink-0 inline-flex items-center rounded-full bg-purple-50 border border-purple-100 px-3 py-1 text-xs font-semibold text-purple-600">{{ $latestPendingSuggestion->created_at?->diffForHumans() }}</span>
    </div>

    @if(!empty($latestPendingSuggestion->suggestions_json['sections']))
    <div class="space-y-1.5 mb-4">
        @foreach(array_slice($latestPendingSuggestion->suggestions_json['sections'], 0, 4) as $section)
        <div class="flex items-start gap-2 text-sm text-purple-800">
            <span class="mt-0.5 text-purple-300">•</span>
            <span>{{ $section }}</span>
        </div>
        @endforeach
    </div>
    @endif

    @if(!empty($latestPendingTargetPlan))
    <div class="rounded-xl border border-rose-100 bg-rose-50/60 px-4 py-4 mb-3">
        <p class="text-xs font-bold uppercase tracking-widest text-rose-700 mb-2">Pourquoi ça baisse</p>
        <p class="text-sm text-gray-700 mb-3">{{ $latestPendingTargetSummary['sections'] }} section(s) faible(s) tirent la page vers le bas.</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach($latestPendingTargetSummary['top_reasons'] as $reason)
            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-rose-700">{{ $reason }}</span>
            @endforeach
            @foreach($latestPendingTargetSummary['phases'] as $phase)
            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-gray-600">phase {{ $phase }}</span>
            @endforeach
        </div>
    </div>
    <div class="rounded-xl border border-purple-100 bg-white/80 px-4 py-4 mb-4">
        <p class="text-xs font-bold uppercase tracking-widest text-purple-700 mb-3">Plan de patch ciblé</p>
        <div class="space-y-2.5">
            @foreach(array_slice($latestPendingTargetPlan, 0, 3) as $target)
            <div class="rounded-xl border border-purple-100 bg-purple-50/40 px-3 py-3">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="text-sm font-semibold text-gray-900">{{ $target['heading'] }}</span>
                    @if(!empty($target['phase']))
                    <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-600">phase {{ $target['phase'] }}</span>
                    @endif
                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">{{ $target['patch_intent'] }}</span>
                </div>
                @if(!empty($target['reasons']))
                <div class="flex flex-wrap gap-1.5">
                    @foreach($target['reasons'] as $reason)
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $reason }}</span>
                    @endforeach
                </div>
                @endif
                @if(!empty($target['instruction']))
                <p class="mt-1.5 text-xs text-gray-600">{{ $target['instruction'] }}</p>
                @endif
                <p class="mt-1 text-xs text-gray-400">{{ $target['replacement_mode'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('admin.pages.suggestions.apply', [$site->site_id, $page->id, $latestPendingSuggestion->id]) }}">
            @csrf
            <button type="submit"
                class="inline-flex items-center rounded-xl px-5 py-2.5 text-sm font-bold text-white transition-all hover:-translate-y-0.5"
                style="background:linear-gradient(135deg,#7c3aed,#6366f1);box-shadow:0 4px 14px rgba(99,102,241,0.3);">
                Appliquer à la page
            </button>
        </form>
        <a href="{{ route('admin.sites.autopilot', $site->site_id) }}"
           class="inline-flex items-center rounded-xl border border-purple-200 bg-white px-5 py-2.5 text-sm font-semibold text-purple-700 hover:bg-purple-50 transition-all">
            Voir toute la file
        </a>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════
     PUBLICATION BLOCKERS
════════════════════════════════════════════ --}}
@if($page->status !== 'published' && !empty($publicationSummary['failed_rules'] ?? []))
@php
    $failedRules = $publicationSummary['failed_rules'] ?? [];
    $blockerMap  = [
        'seo_score_below_threshold'    => ['label' => 'Score SEO insuffisant',       'detail' => 'Actuel : '.((int)($page->seo_score ?? 0)).'/100 — seuil : 70',           'type' => 'diagnostic', 'next' => 'Créez une suggestion éditoriale de rewrite pour traiter ce point.',   'color' => 'rose'],
        'indexability_below_threshold' => ['label' => 'Indexabilité insuffisante',   'detail' => 'Actuel : '.((int)($page->indexability_score ?? 0)).'/100 — seuil : 65',  'type' => 'diagnostic', 'next' => 'Traitez via revue éditoriale ou action CMS réelle.',                  'color' => 'rose'],
        'faq_count_below_minimum'      => ['label' => 'FAQ insuffisante',            'detail' => 'Actuel : '.count($page->faq_json ?? []).' question(s) — minimum : 5',   'type' => 'diagnostic', 'next' => 'Passez par une suggestion éditoriale pour enrichir la FAQ.',          'color' => 'amber'],
        'image_not_approved'           => ['label' => 'Image non approuvée',        'detail' => 'Statut actuel : '.($page->image_status ?? 'missing'),                    'type' => 'quickfix',   'action' => 'approve_image', 'btn' => "Approuver l'image",                       'color' => 'amber'],
        'status_not_pending_review'    => ['label' => 'Statut incorrect',           'detail' => 'Actuel : '.($page->status ?? 'draft').' — requis : review',              'type' => 'quickfix',   'action' => 'set_review',    'btn' => 'Passer en review',                        'color' => 'amber'],
        'forced_noindex'               => ['label' => 'Forced noindex activé',      'detail' => 'La page est forcée en noindex par override manuel.',                      'type' => 'quickfix',   'action' => 'clear_noindex', 'btn' => 'Retirer le forced noindex',               'color' => 'rose'],
        'duplicate_risk_high'          => ['label' => 'Risque de duplication élevé','detail' => 'Score : '.number_format((float)($page->duplicate_risk_score ?? 0)*100,0).'% — seuil max : 70%','type'=>'diagnostic','next'=>'Utilisez une suggestion éditoriale de rewrite.',    'color' => 'rose'],
        'spam_risk_high'               => ['label' => 'Risque spam détecté',        'detail' => 'Signaux spam détectés dans le contenu.',                                  'type' => 'diagnostic', 'next' => 'Revue manuelle recommandée.',                                          'color' => 'rose'],
    ];
@endphp

<div class="bg-white rounded-2xl border border-rose-200 mb-5 anim-fade-up overflow-hidden"
     style="box-shadow:0 2px 12px rgba(244,63,94,0.06);">
    <div class="px-7 py-4 border-b border-rose-100 flex items-center justify-between">
        <div>
            <h3 class="font-bold text-gray-900 text-sm">Diagnostic de publication moteur</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ count($failedRules) }} point(s) bloquants</p>
        </div>
        <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-700">
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
                ? 'Statut actuel : '.($page->image_status ?? 'generated').' — visuel existant, à valider.'
                : 'Aucun visuel généré pour cette page.';
            $def['action'] = $hasGeneratedImage ? 'approve_image' : 'generate_image';
            $def['btn']    = $hasGeneratedImage ? "Approuver l'image" : "Générer l'image IA";
        @endphp
        @endif
        <div class="px-7 py-4 flex items-center justify-between gap-6">
            <div class="flex items-start gap-3 min-w-0">
                @if($def['color'] === 'rose')
                <span class="w-2 h-2 rounded-full bg-rose-500 mt-1.5 shrink-0"></span>
                @else
                <span class="w-2 h-2 rounded-full bg-amber-400 mt-1.5 shrink-0"></span>
                @endif
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900">{{ $def['label'] }}</p>
                    @if($def['color'] === 'rose')
                    <p class="text-xs text-rose-600 mt-0.5">{{ $def['detail'] }}</p>
                    @else
                    <p class="text-xs text-amber-600 mt-0.5">{{ $def['detail'] }}</p>
                    @endif
                    @if(!empty($def['next']))
                    <p class="text-xs text-gray-400 mt-1">{{ $def['next'] }}</p>
                    @endif
                </div>
            </div>
            <div class="shrink-0">
                @if($def['type'] === 'quickfix')
                <form method="POST" action="{{ route('admin.pages.quick-fix', [$site->site_id, $page->id]) }}">
                    @csrf
                    <input type="hidden" name="action" value="{{ $def['action'] }}">
                    @if($def['color'] === 'rose')
                    <button type="submit" class="inline-flex items-center rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 px-3 py-1.5 text-xs font-semibold transition-colors">{{ $def['btn'] }}</button>
                    @else
                    <button type="submit" class="inline-flex items-center rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50 px-3 py-1.5 text-xs font-semibold transition-colors">{{ $def['btn'] }}</button>
                    @endif
                </form>
                @else
                <span class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-400">Diagnostic only</span>
                @endif
            </div>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════
     MAIN CONTENT + SIDEBAR
════════════════════════════════════════════ --}}
<div class="grid grid-cols-3 gap-5">

    {{-- ── MAIN COLUMN ── --}}
    <div class="col-span-2 space-y-4">

        {{-- Meta description --}}
        @if($page->meta_description)
        <div class="bg-white rounded-2xl border border-gray-100 px-6 py-5"
             style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
            <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Meta description</p>
            <p class="text-sm text-gray-700 leading-relaxed">{{ $page->meta_description }}</p>
        </div>
        @endif

        {{-- Content preview --}}
        @if($page->content)
        <div class="bg-white rounded-2xl border border-gray-100 px-6 py-5"
             style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
            <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-3">Contenu</p>
            <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap max-h-64 overflow-y-auto">{{ Str::limit($page->content, 2000) }}</div>
        </div>
        @endif

        {{-- Review issues --}}
        @if($page->review_issues_json)
        <div class="bg-white rounded-2xl border border-amber-100 px-6 py-5"
             style="box-shadow:0 2px 8px rgba(245,158,11,0.04);">
            <p class="text-xs font-bold uppercase tracking-widest text-amber-500 mb-3">Problèmes détectés</p>
            <div class="space-y-2">
                @foreach($page->review_issues_json as $issue)
                <div class="flex items-start gap-2 text-sm">
                    <span class="text-rose-400 mt-0.5">•</span>
                    <span class="text-gray-700">{{ is_array($issue) ? ($issue['message'] ?? json_encode($issue)) : $issue }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Analysis result --}}
        @if(session('analysis'))
        @php $analysis = session('analysis'); @endphp
        <div class="bg-white rounded-2xl border border-indigo-100 px-6 py-5"
             style="box-shadow:0 2px 8px rgba(99,102,241,0.05);">
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-500 mb-3">Résultat d'analyse</p>
            @if(!empty($analysis['status_report']))
            <div class="space-y-1.5">
                @foreach((array) $analysis['status_report'] as $key => $value)
                <div class="flex items-start gap-2 text-xs">
                    <span class="text-indigo-400 font-semibold min-w-32">{{ $key }}</span>
                    <span class="text-gray-700">{{ is_array($value) ? json_encode($value) : $value }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- Rewrite suggestion result --}}
        @if(session('rewrite_suggestion'))
        @php
            $suggestion = session('rewrite_suggestion');
            $rewriteTargetPlan    = $extractRewriteTargetPlan(is_array($suggestion) ? $suggestion : []);
            $rewriteTargetSummary = $rewriteTargetSummary($rewriteTargetPlan);
        @endphp
        <div class="bg-white rounded-2xl border border-purple-100 px-6 py-5"
             style="box-shadow:0 2px 8px rgba(139,92,246,0.05);">
            <div class="flex items-center justify-between mb-4">
                <p class="text-xs font-bold uppercase tracking-widest text-purple-500">Suggestion créée — en attente</p>
                @if(!empty($suggestion['id']))
                <form method="POST" action="{{ route('admin.pages.suggestions.apply', [$site->site_id, $page->id, $suggestion['id']]) }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center rounded-xl px-4 py-2 text-xs font-bold text-white transition-all hover:-translate-y-0.5"
                        style="background:linear-gradient(135deg,#7c3aed,#6366f1);">
                        Appliquer &amp; recalculer →
                    </button>
                </form>
                @endif
            </div>

            @if(!empty($suggestion['proposed_content']) || !empty($suggestion['content']))
            @php $previewContent = $suggestion['proposed_content'] ?? $suggestion['content']; @endphp
            <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 text-sm text-gray-700 whitespace-pre-wrap max-h-64 overflow-y-auto">{{ Str::limit(strip_tags((string) $previewContent), 2000) }}</div>
            @else
            <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 space-y-4 text-sm text-gray-700">
                @if(!empty($suggestion['title']) || !empty($suggestion['meta_description']))
                <div>
                    @if(!empty($suggestion['title']))
                    <p class="font-bold text-gray-900">{{ $suggestion['title'] }}</p>
                    @endif
                    @if(!empty($suggestion['meta_description']))
                    <p class="mt-1 text-sm text-gray-500">{{ $suggestion['meta_description'] }}</p>
                    @endif
                </div>
                @endif
                @if(!empty($suggestion['sections']))
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-purple-600 mb-2">Passes proposées</p>
                    <div class="space-y-1.5">
                        @foreach(array_slice($suggestion['sections'], 0, 6) as $section)
                        <div class="flex items-start gap-2">
                            <span class="text-purple-300 mt-0.5">•</span>
                            <span>{{ $section }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @if(!empty($suggestion['faq']))
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-purple-600 mb-2">FAQ suggérée</p>
                    <div class="space-y-2">
                        @foreach(array_slice($suggestion['faq'], 0, 3) as $faq)
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">{{ $faq['question'] ?? 'Question' }}</p>
                            <p class="text-gray-600 text-sm">{{ $faq['answer'] ?? '' }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @if(!empty($suggestion['rationale']))
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-purple-600 mb-2">Pourquoi</p>
                    <div class="space-y-1.5">
                        @foreach(array_slice($suggestion['rationale'], 0, 4) as $item)
                        <div class="flex items-start gap-2">
                            <span class="text-purple-300 mt-0.5">→</span>
                            <span>{{ $item }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endif

            @if(!empty($rewriteTargetPlan))
            <div class="mt-4 rounded-xl border border-rose-100 bg-rose-50/60 px-4 py-4">
                <p class="text-xs font-bold uppercase tracking-widest text-rose-700 mb-2">Pourquoi ça baisse</p>
                <p class="text-sm text-gray-700 mb-3">{{ $rewriteTargetSummary['sections'] }} section(s) faible(s).</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($rewriteTargetSummary['top_reasons'] as $reason)
                    <span class="inline-flex items-center rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-rose-700">{{ $reason }}</span>
                    @endforeach
                    @foreach($rewriteTargetSummary['phases'] as $phase)
                    <span class="inline-flex items-center rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-gray-600">phase {{ $phase }}</span>
                    @endforeach
                </div>
            </div>
            <div class="mt-3 rounded-xl border border-purple-100 bg-white/80 px-4 py-4">
                <p class="text-xs font-bold uppercase tracking-widest text-purple-700 mb-3">Plan de patch ciblé</p>
                <div class="space-y-2">
                    @foreach(array_slice($rewriteTargetPlan, 0, 3) as $target)
                    <div class="rounded-xl border border-purple-100 bg-purple-50/40 px-3 py-3">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-gray-900">{{ $target['heading'] }}</span>
                            @if(!empty($target['phase']))
                            <span class="inline-flex items-center rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-600">phase {{ $target['phase'] }}</span>
                            @endif
                            <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">{{ $target['patch_intent'] }}</span>
                        </div>
                        @if(!empty($target['reasons']))
                        <div class="flex flex-wrap gap-1.5 mb-1">
                            @foreach($target['reasons'] as $reason)
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{{ $reason }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if(!empty($target['instruction']))
                        <p class="text-xs text-gray-600">{{ $target['instruction'] }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-gray-400">{{ $target['replacement_mode'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

    </div>{{-- /main col --}}

    {{-- ── SIDEBAR ── --}}
    <div class="space-y-4">

        {{-- Rewrite form --}}
        <div class="bg-white rounded-2xl border border-gray-100 px-6 py-5"
             style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
            <h3 class="font-bold text-gray-900 text-sm mb-1">Suggestion éditoriale</h3>
            <p class="text-xs text-gray-400 mb-4 leading-relaxed">Crée une suggestion de rewrite sur SeoPage. Ne corrige pas magiquement le site observé ni le CMS.</p>

            @if(($observedRewriteContext['matched'] ?? false) === true)
            <div class="mb-4 rounded-xl border {{ $rwBorder }} {{ $rwBg }} px-3 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest {{ $rwText }}">Contexte observed</p>
                        <p class="text-sm mt-1 {{ $rwText }}">{{ ucfirst($rwState) }} · score {{ $observedRewriteContext['health']['health_score'] ?? '—' }}</p>
                    </div>
                    <div class="text-xs text-right {{ $rwText }}">
                        <div>{{ count($observedRewriteContext['flags'] ?? []) }} flag(s)</div>
                        <div>{{ count($observedRewriteContext['recommendations'] ?? []) }} reco(s)</div>
                    </div>
                </div>
                @if(!empty($observedRewriteContext['flags']))
                <div class="mt-2.5 flex flex-wrap gap-1.5">
                    @foreach(($observedRewriteContext['flags'] ?? []) as $flag)
                    <span class="inline-flex items-center rounded-full bg-white/80 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ $flag }}</span>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            <form method="POST" action="{{ route('admin.pages.rewrite', [$site->site_id, $page->id]) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Mode</label>
                    <select name="mode"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent bg-white">
                        <option value="enrich">Enrichir</option>
                        <option value="rewrite">Réécriture complète</option>
                        <option value="de-duplicate">Dédupliquer</option>
                        <option value="improve-ctr">Améliorer le CTR</option>
                        <option value="improve-indexability">Améliorer l'indexation</option>
                    </select>
                </div>
                <button type="submit"
                    class="w-full font-bold text-white rounded-xl px-4 py-2.5 text-sm transition-all hover:-translate-y-0.5"
                    style="background:linear-gradient(135deg,#7c3aed,#6366f1);box-shadow:0 4px 14px rgba(99,102,241,0.3);">
                    Créer suggestion
                </button>
            </form>
        </div>

        {{-- Observed runtime recommendations --}}
        @if(($observedRewriteContext['matched'] ?? false) === true && (!empty($observedRewriteContext['recommendations']) || !empty($observedRewriteContext['sections'])))
        <div class="bg-white rounded-2xl border border-gray-100 px-6 py-5"
             style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
            <h3 class="font-bold text-gray-900 text-sm mb-1">Observed runtime</h3>
            <p class="text-xs text-gray-400 mb-4">Réalité observée du site, distincte du workflow éditorial interne.</p>

            @if(!empty($observedRewriteContext['sections']))
            <div class="space-y-1.5 mb-4">
                @foreach(array_slice($observedRewriteContext['sections'], 0, 3) as $section)
                <div class="flex items-start gap-2 text-sm text-gray-700">
                    <span class="text-purple-400 mt-0.5">•</span>
                    <span>{{ $section }}</span>
                </div>
                @endforeach
            </div>
            @endif

            @if(!empty($observedRewriteContext['recommendations']))
            <div class="space-y-2">
                @foreach(array_slice($observedRewriteContext['recommendations'], 0, 3) as $item)
                <div class="rounded-xl border border-gray-100 px-3 py-3">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <span class="text-sm font-semibold text-gray-900">{{ $item['title'] }}</span>
                        <span class="shrink-0 text-xs rounded-full bg-gray-100 px-2 py-0.5 text-gray-500">P{{ $item['priority'] }}</span>
                    </div>
                    @if(!empty($item['suggested_action']))
                    <p class="text-xs text-gray-500">{{ $item['suggested_action'] }}</p>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- Page info / states --}}
        <div class="bg-white rounded-2xl border border-gray-100 px-6 py-5 text-xs"
             style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
            <p class="text-xs font-bold uppercase tracking-widest text-gray-400 pb-3 mb-3 border-b border-gray-100">Vérités et états</p>
            <div class="space-y-2.5 text-gray-500">
                <div class="flex justify-between">
                    <span>Site</span>
                    <span class="font-semibold text-gray-700">{{ $site->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Source éditoriale</span>
                    <span class="font-semibold text-gray-700">SeoPage</span>
                </div>
                <div class="flex justify-between">
                    <span>Source observée</span>
                    <span class="font-semibold text-gray-700">{{ ($observedRewriteContext['matched'] ?? false) ? 'SeoSitePage liée' : 'Aucune' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Génération</span>
                    <span class="font-semibold text-gray-700">{{ $page->generationSourceLabel() }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Moteur publié</span>
                    <span class="font-semibold text-gray-700">{{ $enginePublished ? 'Oui' : 'Non' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Live publié</span>
                    <span class="font-semibold text-gray-700">{{ $livePublished ? 'Oui' : 'Non' }}</span>
                </div>
                @if($page->live_url)
                <div class="flex justify-between gap-4">
                    <span>Live URL</span>
                    <a href="{{ $page->live_url }}" target="_blank" class="font-semibold text-indigo-600 underline text-right break-all">{{ $page->live_url }}</a>
                </div>
                @endif
                @if($page->generationMissingKeys() !== [])
                <div>
                    <p class="font-semibold text-gray-700 mb-1">Clés manquantes</p>
                    <p>{{ implode(', ', $page->generationMissingKeys()) }}</p>
                </div>
                @endif
                <div class="flex justify-between">
                    <span>Règle matching</span>
                    <span class="font-semibold text-gray-700">{{ $page->observed_page_match_rule ?: 'Aucune' }}</span>
                </div>
                @if($page->cluster)
                <div class="flex justify-between">
                    <span>Cluster</span>
                    <span class="font-semibold text-gray-700">{{ $page->cluster }}</span>
                </div>
                @endif
                @if($page->duplicate_risk_score)
                <div class="flex justify-between">
                    <span>Risque doublon</span>
                    <span class="{{ $dupCls }}">{{ number_format((float) $page->duplicate_risk_score * 100, 0) }}%</span>
                </div>
                @endif
                <div class="flex justify-between">
                    <span>Créé</span>
                    <span class="text-gray-700">{{ $page->created_at?->format('d/m/Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Modifié</span>
                    <span class="text-gray-700">{{ $page->updated_at?->diffForHumans() }}</span>
                </div>
                @if($page->published_at)
                <div class="flex justify-between">
                    <span>Publié moteur</span>
                    <span class="text-gray-700">{{ $page->published_at?->format('d/m/Y') }}</span>
                </div>
                @endif
                @if($page->published_live_at)
                <div class="flex justify-between">
                    <span>Publié live</span>
                    <span class="text-gray-700">{{ $page->published_live_at?->format('d/m/Y H:i') }}</span>
                </div>
                @endif
                @if($page->last_observed_at)
                <div class="flex justify-between">
                    <span>Observé</span>
                    <span class="text-gray-700">{{ $page->last_observed_at?->format('d/m/Y H:i') }}</span>
                </div>
                @endif
            </div>
        </div>

        <a href="{{ route('admin.sites.show', $site->site_id) }}"
           class="block text-center text-sm font-semibold text-gray-400 hover:text-gray-700 py-2 transition-colors">
            ← Retour aux pages
        </a>
    </div>{{-- /sidebar --}}

</div>{{-- /grid --}}
</div>{{-- /admin-page-shell --}}
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-bar-pct]').forEach(function (el) {
    var pct = el.getAttribute('data-bar-pct');
    setTimeout(function () { el.style.width = pct + '%'; }, 200);
});
</script>
@endpush
