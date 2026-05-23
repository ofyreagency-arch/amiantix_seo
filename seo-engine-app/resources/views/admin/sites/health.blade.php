@extends('admin.layout')
@section('title', 'Santé — '.$site->name)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700 transition-colors">Sites</a>
    <span class="mx-2 text-gray-300">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700 transition-colors">{{ $site->name }}</a>
    <span class="mx-2 text-gray-300">›</span>
    <span class="font-semibold text-gray-900">Santé</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

@php
    $circumference  = 2 * M_PI * 54;
    $dashOffset     = $circumference * (1 - $health['score'] / 100);
    $svgStroke      = $health['color'];
    $gradeTextCls   = match(true) {
        $health['score'] >= 80 => 'text-emerald-500',
        $health['score'] >= 60 => 'text-amber-500',
        $health['score'] >= 40 => 'text-orange-500',
        default                => 'text-red-500',
    };
@endphp

{{-- ═══ KPI ROW ═══ --}}
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6 anim-fade-up">

    {{-- Health gauge --}}
    <div class="col-span-2 md:col-span-1 bg-white rounded-2xl border border-gray-100 flex flex-col items-center justify-center py-8"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="relative">
            <svg class="w-36 h-36 -rotate-90" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="54" stroke="#f3f4f6" stroke-width="12" fill="none"/>
                <circle cx="60" cy="60" r="54"
                    stroke="{{ $svgStroke }}"
                    stroke-width="12"
                    fill="none"
                    stroke-linecap="round"
                    stroke-dasharray="{{ $circumference }}"
                    stroke-dashoffset="{{ $dashOffset }}"
                    style="transition:stroke-dashoffset 1.4s cubic-bezier(.23,1,.32,1)"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <div class="text-3xl font-black text-gray-900">{{ $health['score'] }}</div>
                <div class="text-xl font-black {{ $gradeTextCls }}">{{ $health['grade'] }}</div>
            </div>
        </div>
        <div class="mt-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Score de santé</div>
    </div>

    {{-- Score breakdown --}}
    <div class="col-span-2 md:col-span-2 bg-white rounded-2xl border border-gray-100 px-6 py-5"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <h3 class="font-bold text-gray-900 mb-4">Détail du score</h3>
        @foreach([
            ['label' => 'Score SEO',      'value' => $health['breakdown']['seo'],          'gradient' => 'from-blue-500 to-blue-400'],
            ['label' => 'Qualité',        'value' => $health['breakdown']['quality'],       'gradient' => 'from-purple-500 to-purple-400'],
            ['label' => 'Topical',        'value' => $health['breakdown']['topical'],       'gradient' => 'from-indigo-500 to-indigo-400'],
            ['label' => 'Indexabilité',   'value' => $health['breakdown']['indexability'],  'gradient' => 'from-cyan-500 to-cyan-400'],
            ['label' => 'Pages publiées', 'value' => $health['breakdown']['published_pct'], 'gradient' => 'from-emerald-500 to-emerald-400'],
        ] as $metric)
        @php $barPct = (int) $metric['value']; @endphp
        <div class="mb-3 last:mb-0">
            <div class="flex justify-between text-xs mb-1.5">
                <span class="font-semibold text-gray-600">{{ $metric['label'] }}</span>
                <span class="font-black text-gray-900">{{ $barPct }}%</span>
            </div>
            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-1.5 rounded-full bg-linear-to-r {{ $metric['gradient'] }} w-0 transition-all duration-700"
                     data-pct="{{ $barPct }}"></div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Page stats --}}
    <div class="col-span-2 md:col-span-1 bg-white rounded-2xl border border-gray-100 px-6 py-5"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <h3 class="font-bold text-gray-900 mb-4">Pages</h3>
        <div class="space-y-3">
            @foreach([
                ['label' => 'Total',      'value' => $health['total_pages'], 'cls' => 'text-gray-900',    'bg' => 'bg-gray-100'],
                ['label' => 'Publiées',   'value' => $health['published'],   'cls' => 'text-emerald-700', 'bg' => 'bg-emerald-50'],
                ['label' => 'Brouillons', 'value' => $health['draft'],       'cls' => 'text-gray-600',    'bg' => 'bg-gray-50'],
                ['label' => 'Erreurs',    'value' => $health['errors'],      'cls' => 'text-rose-700',    'bg' => 'bg-rose-50'],
            ] as $stat)
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-gray-500">{{ $stat['label'] }}</span>
                <span class="text-lg font-black {{ $stat['cls'] }} {{ $stat['bg'] }} px-3 py-0.5 rounded-lg min-w-12 text-center">
                    {{ $stat['value'] }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Chart data stored as data-attributes, read by pure JS via atob() --}}
@php
    $chartHistory  = base64_encode(json_encode($history));
    $chartDistKeys = base64_encode(json_encode(array_keys($health['score_dist'])));
    $chartDistVals = base64_encode(json_encode(array_values($health['score_dist'])));
    $chartHasHist  = count($history) > 1 ? '1' : '0';
