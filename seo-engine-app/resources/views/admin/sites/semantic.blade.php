@extends('admin.layout')
@section('title', 'Carte sémantique — '.$site->name)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700 transition-colors">Sites</a>
    <span class="mx-2 text-gray-300">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700 transition-colors">{{ $site->name }}</a>
    <span class="mx-2 text-gray-300">›</span>
    <span class="font-semibold text-gray-900">Carte sémantique</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

{{-- ═══ HEADER ═══ --}}
<div class="flex items-center justify-between mb-5 anim-fade-up">
    <div>
        <h2 class="text-xl font-black text-gray-900">Carte sémantique</h2>
        <p class="text-sm text-gray-400 mt-0.5">
            <span class="font-semibold text-indigo-600">{{ $pageCount }}</span> pages ·
            <span class="font-semibold text-purple-600">{{ $linkCount }}</span> liens sémantiques
        </p>
    </div>
    <div class="flex items-center gap-4 text-xs font-semibold text-gray-500">
        <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-blue-400 inline-block"></span>Interne
        </span>
        <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-purple-400 inline-block"></span>Sémantique
        </span>
        <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-rose-400 inline-block"></span>Cannibalisation
        </span>
    </div>
</div>

@if($pageCount === 0)

{{-- No pages --}}
<div class="bg-white rounded-2xl border border-dashed border-gray-200 px-8 py-20 text-center anim-fade-up"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.03);">
    <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
        <svg class="w-8 h-8 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
        </svg>
    </div>
    <h3 class="text-base font-bold text-gray-500 mb-1">Aucune page</h3>
    <p class="text-sm text-gray-400">Générez des pages et lancez l'embedding pour voir la carte sémantique.</p>
</div>

@elseif($linkCount === 0)

{{-- No links --}}
<div class="bg-white rounded-2xl border border-gray-100 px-8 py-20 text-center anim-fade-up"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="w-16 h-16 bg-violet-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
        <svg class="w-8 h-8 text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
        </svg>
    </div>
    <h3 class="text-base font-bold text-gray-500 mb-2">Embeddings non calculés</h3>
    <p class="text-sm text-gray-400 mb-3">Les relations sémantiques entre vos pages n'ont pas encore été générées.</p>
    <code class="inline-block text-xs font-mono bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg">php artisan seo:semantic-links</code>
</div>

@else

{{-- ═══ GRAPH ═══ --}}
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden relative anim-fade-up"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">

    {{-- Controls overlay --}}
    <div class="absolute top-4 right-4 z-10 flex items-center gap-2">
        <div class="flex items-center gap-1.5 bg-white/90 backdrop-blur border border-gray-100 rounded-xl px-3 py-1.5 text-xs font-semibold text-gray-500 shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            Glissez · Scroll pour zoomer
        </div>
    </div>

    {{-- Tooltip --}}
    <div id="semantic-tooltip"
         class="hidden fixed bg-gray-900 text-white text-xs px-3 py-2 rounded-xl shadow-2xl z-50 pointer-events-none max-w-xs leading-relaxed"
         style="backdrop-filter:blur(8px);">
    </div>

    {{-- Graph canvas --}}
    <div id="semantic-map" class="w-full" style="height:600px;cursor:grab;"></div>
</div>

<div class="mt-3 text-center text-xs text-gray-400">
    Glissez pour déplacer · Scroll pour zoomer · Cliquez sur un nœud pour ouvrir la page
</div>

@endif
@endsection

@push('scripts')
@if($linkCount > 0)
<script>
(function () {
    var siteId  = '{{ $site->site_id }}';
    var dataUrl = '{{ route("admin.sites.semantic.data", $site->site_id) }}';

    var clusterColors = {};
    var palette = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6','#f97316','#84cc16'];
    var colorIdx = 0;

    function clusterColor(cluster) {
        if (!clusterColors[cluster]) {
            clusterColors[cluster] = palette[colorIdx++ % palette.length];
        }
        return clusterColors[cluster];
    }

    function linkColor(type) {
        var map = { internal_link: '#93c5fd', semantic_neighbor: '#c4b5fd', cannibalization: '#fca5a5' };
        return map[type] || '#e5e7eb';
    }

    fetch(dataUrl)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var nodes = data.nodes;
            var edges = data.edges;
            if (!nodes.length) return;

            var container = document.getElementById('semantic-map');
            var W = container.clientWidth;
            var H = 600;
            var tooltip = document.getElementById('semantic-tooltip');

            var svg = d3.select('#semantic-map')
                .append('svg').attr('width', W).attr('height', H)
                .call(d3.zoom().scaleExtent([0.3, 3]).on('zoom', function (e) { g.attr('transform', e.transform); }));

            var g = svg.append('g');

            var sim = d3.forceSimulation(nodes)
                .force('link', d3.forceLink(edges).id(function (d) { return d.id; }).distance(80).strength(function (d) { return d.strength * 0.8; }))
                .force('charge', d3.forceManyBody().strength(-250))
                .force('center', d3.forceCenter(W / 2, H / 2))
                .force('collision', d3.forceCollide().radius(function (d) { return Math.max(14, d.score / 4 + 10); }));

            var link = g.append('g').selectAll('line').data(edges).join('line')
                .attr('stroke', function (d) { return linkColor(d.type); })
                .attr('stroke-width', function (d) { return Math.max(1, d.strength * 3); })
                .attr('stroke-opacity', 0.7);

            var node = g.append('g').selectAll('circle').data(nodes).join('circle')
                .attr('r', function (d) { return Math.max(8, Math.min(22, d.score / 4 + 8)); })
                .attr('fill', function (d) { return clusterColor(d.cluster); })
                .attr('stroke', '#fff').attr('stroke-width', 2)
                .style('cursor', 'pointer')
                .call(d3.drag()
                    .on('start', function (e, d) { if (!e.active) sim.alphaTarget(0.3).restart(); d.fx = d.x; d.fy = d.y; })
                    .on('drag',  function (e, d) { d.fx = e.x; d.fy = e.y; })
                    .on('end',   function (e, d) { if (!e.active) sim.alphaTarget(0); d.fx = null; d.fy = null; }))
                .on('mouseover', function (e, d) {
                    tooltip.innerHTML = '<b>' + d.label + '</b><br>Cluster: ' + d.cluster + '<br>Score SEO: ' + d.score.toFixed(0) + '<br>Statut: ' + d.status;
                    tooltip.classList.remove('hidden');
                })
                .on('mousemove', function (e) {
                    tooltip.style.left = (e.pageX + 14) + 'px';
                    tooltip.style.top  = (e.pageY - 10) + 'px';
                })
                .on('mouseout', function () { tooltip.classList.add('hidden'); })
                .on('click', function (e, d) {
                    window.location.href = '/admin/sites/' + siteId + '/pages/' + d.id;
                });

            var label = g.append('g').selectAll('text').data(nodes).join('text')
                .text(function (d) { return d.label.length > 20 ? d.label.slice(0, 20) + '…' : d.label; })
                .attr('font-size', 10).attr('fill', '#6b7280')
                .attr('pointer-events', 'none');

            sim.on('tick', function () {
                link.attr('x1', function (d) { return d.source.x; })
                    .attr('y1', function (d) { return d.source.y; })
                    .attr('x2', function (d) { return d.target.x; })
                    .attr('y2', function (d) { return d.target.y; });
                node.attr('cx', function (d) { return d.x; })
                    .attr('cy', function (d) { return d.y; });
                label.attr('x', function (d) { return d.x + 14; })
                     .attr('y', function (d) { return d.y + 4; });
            });
        });
}());
</script>
@endif
@endpush
