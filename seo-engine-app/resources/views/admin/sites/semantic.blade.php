@extends('admin.layout')
@section('title', 'Carte sémantique — '.$site->name)
@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700">Sites</a>
    <span class="mx-2">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700">{{ $site->name }}</a>
    <span class="mx-2">›</span>
    <span class="font-medium text-gray-900">Carte sémantique</span>
@endsection

@section('content')
@include('admin.partials.site-tabs')

<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-bold text-gray-900">Carte sémantique</h2>
        <p class="text-sm text-gray-500">{{ $pageCount }} pages · {{ $linkCount }} liens sémantiques</p>
    </div>
    <div class="flex items-center gap-3 text-xs text-gray-500">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-blue-400 inline-block"></span> Interne</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-purple-400 inline-block"></span> Sémantique</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-400 inline-block"></span> Cannibalisation</span>
    </div>
</div>

@if($pageCount === 0)
<div class="bg-white rounded-2xl border border-dashed border-gray-200 px-8 py-16 text-center">
    <p class="text-gray-400">Aucune page. Générez des pages et lancez l'embedding pour voir la carte.</p>
</div>
@elseif($linkCount === 0)
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-8 py-16 text-center">
    <p class="text-gray-500 mb-3">Les embeddings sémantiques ne sont pas encore calculés.</p>
    <p class="text-gray-400 text-sm">Lancez l'autopilot ou la commande <code class="bg-gray-100 px-2 py-0.5 rounded">php artisan seo:semantic-links</code></p>
</div>
@else
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div id="semantic-tooltip"
         class="hidden absolute bg-gray-900 text-white text-xs px-3 py-2 rounded-lg shadow-xl z-50 pointer-events-none max-w-xs">
    </div>
    <div id="semantic-map" class="w-full" style="height: 600px; cursor: grab;"></div>
</div>

<div class="mt-4 text-xs text-gray-400 text-center">
    Glissez pour déplacer · Scroll pour zoomer · Cliquez sur un nœud pour voir la page
</div>
@endif

@endsection

@push('scripts')
@if($linkCount > 0)
<script>
const siteId = '{{ $site->site_id }}';
const dataUrl = '{{ route("admin.sites.semantic.data", $site->site_id) }}';

const clusterColors = {};
const palette = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6','#f97316','#84cc16'];
let colorIdx = 0;

function clusterColor(cluster) {
    if (!clusterColors[cluster]) {
        clusterColors[cluster] = palette[colorIdx++ % palette.length];
    }
    return clusterColors[cluster];
}

function linkColor(type) {
    return { internal_link: '#93c5fd', semantic_neighbor: '#c4b5fd', cannibalization: '#fca5a5' }[type] || '#e5e7eb';
}

fetch(dataUrl)
    .then(r => r.json())
    .then(({ nodes, edges }) => {
        if (!nodes.length) return;

        const container = document.getElementById('semantic-map');
        const W = container.clientWidth;
        const H = 600;

        const svg = d3.select('#semantic-map')
            .append('svg').attr('width', W).attr('height', H)
            .call(d3.zoom().scaleExtent([0.3, 3]).on('zoom', e => g.attr('transform', e.transform)));

        const g = svg.append('g');
        const tooltip = document.getElementById('semantic-tooltip');

        const sim = d3.forceSimulation(nodes)
            .force('link', d3.forceLink(edges).id(d => d.id).distance(80).strength(d => d.strength * 0.8))
            .force('charge', d3.forceManyBody().strength(-250))
            .force('center', d3.forceCenter(W / 2, H / 2))
            .force('collision', d3.forceCollide().radius(d => Math.max(14, d.score / 4 + 10)));

        const link = g.append('g').selectAll('line').data(edges).join('line')
            .attr('stroke', d => linkColor(d.type))
            .attr('stroke-width', d => Math.max(1, d.strength * 3))
            .attr('stroke-opacity', 0.7);

        const node = g.append('g').selectAll('circle').data(nodes).join('circle')
            .attr('r', d => Math.max(8, Math.min(22, d.score / 4 + 8)))
            .attr('fill', d => clusterColor(d.cluster))
            .attr('stroke', '#fff').attr('stroke-width', 2)
            .style('cursor', 'pointer')
            .call(d3.drag()
                .on('start', (e, d) => { if (!e.active) sim.alphaTarget(0.3).restart(); d.fx = d.x; d.fy = d.y; })
                .on('drag',  (e, d) => { d.fx = e.x; d.fy = e.y; })
                .on('end',   (e, d) => { if (!e.active) sim.alphaTarget(0); d.fx = null; d.fy = null; }))
            .on('mouseover', (e, d) => {
                tooltip.innerHTML = `<b>${d.label}</b><br>Cluster: ${d.cluster}<br>Score SEO: ${d.score.toFixed(0)}<br>Statut: ${d.status}`;
                tooltip.classList.remove('hidden');
            })
            .on('mousemove', e => {
                tooltip.style.left = (e.pageX + 12) + 'px';
                tooltip.style.top  = (e.pageY - 8) + 'px';
            })
            .on('mouseout', () => tooltip.classList.add('hidden'))
            .on('click', (e, d) => {
                window.location.href = `/admin/sites/${siteId}/pages/${d.id}`;
            });

        const label = g.append('g').selectAll('text').data(nodes).join('text')
            .text(d => d.label.length > 20 ? d.label.slice(0, 20) + '…' : d.label)
            .attr('font-size', 10).attr('fill', '#6b7280')
            .attr('pointer-events', 'none');

        sim.on('tick', () => {
            link.attr('x1', d => d.source.x).attr('y1', d => d.source.y)
                .attr('x2', d => d.target.x).attr('y2', d => d.target.y);
            node.attr('cx', d => d.x).attr('cy', d => d.y);
            label.attr('x', d => d.x + 14).attr('y', d => d.y + 4);
        });
    });
</script>
@endif
@endpush
