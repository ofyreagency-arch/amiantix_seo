@extends('admin.layout')
@section('title', 'Stratégie — '.$site->name)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700 transition-colors">Sites</a>
    <span class="mx-2 text-gray-300">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700 transition-colors">{{ $site->name }}</a>
    <span class="mx-2 text-gray-300">›</span>
    <span class="font-semibold text-gray-900">Stratégie</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

@php
$impactColors = [
    'high'   => 'bg-rose-50 text-rose-700 border-rose-100',
    'medium' => 'bg-amber-50 text-amber-700 border-amber-100',
    'low'    => 'bg-gray-100 text-gray-600 border-gray-200',
];
$typeColors = [
    'add_internal_links'   => 'bg-emerald-50 text-emerald-700 border-emerald-100',
    'refresh_page'         => 'bg-blue-50 text-blue-700 border-blue-100',
    'differentiate_intent' => 'bg-rose-50 text-rose-700 border-rose-100',
    'create_page'          => 'bg-purple-50 text-purple-700 border-purple-100',
    'page'                 => 'bg-blue-50 text-blue-700 border-blue-100',
    'cluster'              => 'bg-violet-50 text-violet-700 border-violet-100',
    'technical'            => 'bg-orange-50 text-orange-700 border-orange-100',
    'link'                 => 'bg-emerald-50 text-emerald-700 border-emerald-100',
    'content'              => 'bg-cyan-50 text-cyan-700 border-cyan-100',
];
$typeLabels = [
    'add_internal_links'   => 'add internal links',
    'refresh_page'         => 'refresh page',
    'differentiate_intent' => 'differentiate intent',
    'create_page'          => 'create page',
];
@endphp

{{-- ═══ HEADER ═══ --}}
<div class="flex items-center justify-between mb-6 anim-fade-up">
    <div>
        <h2 class="text-xl font-black text-gray-900">Plan stratégique SEO</h2>
        <p class="text-sm text-gray-400 mt-0.5">Généré par IA · adapté à la niche <span class="font-semibold text-indigo-600">{{ $site->niche }}</span></p>
    </div>
    <form method="POST" action="{{ route('admin.sites.strategy.generate', $site->site_id) }}">
        @csrf
        <button type="submit"
                class="flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white rounded-xl transition-all hover:-translate-y-0.5"
                style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 14px rgba(99,102,241,0.35);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            {{ $items->isEmpty() ? 'Générer la stratégie' : 'Regénérer' }}
        </button>
    </form>
</div>

@if($items->isEmpty())

{{-- Empty state --}}
<div class="bg-white rounded-2xl border border-dashed border-gray-200 px-8 py-20 text-center anim-fade-up"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.03);">
    <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
        <svg class="w-8 h-8 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
        </svg>
    </div>
    <h3 class="text-base font-bold text-gray-500 mb-1">Aucune stratégie générée</h3>
    <p class="text-sm text-gray-400">Cliquez sur "Générer la stratégie" pour obtenir un plan d'action personnalisé par IA.</p>
</div>

@else

<div class="space-y-3 anim-fade-up">
    @foreach($items as $item)
    @php
        $ic        = $impactColors[$item->estimated_impact] ?? 'bg-gray-100 text-gray-600 border-gray-200';
        $tc        = $typeColors[$item->type] ?? 'bg-gray-100 text-gray-600 border-gray-200';
        $done      = $item->status === 'done';
        $doneCls   = $done ? 'opacity-50' : '';
        $doneTxt   = $done ? 'line-through text-gray-400' : 'text-gray-900';
        $prioBg    = $item->priority <= 3
            ? 'bg-rose-50 text-rose-700'
            : ($item->priority <= 7 ? 'bg-amber-50 text-amber-700' : 'bg-gray-50 text-gray-500');
    @endphp
    <div class="bg-white rounded-2xl border border-gray-100 px-6 py-4 flex items-start gap-5 {{ $doneCls }} hover:border-indigo-100 transition-all"
         style="box-shadow:0 2px 8px rgba(0,0,0,0.03);">

        {{-- Priority badge --}}
        <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center font-black text-lg {{ $prioBg }}">
            {{ $item->priority }}
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                <span class="font-bold {{ $doneTxt }}">{{ $item->title }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold border {{ $tc }}">
                    {{ $typeLabels[$item->type] ?? $item->type }}
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold border {{ $ic }}">
                    {{ $item->estimated_impact }}
                </span>
                @if($done)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
                    ✓ Fait
                </span>
                @endif
            </div>
            <p class="text-sm text-gray-600 leading-relaxed">{{ $item->description }}</p>
            @if(!empty($item->keywords_json))
            <div class="flex flex-wrap gap-1.5 mt-2.5">
                @foreach(array_slice($item->keywords_json, 0, 5) as $kw)
                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-lg font-medium">{{ $kw }}</span>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Mark done --}}
        @if(!$done)
        <form method="POST" action="{{ route('admin.sites.strategy.done', [$site->site_id, $item->id]) }}" class="shrink-0">
            @csrf
            <button type="submit"
                    class="text-xs font-semibold text-gray-400 hover:text-emerald-600 px-3 py-1.5 border border-gray-200 rounded-lg hover:border-emerald-200 hover:bg-emerald-50 transition-all">
                Marquer fait
            </button>
        </form>
        @endif
    </div>
    @endforeach
</div>

@endif
@endsection
