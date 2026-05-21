@extends('admin.layout')
@section('title', 'Autopilot — '.$site->name)
@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700">Sites</a>
    <span class="mx-2">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700">{{ $site->name }}</a>
    <span class="mx-2">›</span>
    <span class="font-medium text-gray-900">Autopilot</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

<div class="grid grid-cols-1 xl:grid-cols-[1.1fr_0.9fr] gap-6 mb-6">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-gray-900">Autopilot observed</h2>
                <div class="mt-1 text-xs text-gray-500">Le runtime remonte maintenant les pages observées fragiles avant même la couche action historique.</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-semibold {{ $observedStats['health_score'] >= 70 ? 'text-emerald-600' : ($observedStats['health_score'] >= 50 ? 'text-amber-600' : 'text-rose-600') }}">
                    {{ $observedStats['health_score'] }}
                </div>
                <div class="text-xs text-gray-500">health observed</div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-px bg-gray-100">
            @foreach([
                ['label' => 'Observées', 'value' => $observedStats['observed_pages']],
                ['label' => 'Healthy', 'value' => $observedStats['healthy']],
                ['label' => 'Warning', 'value' => $observedStats['warning']],
                ['label' => 'Critical', 'value' => $observedStats['critical']],
                ['label' => 'Recommandations', 'value' => $observedStats['recommendations']],
                ['label' => 'Suggestions legacy', 'value' => $stats['pending']],
            ] as $item)
            <div class="bg-white px-5 py-4">
                <div class="text-xs uppercase tracking-wider text-gray-400">{{ $item['label'] }}</div>
                <div class="mt-2 text-xl font-semibold text-gray-900">{{ $item['value'] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Pages observed sous tension</h2>
            <div class="mt-1 text-xs text-gray-500">Ce que le runtime pousserait naturellement dans le backlog avant toute réécriture manuelle.</div>
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
                    <span>{{ collect($alert['flags'] ?? [])->take(2)->implode(' · ') ?: 'aucun flag' }}</span>
                </div>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-sm text-gray-400">
                Aucune page observed sous tension pour ce site.
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Stats --}}
<div class="grid grid-cols-3 gap-5 mb-6">
    @foreach([
        ['label' => 'En attente',  'value' => $stats['pending'],  'color' => 'text-amber-600',  'bg' => 'bg-amber-50'],
        ['label' => 'Appliquées', 'value' => $stats['applied'],  'color' => 'text-green-600',  'bg' => 'bg-green-50'],
        ['label' => 'Rejetées',   'value' => $stats['rejected'], 'color' => 'text-gray-500',   'bg' => 'bg-gray-50'],
    ] as $stat)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-5 flex items-center gap-4">
        <div class="w-12 h-12 {{ $stat['bg'] }} rounded-xl flex items-center justify-center">
            <span class="text-2xl font-bold {{ $stat['color'] }}">{{ $stat['value'] }}</span>
        </div>
        <span class="text-sm font-medium text-gray-600">{{ $stat['label'] }}</span>
    </div>
    @endforeach
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Backlog observed</h2>
        <div class="mt-1 text-xs text-gray-500">Recommandations calculées depuis la couche observée, indépendantes des suggestions legacy.</div>
    </div>

    <div class="divide-y divide-gray-50">
        @forelse($observedRecommendations as $recommendation)
        <div class="px-6 py-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ $recommendation->title }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ $recommendation->type }} @if($recommendation->cluster) · {{ $recommendation->cluster }} @endif</div>
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                    P{{ $recommendation->priority }}
                </span>
            </div>
            <div class="mt-2 text-sm text-gray-600">{{ $recommendation->reasoning }}</div>
        </div>
        @empty
        <div class="px-6 py-8 text-center text-sm text-gray-400">
            Aucune recommandation observed pending pour ce site.
        </div>
        @endforelse
    </div>
</div>

{{-- Pending suggestions --}}
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-gray-900">Suggestions legacy en attente</h2>
            <div class="mt-1 text-xs text-gray-500">File d’action historique générée par le moteur rewrite / feedback loop.</div>
        </div>
        <form method="POST" action="{{ route('admin.pages.autopilot', $site->site_id) }}">
            @csrf
            <button type="submit"
                class="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                Relancer l'autopilot
            </button>
        </form>
    </div>

    @forelse($pending as $suggestion)
    <div class="px-6 py-5 border-b border-gray-50 last:border-0">
        <div class="flex items-start gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                    <span class="font-medium text-gray-900">{{ $suggestion->page?->keyword ?? 'Page inconnue' }}</span>
                    <span class="text-xs text-gray-400 font-mono">{{ $suggestion->page?->slug }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                        {{ $suggestion->source }}
                    </span>
                </div>

                @if(!empty($suggestion->signals_json))
                <div class="flex flex-wrap gap-1.5 mb-2">
                    @foreach(array_slice($suggestion->signals_json, 0, 4) as $signal)
                    <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-md">
                        {{ is_array($signal) ? ($signal['type'] ?? json_encode($signal)) : $signal }}
                    </span>
                    @endforeach
                </div>
                @endif

                @if(!empty($suggestion->suggestions_json))
                <div class="space-y-1">
                    @foreach(array_slice($suggestion->suggestions_json, 0, 2) as $item)
                    <div class="text-sm text-gray-600 flex items-start gap-2">
                        <span class="text-indigo-400 mt-0.5">→</span>
                        <span>{{ is_array($item) ? ($item['action'] ?? json_encode($item)) : $item }}</span>
                    </div>
                    @endforeach
                </div>
                @endif

                <div class="text-xs text-gray-400 mt-2">{{ $suggestion->created_at?->diffForHumans() }}</div>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
                <form method="POST" action="{{ route('admin.sites.suggestions.approve', [$site->site_id, $suggestion->id]) }}">
                    @csrf
                    <button type="submit"
                        class="px-4 py-1.5 bg-green-500 hover:bg-green-600 text-white text-xs font-medium rounded-lg transition-colors">
                        ✓ Approuver
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.sites.suggestions.reject', [$site->site_id, $suggestion->id]) }}">
                    @csrf
                    <button type="submit"
                        class="px-4 py-1.5 border border-gray-200 text-gray-500 hover:text-gray-700 text-xs font-medium rounded-lg transition-colors">
                        Rejeter
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="px-6 py-12 text-center text-gray-400">
        <div class="text-4xl mb-3">✓</div>
        <div class="font-medium text-gray-500">Aucune suggestion en attente</div>
        <div class="text-sm mt-1">L'autopilot générera de nouvelles suggestions automatiquement.</div>
    </div>
    @endforelse

    @if($pending->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">{{ $pending->links() }}</div>
    @endif
</div>

@endsection
