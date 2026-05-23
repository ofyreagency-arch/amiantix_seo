@php
$tabs = [
    ['route' => 'admin.sites.show',     'label' => 'Pages',     'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ['route' => 'admin.sites.health',   'label' => 'Santé',     'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ['route' => 'admin.sites.strategy', 'label' => 'Stratégie', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
    ['route' => 'admin.sites.semantic', 'label' => 'Sémantique','icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'],
    ['route' => 'admin.sites.crawler',  'label' => 'Crawler',   'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
    ['route' => 'admin.sites.autopilot','label' => 'Autopilot', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
];
@endphp

<div class="flex gap-1 p-1 rounded-2xl mb-6 border border-gray-100"
     style="background:#f0f1f5;">
    @foreach($tabs as $tab)
    @php $active = request()->routeIs($tab['route']); @endphp
    <a href="{{ route($tab['route'], $site->site_id) }}"
       class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all flex-1 justify-center
              {{ $active
                    ? 'bg-white text-indigo-600 shadow-sm border border-gray-100/80'
                    : 'text-gray-400 hover:text-gray-700 hover:bg-white/60' }}">
        <svg class="w-3.5 h-3.5 shrink-0 {{ $active ? 'text-indigo-500' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"/>
        </svg>
        <span class="hidden lg:inline">{{ $tab['label'] }}</span>
    </a>
    @endforeach
</div>