@endphp
<div id="chart-data" class="hidden"
     data-history="{{ $chartHistory }}"
     data-dist-keys="{{ $chartDistKeys }}"
     data-dist-vals="{{ $chartDistVals }}"
     data-has-history="{{ $chartHasHist }}"></div>

{{-- ═══ CHARTS ═══ --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 anim-fade-up delay-100">

    {{-- Score evolution --}}
    <div class="bg-white rounded-2xl border border-gray-100 px-6 py-5"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="font-bold text-gray-900">Évolution santé</h3>
                <p class="text-xs text-gray-400 mt-0.5">30 derniers jours</p>
            </div>
            <div class="flex items-center gap-3 text-xs text-gray-400">
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-0.5 bg-indigo-500 rounded inline-block"></span> Santé
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-px bg-blue-400 rounded inline-block border-t-2 border-dashed border-blue-400"></span> SEO
                </span>
            </div>
        </div>
        @if(count($history) > 1)
            <canvas id="healthChart" height="200"></canvas>
        @else
            <div class="flex flex-col items-center justify-center h-48 text-center">
                <div class="w-10 h-10 bg-indigo-50 rounded-2xl flex items-center justify-center mb-3">
                    <svg class="w-5 h-5 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div class="text-sm font-semibold text-gray-400">Pas encore d'historique</div>
                <div class="text-xs text-gray-300 mt-1">Revenez demain après le premier snapshot.</div>
            </div>
        @endif
    </div>

    {{-- Score distribution --}}
    <div class="bg-white rounded-2xl border border-gray-100 px-6 py-5"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="mb-5">
            <h3 class="font-bold text-gray-900">Distribution SEO</h3>
            <p class="text-xs text-gray-400 mt-0.5">Répartition des scores par tranche</p>
        </div>
        <canvas id="distChart" height="200"></canvas>
    </div>
</div>

{{-- ═══ CLUSTERS ═══ --}}
@if(!empty($health['clusters']))
<div class="bg-white rounded-2xl border border-gray-100 px-6 py-5 anim-fade-up delay-150"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-7 h-7 bg-indigo-50 rounded-xl flex items-center justify-center">
            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            </svg>
        </div>
        <div>
            <h3 class="font-bold text-gray-900">Clusters de contenu</h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ count($health['clusters']) }} cluster(s) identifié(s)</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach($health['clusters'] as $cluster => $count)
        <div class="flex items-center gap-2 bg-indigo-50 border border-indigo-100 rounded-xl px-3.5 py-2 hover:bg-indigo-100 transition-colors cursor-default">
            <span class="text-sm font-semibold text-indigo-800">{{ $cluster ?: 'général' }}</span>
            <span class="bg-indigo-200 text-indigo-900 rounded-full px-2 py-0.5 text-xs font-black">{{ $count }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
(function () {
    // Animate progress bars via data-pct attribute
    document.querySelectorAll('[data-pct]').forEach(function (el) {
        var pct = el.getAttribute('data-pct');
        setTimeout(function () { el.style.width = pct + '%'; }, 120);
    });

    // Chart data read from data-attributes (no Blade syntax in JS context)
    var cd         = document.getElementById('chart-data');
    var hasHistory = cd && cd.dataset.hasHistory === '1';
    var distLabels = cd ? JSON.parse(atob(cd.dataset.distKeys)) : [];
    var distValues = cd ? JSON.parse(atob(cd.dataset.distVals)) : [];

    var tooltipDefaults = {
        backgroundColor: 'rgba(15,17,35,0.92)',
        titleColor: '#fff',
        bodyColor: 'rgba(255,255,255,0.7)',
        borderColor: 'rgba(99,102,241,0.3)',
        borderWidth: 1,
        cornerRadius: 10,
        padding: 10,
    };

    if (hasHistory) {
        var historyData = JSON.parse(atob(cd.dataset.history));
        new Chart(document.getElementById('healthChart'), {
            type: 'line',
            data: {
                labels: historyData.map(function (r) { return r.snapshot_date; }),
                datasets: [
                    {
                        label: 'Santé',
                        data: historyData.map(function (r) { return r.health_score; }),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.07)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#6366f1',
                        borderWidth: 2,
                    },
                    {
                        label: 'SEO',
                        data: historyData.map(function (r) { return r.avg_seo_score; }),
                        borderColor: '#60a5fa',
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        pointRadius: 2,
                        borderDash: [4, 4],
                        borderWidth: 1.5,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false }, tooltip: tooltipDefaults },
                scales: {
                    y: { min: 0, max: 100, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    new Chart(document.getElementById('distChart'), {
        type: 'bar',
        data: {
            labels: distLabels,
            datasets: [{
                label: 'Pages',
                data: distValues,
                backgroundColor: [
                    'rgba(239,68,68,0.8)',
                    'rgba(251,191,36,0.8)',
                    'rgba(110,231,183,0.8)',
                    'rgba(52,211,153,0.8)',
                    'rgba(16,185,129,0.8)',
                ],
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: tooltipDefaults },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { grid: { display: false } }
            }
        }
    });
}());
</script>
@endpush
