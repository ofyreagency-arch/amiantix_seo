@extends('admin.layout')

@section('title', 'Dashboard')

@section('breadcrumb')
    <span class="font-medium text-gray-900">Dashboard</span>
@endsection

@section('content')
{{-- Stats --}}
<div class="grid grid-cols-4 gap-5 mb-8">
    @foreach([
        ['label' => 'Sites actifs',    'value' => $stats['total_sites'],  'color' => 'text-indigo-600', 'bg' => 'bg-indigo-50'],
        ['label' => 'Pages totales',   'value' => $stats['total_pages'],  'color' => 'text-gray-900',   'bg' => 'bg-gray-50'],
        ['label' => 'Publiées',        'value' => $stats['published'],    'color' => 'text-green-600',  'bg' => 'bg-green-50'],
        ['label' => 'Cette semaine',   'value' => $stats['this_week'],    'color' => 'text-blue-600',   'bg' => 'bg-blue-50'],
    ] as $stat)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div class="text-3xl font-bold {{ $stat['color'] }}">{{ $stat['value'] }}</div>
        <div class="text-sm text-gray-500 mt-1">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-3 gap-6">
    {{-- Sites --}}
    <div class="col-span-1 bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900 text-sm">Sites actifs</h2>
            <a href="{{ route('admin.sites.index') }}" class="text-xs text-indigo-600 hover:text-indigo-700">Voir tout →</a>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($sites as $item)
            <a href="{{ route('admin.sites.show', $item['site']->site_id) }}"
               class="flex items-center justify-between px-6 py-3.5 hover:bg-gray-50 transition-colors">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ $item['site']->name }}</div>
                    <div class="text-xs text-gray-400">{{ $item['site']->niche }}</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-semibold text-gray-700">{{ $item['pages'] }}</div>
                    <div class="text-xs text-green-600">{{ $item['published'] }} pub.</div>
                </div>
            </a>
            @empty
            <div class="px-6 py-8 text-center text-sm text-gray-400">Aucun site</div>
            @endforelse
        </div>
    </div>

    {{-- Recent pages --}}
    <div class="col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900 text-sm">Pages récentes</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Keyword</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Site</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Statut</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Score</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($recent as $page)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-3 font-medium text-gray-900 max-w-xs truncate">{{ $page->keyword }}</td>
                        <td class="px-6 py-3 text-gray-500 text-xs">{{ $page->site_id }}</td>
                        <td class="px-6 py-3">
                            @php
                                $colors = ['published' => 'bg-green-100 text-green-700', 'draft' => 'bg-gray-100 text-gray-600', 'review' => 'bg-yellow-100 text-yellow-700', 'error' => 'bg-red-100 text-red-700'];
                                $c = $colors[$page->status] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c }}">{{ $page->status }}</span>
                        </td>
                        <td class="px-6 py-3 text-right text-gray-600">{{ $page->seo_score ? number_format((float)$page->seo_score, 0) : '—' }}</td>
                        <td class="px-6 py-3 text-right text-gray-400 text-xs">{{ $page->updated_at?->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">Aucune page</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
