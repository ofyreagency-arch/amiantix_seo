@extends('admin.layout')
@section('title', 'Crawler — '.$site->name)
@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700">Sites</a>
    <span class="mx-2">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700">{{ $site->name }}</a>
    <span class="mx-2">›</span>
    <span class="font-medium text-gray-900">Crawler</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-bold text-gray-900">Analyse du site existant</h2>
        <p class="text-sm text-gray-500">Comprend le contenu existant et détecte les gaps de couverture.</p>
    </div>
    <form method="POST" action="{{ route('admin.sites.crawler.start', $site->site_id) }}">
        @csrf
        <button type="submit" onclick="return confirm('Lancer un crawl sur {{ $site->url }} ? Cela peut prendre 1-2 minutes.')"
            class="flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9 3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
            </svg>
            Lancer le crawl
        </button>
    </form>
</div>

@if($results['total'] === 0)
<div class="bg-white rounded-2xl border border-dashed border-gray-200 px-8 py-16 text-center">
    <div class="w-16 h-16 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3"/>
        </svg>
    </div>
    <p class="text-gray-500">Aucun crawl effectué. Lancez l'analyse pour cartographier le site.</p>
</div>
@else

{{-- Coverage stats --}}
<div class="grid grid-cols-4 gap-5 mb-6">
    @foreach([
        ['label' => 'Pages crawlées',     'value' => $results['total'],     'color' => 'text-gray-900'],
        ['label' => 'Couvertes par SEO',  'value' => $results['covered'],   'color' => 'text-green-600'],
        ['label' => 'Non couvertes',      'value' => $results['uncovered'], 'color' => 'text-red-500'],
        ['label' => 'Taux de couverture', 'value' => $results['rate'].'%',  'color' => 'text-indigo-600'],
    ] as $stat)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-center">
        <div class="text-3xl font-bold {{ $stat['color'] }}">{{ $stat['value'] }}</div>
        <div class="text-xs text-gray-500 mt-1">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- Coverage bar --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-4 mb-6">
    <div class="flex justify-between text-xs text-gray-500 mb-2">
        <span>Couverture SEO</span>
        <span>{{ $results['covered'] }} / {{ $results['total'] }} pages</span>
    </div>
    <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
        <div class="h-3 bg-gradient-to-r from-green-400 to-green-500 rounded-full transition-all"
             style="width: {{ $results['rate'] }}%"></div>
    </div>
</div>

{{-- Pages table --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Pages découvertes</h3>
        <div class="flex gap-3 text-xs">
            <span class="flex items-center gap-1.5 text-green-600"><span class="w-2 h-2 bg-green-400 rounded-full"></span>Couverte</span>
            <span class="flex items-center gap-1.5 text-red-500"><span class="w-2 h-2 bg-red-400 rounded-full"></span>Non couverte</span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase">URL</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase">Titre</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase">Mots</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase">Prof.</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase">Code</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase">Couvert</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($results['pages'] as $page)
                <tr class="hover:bg-gray-50 {{ !$page->is_covered ? 'bg-red-50/30' : '' }}">
                    <td class="px-6 py-3 text-xs font-mono text-gray-500 max-w-xs truncate">{{ $page->url }}</td>
                    <td class="px-6 py-3 text-gray-700 max-w-xs truncate">{{ $page->title ?: '—' }}</td>
                    <td class="px-6 py-3 text-right text-gray-500">{{ $page->word_count ?? '—' }}</td>
                    <td class="px-6 py-3 text-right text-gray-400">{{ $page->depth }}</td>
                    <td class="px-6 py-3 text-right">
                        <span class="{{ $page->status_code === 200 ? 'text-green-600' : 'text-red-500' }} font-medium">{{ $page->status_code }}</span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        @if($page->is_covered)
                            <span class="text-green-600 font-medium">✓</span>
                        @else
                            <span class="text-red-400">✗</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
