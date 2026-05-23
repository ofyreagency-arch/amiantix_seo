@extends('admin.layout')
@section('title', 'Autopilot — '.$site->name)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700 transition-colors">Sites</a>
    <span class="mx-2 text-gray-300">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700 transition-colors">{{ $site->name }}</a>
    <span class="mx-2 text-gray-300">›</span>
    <span class="font-semibold text-gray-900">Autopilot</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

@php
$obsHealthCls = $observedStats['health_score'] >= 70
    ? 'text-emerald-600'
    : ($observedStats['health_score'] >= 50 ? 'text-amber-600' : 'text-rose-600');
@endphp

{{-- ═══ OBSERVED LAYER + ALERTS ═══ --}}
<div class="grid grid-cols-1 xl:grid-cols-[1.1fr_0.9fr] gap-6 mb-6 anim-fade-up">

    {{-- Observed stats --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <h2 class="font-bold text-gray-900">Autopilot observed</h2>
                <p class="text-xs text-gray-400 mt-0.5">Pages observées fragiles remontées avant la couche action historique.</p>
            </div>
            <div class="text-right shrink-0">
                <div class="text-2xl font-black {{ $obsHealthCls }}">{{ $observedStats['health_score'] }}</div>
                <div class="text-xs text-gray-400">health observed</div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-px" style="background:#f3f4f6;">
            @foreach([
                ['label' => 'Observées',       'value' => $observedStats['observed_pages'], 'cls' => 'text-gray-900'],
                ['label' => 'Healthy',          'value' => $observedStats['healthy'],        'cls' => 'text-emerald-600'],
                ['label' => 'Warning',          'value' => $observedStats['warning'],        'cls' => 'text-amber-600'],
                ['label' => 'Critical',         'value' => $observedStats['critical'],       'cls' => 'text-rose-600'],
                ['label' => 'Recommandations',  'value' => $observedStats['recommendations'],'cls' => 'text-indigo-600'],
                ['label' => 'Suggestions legacy','value' => $stats['pending'],               'cls' => 'text-purple-600'],
            ] as $item)
            <div class="bg-white px-5 py-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">{{ $item['label'] }}</div>
                <div class="text-xl font-black {{ $item['cls'] }}">{{ $item['value'] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Observed alerts --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-900">Pages observed sous tension</h2>
            <p class="text-xs text-gray-400 mt-0.5">Ce que le runtime pousserait dans le backlog avant toute réécriture manuelle.</p>
        </div>

        <div class="divide-y divide-gray-50 max-h-[340px] overflow-y-auto">
            @forelse($observedAlerts as $alert)
            @php
                $isCritical = ($alert['state'] ?? 'warning') === 'critical';
                $alertBadge = $isCritical
                    ? 'bg-rose-50 text-rose-700 border-rose-100'
                    : 'bg-amber-50 text-amber-700 border-amber-100';
            @endphp
            <div class="px-5 py-4 hover:bg-gray-50/60 transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 truncate">{{ $alert['title'] ?: $alert['path'] }}</div>
                        <div class="mt-0.5 text-xs text-gray-400 truncate">{{ $alert['cluster_label'] ?: 'cluster inconnu' }} · {{ $alert['path'] }}</div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold border {{ $alertBadge }} shrink-0">
                        {{ $alert['state'] }}
                    </span>
                </div>
                <div class="mt-2 flex gap-3 text-xs text-gray-400">
                    <span>P{{ (int)($alert['priority'] ?? 0) }}</span>
                    <span>·</span>
                    <span>santé {{ (int)($alert['health_score'] ?? 0) }}</span>
                    <span>·</span>
                    <span class="truncate max-w-[200px]">{{ collect($alert['flags'] ?? [])->take(2)->implode(' · ') ?: 'aucun flag' }}</span>
                </div>
            </div>
            @empty
            <div class="px-6 py-12 text-center">
                <div class="w-10 h-10 bg-emerald-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="text-sm font-semibold text-gray-400">Aucune page sous tension</div>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ═══ KPI STATS ═══ --}}
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6 anim-fade-up delay-50">
    @foreach([
        ['label' => 'En attente',       'value' => $stats['pending'],         'cls' => 'text-amber-700',   'bg' => 'bg-amber-50',   'border' => 'border-amber-100',   'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Appliquées',       'value' => $stats['applied'],         'cls' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'border' => 'border-emerald-100', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Rejetées',         'value' => $stats['rejected'],        'cls' => 'text-gray-600',    'bg' => 'bg-gray-50',    'border' => 'border-gray-200',    'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Sections ciblées', 'value' => $stats['rewrite_targets'], 'cls' => 'text-indigo-700',  'bg' => 'bg-indigo-50',  'border' => 'border-indigo-100',  'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
    ] as $stat)
    <div class="bg-white rounded-2xl border border-gray-100 px-6 py-5"
         style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
        <div class="flex items-center justify-between mb-3">
            <div class="w-9 h-9 {{ $stat['bg'] }} border {{ $stat['border'] }} rounded-xl flex items-center justify-center">
                <svg class="w-4.5 h-4.5 {{ $stat['cls'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/>
                </svg>
            </div>
        </div>
        <div class="text-2xl font-black {{ $stat['cls'] }}">{{ $stat['value'] }}</div>
        <div class="text-xs font-semibold text-gray-400 mt-0.5">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- ═══ OBSERVED BACKLOG ═══ --}}
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6 anim-fade-up delay-100"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-900">Backlog observed</h2>
        <p class="text-xs text-gray-400 mt-0.5">Recommandations calculées depuis la couche observée, indépendantes des suggestions legacy.</p>
    </div>

    <div class="divide-y divide-gray-50">
        @forelse($observedRecommendations as $recommendation)
        <div class="px-6 py-4 hover:bg-gray-50/40 transition-colors">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-gray-900">{{ $recommendation->title }}</div>
                    <div class="mt-0.5 text-xs text-gray-400">
                        {{ $recommendation->type }}
                        @if($recommendation->cluster) · {{ $recommendation->cluster }} @endif
                    </div>
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700 shrink-0">
                    P{{ $recommendation->priority }}
                </span>
            </div>
            @if($recommendation->reasoning)
            <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ $recommendation->reasoning }}</p>
            @endif
        </div>
        @empty
        <div class="px-6 py-12 text-center">
            <div class="w-10 h-10 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-5 h-5 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div class="text-sm font-semibold text-gray-400">Aucune recommandation observed pending.</div>
        </div>
        @endforelse
    </div>
</div>

{{-- ═══ LEGACY PENDING SUGGESTIONS ═══ --}}
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-150"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-4">
        <div>
            <h2 class="font-bold text-gray-900">Suggestions legacy en attente</h2>
            <p class="text-xs text-gray-400 mt-0.5">File d'action historique générée par le moteur rewrite / feedback loop.</p>
        </div>
        <form method="POST" action="{{ route('admin.pages.autopilot', $site->site_id) }}">
            @csrf
            <button type="submit"
                    class="text-xs font-bold text-white px-3 py-2 rounded-xl transition-all hover:-translate-y-0.5 flex items-center gap-1.5 shrink-0"
                    style="background:linear-gradient(135deg,#7c3aed,#6d28d9);box-shadow:0 2px 8px rgba(124,58,237,0.3);">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Relancer l'autopilot
            </button>
        </form>
    </div>

    @forelse($pending as $suggestion)
    <div class="px-6 py-5 border-b border-gray-50 last:border-0 hover:bg-gray-50/40 transition-colors">
        <div class="flex items-start gap-4">
            <div class="flex-1 min-w-0">

                {{-- Header --}}
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="font-bold text-gray-900">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</span>
                    <span class="text-xs text-gray-400 font-mono">{{ $suggestion->page?->slug }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                        {{ $suggestion->source }}
                    </span>
                </div>

                {{-- Signals --}}
                @if(!empty($suggestion->signals_json))
                <div class="flex flex-wrap gap-1.5 mb-3">
                    @foreach(array_slice($suggestion->signals_json, 0, 4) as $signal)
                    <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-lg font-medium">
                        {{ is_array($signal) ? ($signal['type'] ?? json_encode($signal)) : $signal }}
                    </span>
                    @endforeach
                </div>
                @endif

                {{-- Suggestion content --}}
                @if(!empty($suggestion->suggestions_json))
                @php
                    $payload          = is_array($suggestion->suggestions_json) ? $suggestion->suggestions_json : [];
                    $sections         = array_slice(\Illuminate\Support\Arr::wrap($payload['sections']  ?? []), 0, 3);
                    $rationale        = array_slice(\Illuminate\Support\Arr::wrap($payload['rationale'] ?? []), 0, 2);
                    $faq              = array_slice(\Illuminate\Support\Arr::wrap($payload['faq']       ?? []), 0, 1);
                    $rewritePlan      = array_slice(\Illuminate\Support\Arr::wrap($suggestion->dashboard_rewrite_target_plan ?? []), 0, 3);
                    $hasPayload       = !empty($sections) || !empty($rationale) || !empty($faq) || !empty($rewritePlan);
                @endphp
                <div class="space-y-1.5">
                    @foreach($sections as $item)
                    <div class="text-sm text-gray-700 flex items-start gap-2">
                        <span class="text-indigo-400 mt-0.5 shrink-0">→</span>
                        <span>{{ $item }}</span>
                    </div>
                    @endforeach
                    @foreach($rationale as $item)
                    <div class="text-xs text-gray-500 flex items-start gap-2">
                        <span class="text-purple-300 mt-0.5 shrink-0">•</span>
                        <span>{{ $item }}</span>
                    </div>
                    @endforeach
                    @foreach($faq as $item)
                    <div class="text-xs text-gray-500 flex items-start gap-2">
                        <span class="text-emerald-400 mt-0.5 shrink-0">?</span>
                        <span>{{ $item['question'] ?? 'Question suggérée' }}</span>
                    </div>
                    @endforeach

                    @if(!empty($rewritePlan))
                    <div class="mt-3 rounded-xl border border-indigo-100 bg-indigo-50/60 p-3">
                        <div class="text-xs font-bold uppercase tracking-wider text-indigo-700 mb-2">Plan de patch ciblé</div>
                        <div class="space-y-2">
                            @foreach($rewritePlan as $target)
                            <div class="rounded-lg bg-white/80 px-3 py-2 border border-indigo-100">
                                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                    <span class="text-sm font-semibold text-gray-900">{{ $target['heading'] }}</span>
                                    @if(!empty($target['phase']))
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                                        phase {{ $target['phase'] }}
                                    </span>
                                    @endif
                                    @if(!empty($target['patch_intent']))
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                                        {{ $target['patch_intent'] }}
                                    </span>
                                    @endif
                                </div>
                                @if(!empty($target['reasons']))
                                <div class="flex flex-wrap gap-1.5 mb-1">
                                    @foreach($target['reasons'] as $reason)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                        {{ $reason }}
                                    </span>
                                    @endforeach
                                </div>
                                @endif
                                @if(!empty($target['instruction']))
                                <p class="text-xs text-gray-600">{{ $target['instruction'] }}</p>
                                @endif
                                @if(!empty($target['replacement_mode']))
                                <div class="text-xs text-gray-400 mt-0.5">{{ $target['replacement_mode'] }}</div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if(!$hasPayload)
                    <p class="text-sm text-gray-400 italic">Payload éditorial peu exploitable. Revoir le rewrite ou son fallback.</p>
                    @endif
                </div>
                @endif

                <div class="text-xs text-gray-300 mt-3">{{ $suggestion->created_at?->diffForHumans() }}</div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col gap-2 shrink-0">
                <form method="POST" action="{{ route('admin.sites.suggestions.approve', [$site->site_id, $suggestion->id]) }}">
                    @csrf
                    <button type="submit"
                            class="w-full px-4 py-1.5 rounded-lg text-xs font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition-colors">
                        ✓ Approuver
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.sites.suggestions.reject', [$site->site_id, $suggestion->id]) }}">
                    @csrf
                    <button type="submit"
                            class="w-full px-4 py-1.5 rounded-lg text-xs font-bold text-gray-500 border border-gray-200 hover:border-gray-300 hover:text-gray-700 transition-colors">
                        Rejeter
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="px-6 py-16 text-center">
        <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <div class="text-base font-bold text-gray-500">Aucune suggestion en attente</div>
        <div class="text-sm text-gray-400 mt-1">L'autopilot générera de nouvelles suggestions automatiquement.</div>
    </div>
    @endforelse

    @if($pending->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $pending->links() }}
    </div>
    @endif
</div>

@endsection
