@extends('admin.layout')
@section('title', 'Santé — '.$site->name)
@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700">Sites</a>
    <span class="mx-2">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700">{{ $site->name }}</a>
    <span class="mx-2">›</span>
    <span class="font-medium text-gray-900">Santé</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

<div class="grid grid-cols-4 gap-6 mb-6">
    {{-- Health gauge --}}
    <div class="col-span-1 bg-white rounded-2xl border border-gray-100 shadow-sm flex flex-col items-center justify-center py-8">
        @php $circumference = 2 * M_PI * 54; $offset = $circumference * (1 - $health['score'] / 100); @endphp
        <svg class="w-36 h-36 -rotate-90" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="54" stroke="#f3f4f6" stroke-width="12" fill="none"/>
            <circle cx="60" cy="60" r="54"
                stroke="{{ $health['color'] }}"
                stroke-width="12"
                fill="none"
                stroke-linecap="round"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $offset }}"
                style="transition: stroke-dashoffset 1s ease"/>
        </svg>
        <div class="-mt-28 mb-16 text-center">
            <div class="text-4xl font-black text-gray-900">{{ $health['score'] }}</div>
            <div class="text-2xl font-bold" style="color: {{ $health['color'] }}">{{ $health['grade'] }}</div>
        </div>
        <div class="text-sm font-medium text-gray-500">Score de santé</div>
    </div>

    {{-- Breakdown --}}
    <div class="col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-5">
        <h3 class="font-semibold text-gray-900 mb-4">Détail du score</h3>
        @foreach([
            ['label' => 'Score SEO',         'value' => $health['breakdown']['seo'],           'color' => 'bg-blue-500'],
            ['label' => 'Qualité',           'value' => $health['breakdown']['quality'],        'color' => 'bg-purple-500'],
            ['label' => 'Topical',           'value' => $health['breakdown']['topical'],        'color' => 'bg-indigo-500'],
            ['label' => 'Indexabilité',      'value' => $health['breakdown']['indexability'],   'color' => 'bg-cyan-500'],
            ['label' => 'Pages publiées',    'value' => $health['breakdown']['published_pct'],  'color' => 'bg-green-500'],
        ] as $metric)
        <div class="mb-3">
            <div class="flex justify-between text-xs text-gray-600 mb-1">
                <span>{{ $metric['label'] }}</span>
                <span class="font-semibold">{{ $metric['value'] }}%</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="{{ $metric['color'] }} h-2 rounded-full transition-all"
                     style="width: {{ $metric['value'] }}%"></div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Page stats --}}
    <div class="col-span-1 bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-5 space-y-4">
        <h3 class="font-semibold text-gray-900">Pages</h3>
        @foreach([
            ['label' => 'Total',     'value' => $health['total_pages'], 'color' => 'text-gray-900'],
            ['label' => 'Publiées',  'value' => $health['published'],   'color' => 'text-green-600'],
            ['label' => 'Brouillons','value' => $health['draft'],       'color' => 'text-gray-500'],
            ['label' => 'Erreurs',   'value' => $health['errors'],      'color' => 'text-red-500'],
        ] as $stat)
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500">{{ $stat['label'] }}</span>
            <span class="text-2xl font-bold {{ $stat['color'] }}">{{ $stat['value'] }}</span>
        </div>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-2 gap-6">
    {{-- Score evolution chart --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-5">
        <h3 class="font-semibold text-gray-900 mb-4">Évolution santé (30j)</h3>
        @if(count($history) > 1)
            <canvas id="healthChart" height="200"></canvas>
        @else
            <div class="flex items-center justify-center h-48 text-gray-400 text-sm">
                Pas encore d'historique — revenez demain après le premier snapshot automatique.
            </div>
        @endif
    </div>

    {{-- Score distribution --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-5">
        <h3 class="font-semibold text-gray-900 mb-4">Distribution des scores SEO</h3>
        <canvas id="distChart" height="200"></canvas>
    </div>
</div>

{{-- Clusters --}}
@if(!empty($health['clusters']))
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-5 mt-6">
    <h3 class="font-semibold text-gray-900 mb-4">Clusters de contenu</h3>
    <div class="flex flex-wrap gap-3">
        @foreach($health['clusters'] as $cluster => $count)
        <div class="flex items-center gap-2 bg-indigo-50 text-indigo-700 rounded-lg px-4 py-2">
            <span class="font-medium text-sm">{{ $cluster ?: 'général' }}</span>
            <span class="bg-indigo-200 text-indigo-800 rounded-full px-2 py-0.5 text-xs font-bold">{{ $count }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
@if(count($history) > 1)
new Chart(document.getElementById('healthChart'), {
    type: 'line',
    data: {
        labels: {!! json_encode(array_column($history, 'snapshot_date')) !!},
        datasets: [
            {
                label: 'Santé',
                data: {!! json_encode(array_column($history, 'health_score')) !!},
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,0.08)',
                tension: 0.4,
                fill: true,
                pointRadius: 3,
            },
            {
                label: 'SEO',
                data: {!! json_encode(array_column($history, 'avg_seo_score')) !!},
                borderColor: '#3b82f6',
                backgroundColor: 'transparent',
                tension: 0.4,
                pointRadius: 2,
                borderDash: [4, 4],
            }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { min: 0, max: 100 } } }
});
@endif

new Chart(document.getElementById('distChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode(array_keys($health['score_dist'])) !!},
        datasets: [{
            label: 'Pages',
            data: {!! json_encode(array_values($health['score_dist'])) !!},
            backgroundColor: ['#fca5a5','#fcd34d','#6ee7b7','#6ee7b7','#34d399'],
            borderRadius: 6,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>
@endpush
