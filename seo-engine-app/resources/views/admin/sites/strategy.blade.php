@extends('admin.layout')
@section('title', 'Stratégie — '.$site->name)
@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700">Sites</a>
    <span class="mx-2">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700">{{ $site->name }}</a>
    <span class="mx-2">›</span>
    <span class="font-medium text-gray-900">Stratégie</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-bold text-gray-900">Plan stratégique SEO</h2>
        <p class="text-sm text-gray-500 mt-0.5">Généré par IA, adapté à la niche {{ $site->niche }}</p>
    </div>
    <form method="POST" action="{{ route('admin.sites.strategy.generate', $site->site_id) }}">
        @csrf
        <button type="submit"
            class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            {{ $items->isEmpty() ? 'Générer la stratégie' : 'Regénérer' }}
        </button>
    </form>
</div>

@if($items->isEmpty())
<div class="bg-white rounded-2xl border border-dashed border-gray-200 px-8 py-16 text-center">
    <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
    </div>
    <h3 class="text-gray-700 font-semibold mb-2">Aucune stratégie générée</h3>
    <p class="text-gray-400 text-sm">Cliquez sur "Générer la stratégie" pour obtenir un plan d'action personnalisé par IA.</p>
</div>
@else

@php
$impactColors = ['high' => 'bg-red-100 text-red-700', 'medium' => 'bg-yellow-100 text-yellow-700', 'low' => 'bg-gray-100 text-gray-600'];
$typeColors = [
    'add_internal_links' => 'bg-green-100 text-green-700',
    'refresh_page' => 'bg-blue-100 text-blue-700',
    'differentiate_intent' => 'bg-rose-100 text-rose-700',
    'create_page' => 'bg-purple-100 text-purple-700',
    'page' => 'bg-blue-100 text-blue-700',
    'cluster' => 'bg-purple-100 text-purple-700',
    'technical' => 'bg-orange-100 text-orange-700',
    'link' => 'bg-green-100 text-green-700',
    'content' => 'bg-cyan-100 text-cyan-700',
];
$typeLabels = [
    'add_internal_links' => 'add internal links',
    'refresh_page' => 'refresh page',
    'differentiate_intent' => 'differentiate intent',
    'create_page' => 'create page',
];
@endphp

<div class="space-y-3">
    @foreach($items as $item)
    @php
        $ic = $impactColors[$item->estimated_impact] ?? 'bg-gray-100 text-gray-600';
        $tc = $typeColors[$item->type] ?? 'bg-gray-100 text-gray-600';
        $done = $item->status === 'done';
    @endphp
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-4 flex items-start gap-5 {{ $done ? 'opacity-50' : '' }}">
        <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center font-black text-lg
            {{ $item->priority <= 3 ? 'bg-red-50 text-red-600' : ($item->priority <= 7 ? 'bg-amber-50 text-amber-600' : 'bg-gray-50 text-gray-500') }}">
            {{ $item->priority }}
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="font-semibold text-gray-900 {{ $done ? 'line-through' : '' }}">{{ $item->title }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tc }}">{{ $typeLabels[$item->type] ?? $item->type }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $ic }}">{{ $item->estimated_impact }}</span>
                @if($done)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">✓ Fait</span>
                @endif
            </div>
            <p class="text-sm text-gray-600">{{ $item->description }}</p>
            @if(!empty($item->keywords_json))
            <div class="flex flex-wrap gap-1.5 mt-2">
                @foreach(array_slice($item->keywords_json, 0, 5) as $kw)
                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-md">{{ $kw }}</span>
                @endforeach
            </div>
            @endif
        </div>
        @if(!$done)
        <form method="POST" action="{{ route('admin.sites.strategy.done', [$site->site_id, $item->id]) }}" class="flex-shrink-0">
            @csrf
            <button type="submit" class="text-xs text-gray-400 hover:text-green-600 px-3 py-1.5 border border-gray-200 rounded-lg hover:border-green-200 transition-colors">
                Marquer fait
            </button>
        </form>
        @endif
    </div>
    @endforeach
</div>
@endif

@endsection
