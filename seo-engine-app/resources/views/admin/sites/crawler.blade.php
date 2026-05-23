@extends('admin.layout')
@section('title', 'Crawler — '.$site->name)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700 transition-colors">Sites</a>
    <span class="mx-2 text-gray-300">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700 transition-colors">{{ $site->name }}</a>
    <span class="mx-2 text-gray-300">›</span>
    <span class="font-semibold text-gray-900">Crawler</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

{{-- ═══ HEADER ═══ --}}
<div class="flex items-center justify-between mb-6 anim-fade-up">
    <div>
        <h2 class="text-xl font-black text-gray-900">Analyse du site existant</h2>
        <p class="text-sm text-gray-400 mt-0.5">Cartographie le contenu et détecte les gaps de couverture.</p>
    </div>
    <form method="POST" action="{{ route('admin.sites.crawler.start', $site->site_id) }}">
        @csrf
        <button type="submit"
                onclick="return confirm('Lancer un crawl sur {{ $site->url }} ?')"
                class="flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white rounded-xl transition-all hover:-translate-y-0.5"
                style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 4px 14px rgba(16,185,129,0.35);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Lancer le crawl
        </button>
    </form>
</div>

@if($results['total'] === 0)

{{-- Empty state --}}
<div class="bg-white rounded-2xl border border-dashed border-gray-200 px-8 py-20 text-center anim-fade-up"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.03);">
    <div class="w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
        <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
    </div>
    <h3 class="text-base font-bold text-gray-500 mb-1">Aucun crawl effectué</h3>
    <p class="text-sm text-gray-400">Lancez l'analyse pour cartographier le site et détecter les gaps.</p>
</div>

@else

@php $coverageRate = (int) $results['rate']; @endphp

{{-- ═══ STATS ═══ --}}
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6 anim-fade-up">
    @foreach([
        ['label' => 'Pages crawlées',     'value' => $results['total'],     'cls' => 'text-gray-900',    'bg' => 'bg-gray-100',    'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9 3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9'],
        ['label' => 'Couvertes par SEO',  'value' => $results['covered'],   'cls' => 'text-emerald-700', 'bg' => 'bg-emerald-50',  'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Non couvertes',      'value' => $results['uncovered'], 'cls' => 'text-rose-700',    'bg' => 'bg-rose-50',     'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Taux de couverture', 'value' => $coverageRate.'%',     'cls' => 'text-indigo-700',  'bg' => 'bg-indigo-50',   'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ] as $stat)
    <div class="bg-white rounded-2xl border border-gray-100 px-6 py-5"
         style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
        <div class="w-9 h-9 {{ $stat['bg'] }} rounded-xl flex items-center justify-center mb-3">
            <svg class="w-4.5 h-4.5 {{ $stat['cls'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/>
            </svg>
        </div>
        <div class="text-2xl font-black {{ $stat['cls'] }}">{{ $stat['value'] }}</div>
        <div class="text-xs font-semibold text-gray-400 mt-0.5">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- ═══ COVERAGE BAR ═══ --}}
<div class="bg-white rounded-2xl border border-gray-100 px-6 py-4 mb-6 anim-fade-up delay-50"
     style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">
    <div class="flex justify-between text-xs font-semibold mb-2.5">
        <span class="text-gray-600">Couverture SEO</span>
        <span class="text-gray-900">{{ $results['covered'] }} / {{ $results['total'] }} pages</span>
    </div>
    <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
        <div class="h-2.5 bg-linear-to-r from-emerald-400 to-emerald-500 rounded-full w-0 transition-all duration-700"
             data-pct="{{ $coverageRate }}"></div>
    </div>
</div>

{{-- ═══ PAGES TABLE ═══ --}}
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-100"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="font-bold text-gray-900">Pages découvertes</h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ count($results['pages']) }} page(s) indexée(s)</p>
        </div>
        <div class="flex gap-4 text-xs font-semibold">
            <span class="flex items-center gap-1.5 text-emerald-600">
                <span class="w-2 h-2 bg-emerald-400 rounded-full inline-block"></span>Couverte
            </span>
            <span class="flex items-center gap-1.5 text-rose-500">
                <span class="w-2 h-2 bg-rose-400 rounded-full inline-block"></span>Non couverte
            </span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="background:#f8f9fc;">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">URL</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden md:table-cell">Titre</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden lg:table-cell">Mots</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden xl:table-cell">Prof.</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Code</th>
                    <th class="text-right px-6 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Couvert</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($results['pages'] as $page)
                @php
                    $rowCls    = $page->is_covered ? '' : 'bg-rose-50/30';
                    $codeCls   = $page->status_code === 200 ? 'text-emerald-600 font-bold' : 'text-rose-500 font-bold';
                    $coverIcon = $page->is_covered ? '✓' : '✗';
                    $coverCls  = $page->is_covered ? 'text-emerald-600 font-bold' : 'text-rose-400';
                @endphp
                <tr class="hover:bg-gray-50/60 transition-colors {{ $rowCls }}">
                    <td class="px-6 py-3 text-xs font-mono text-gray-500 max-w-[220px] truncate">{{ $page->url }}</td>
                    <td class="px-6 py-3 text-gray-700 max-w-[200px] truncate hidden md:table-cell">{{ $page->title ?: '—' }}</td>
                    <td class="px-6 py-3 text-right text-gray-500 hidden lg:table-cell">{{ $page->word_count ?? '—' }}</td>
                    <td class="px-6 py-3 text-right text-gray-400 hidden xl:table-cell">{{ $page->depth }}</td>
                    <td class="px-6 py-3 text-right"><span class="{{ $codeCls }}">{{ $page->status_code }}</span></td>
                    <td class="px-6 py-3 text-right"><span class="{{ $coverCls }}">{{ $coverIcon }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-pct]').forEach(function (el) {
    var pct = el.getAttribute('data-pct');
    setTimeout(function () { el.style.width = pct + '%'; }, 150);
});
</script>
@endpush
