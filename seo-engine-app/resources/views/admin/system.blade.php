@extends('admin.layout')

@section('title', 'System Status')

@section('breadcrumb')
    <span class="font-semibold text-gray-900">System Status</span>
@endsection

@section('content')

@php
    $doctorOk   = $doctorResult['ok'] && $dbOk && $cacheOk;
    $queueAsync = $queueDriver !== 'sync';
    $openaiOk   = $openaiKey && ($openaiPing['ok'] ?? false);
    $diskPct    = (int) $diskUsedPct;
    $envIsProd  = ($infrastructure['environment'] ?? '') === 'production';
    $debugOn    = (bool) ($infrastructure['debug_mode'] ?? false);
    $criticalRuntime = ($runtimeSummary['critical'] ?? 0) > 0;
@endphp

{{-- ═══ PAGE HEADER ═══ --}}
<div class="flex items-center justify-between mb-6 anim-fade-up">
    <div>
        <h1 class="text-xl font-black text-gray-900">System Status</h1>
        <p class="text-sm text-gray-400 mt-0.5">État du VPS, services et moteur SEO · {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
    <a href="{{ route('admin.system') }}"
       class="flex items-center gap-2 px-4 py-2 border border-gray-200 bg-white text-gray-600 hover:border-indigo-200 hover:text-indigo-600 text-sm font-semibold rounded-xl transition-all"
       style="box-shadow:0 1px 4px rgba(0,0,0,0.04);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Rafraîchir
    </a>
</div>

{{-- ═══ GLOBAL STATUS BANNER ═══ --}}
@if($doctorOk)
<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-5 mb-6 flex items-center gap-4 anim-fade-up">
    <div class="w-12 h-12 rounded-2xl bg-emerald-500 flex items-center justify-center shrink-0 shadow-lg">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
    <div>
        <div class="font-black text-lg text-emerald-800">Tout opérationnel</div>
        <div class="text-sm text-emerald-700 mt-0.5">
            {{ count($doctorResult['errors']) }} erreur(s) · {{ count($doctorResult['warnings']) }} avertissement(s)
        </div>
    </div>
</div>
@else
<div class="rounded-2xl border border-rose-200 bg-rose-50 px-6 py-5 mb-6 flex items-center gap-4 anim-fade-up">
    <div class="w-12 h-12 rounded-2xl bg-rose-500 flex items-center justify-center shrink-0 shadow-lg">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
    </div>
    <div>
        <div class="font-black text-lg text-rose-800">Problèmes détectés</div>
        <div class="text-sm text-rose-700 mt-0.5">
            {{ count($doctorResult['errors']) }} erreur(s) · {{ count($doctorResult['warnings']) }} avertissement(s)
            @if(!$dbOk) · <strong>DB KO</strong> @endif
            @if(!$cacheOk) · <strong>Cache KO</strong> @endif
        </div>
    </div>
</div>
@endif

{{-- ═══ RUNTIME HEALTH BOARD ═══ --}}
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6 anim-fade-up delay-25"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-4">
        <div>
            <h2 class="font-bold text-gray-900">System Health / Runtime Status</h2>
            <p class="text-xs text-gray-400 mt-0.5">Vue instantanée des modules réels du moteur, sans faux OK.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap justify-end">
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">
                {{ $runtimeSummary['ok'] ?? 0 }} OK
            </span>
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold border bg-amber-50 text-amber-700 border-amber-100">
                {{ $runtimeSummary['warning'] ?? 0 }} warning
            </span>
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold border {{ $criticalRuntime ? 'bg-rose-50 text-rose-700 border-rose-100' : 'bg-gray-100 text-gray-500 border-gray-200' }}">
                {{ $runtimeSummary['critical'] ?? 0 }} critical
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-px" style="background:#f3f4f6;">
        @foreach($runtimeModules as $module)
        @php
            $statusCls = match ($module['status']) {
                'ok' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                'warning' => 'bg-amber-50 text-amber-700 border-amber-100',
                default => 'bg-rose-50 text-rose-700 border-rose-100',
            };
            $dotCls = match ($module['status']) {
                'ok' => 'bg-emerald-500',
                'warning' => 'bg-amber-400',
                default => 'bg-rose-500',
            };
        @endphp
        <div class="bg-white px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $dotCls }} shrink-0"></div>
                        <h3 class="text-sm font-bold text-gray-900">{{ $module['label'] }}</h3>
                    </div>
                    <p class="text-sm text-gray-500 mt-1.5">{{ $module['summary'] }}</p>
                </div>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border {{ $statusCls }}">
                    {{ $module['status'] }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-4">
                @foreach($module['details'] as $detailLabel => $detailValue)
                <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $detailLabel }}</div>
                    <div class="text-sm font-semibold text-gray-800 mt-1 break-words">{{ $detailValue }}</div>
                </div>
                @endforeach
            </div>

            <div class="mt-4 flex flex-col gap-1 text-xs">
                <div class="text-gray-400">
                    Dernière exécution :
                    <span class="font-semibold text-gray-700">{{ $module['last_run'] ?: 'non détectée' }}</span>
                </div>
                <div class="text-gray-400">
                    Dernière erreur :
                    <span class="font-semibold {{ $module['last_error'] ? 'text-rose-600' : 'text-gray-500' }}">
                        {{ $module['last_error'] ?: 'aucune' }}
                    </span>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- ═══ 3-COL GRID ═══ --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6 anim-fade-up delay-50">

    {{-- ─── Infrastructure ─── --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <div class="w-6 h-6 bg-indigo-50 rounded-lg flex items-center justify-center">
                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                </svg>
            </div>
            <h2 class="font-bold text-gray-900 text-sm">Infrastructure VPS</h2>
        </div>
        <div class="divide-y divide-gray-50 text-sm">
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">PHP</span>
                <span class="font-mono font-bold text-gray-800 text-xs">{{ $infrastructure['php_version'] }}</span>
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Laravel</span>
                <span class="font-mono font-bold text-gray-800 text-xs">{{ $infrastructure['laravel_version'] }}</span>
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Environnement</span>
                @if($envIsProd)
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">production</span>
                @else
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-amber-50 text-amber-700 border-amber-100">{{ $infrastructure['environment'] }}</span>
                @endif
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Debug mode</span>
                @if($debugOn)
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-rose-50 text-rose-700 border-rose-100">ON (risque)</span>
                @else
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">OFF</span>
                @endif
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Timezone</span>
                <span class="font-mono text-gray-700 text-xs">{{ $infrastructure['timezone'] }}</span>
            </div>
            <div class="px-5 py-3">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-500 text-sm">Disque</span>
                    <span class="text-xs font-semibold text-gray-600">{{ $diskPct }}% · {{ $diskFreeHuman }} libre</span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                    @if($diskPct >= 90)
                    <div class="h-2 rounded-full bg-rose-500 w-0 transition-all duration-700" data-pct="{{ $diskPct }}"></div>
                    @elseif($diskPct >= 70)
                    <div class="h-2 rounded-full bg-amber-500 w-0 transition-all duration-700" data-pct="{{ $diskPct }}"></div>
                    @else
                    <div class="h-2 rounded-full bg-emerald-500 w-0 transition-all duration-700" data-pct="{{ $diskPct }}"></div>
                    @endif
                </div>
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Mémoire PHP</span>
                <span class="text-gray-700 text-xs font-mono font-bold">{{ $memoryUsageMb }} / {{ $memoryLimitMb }} MB</span>
            </div>
        </div>
    </div>

    {{-- ─── Services ─── --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <div class="w-6 h-6 bg-emerald-50 rounded-lg flex items-center justify-center">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <h2 class="font-bold text-gray-900 text-sm">Services connectés</h2>
        </div>
        <div class="divide-y divide-gray-50 text-sm">

            {{-- DB --}}
            <div class="px-5 py-3.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        @if($dbOk)
                        <div class="w-2 h-2 rounded-full bg-emerald-500 status-dot shrink-0"></div>
                        @else
                        <div class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></div>
                        @endif
                        <span class="font-semibold text-gray-800">Base de données</span>
                    </div>
                    @if($dbOk)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">OK</span>
                    @else
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-rose-50 text-rose-700 border-rose-100">KO</span>
                    @endif
                </div>
                @if($dbError)
                <p class="mt-1.5 text-xs font-mono text-rose-600">{{ $dbError }}</p>
                @else
                <p class="mt-1 text-xs text-gray-400">driver: {{ config('database.default') }}</p>
                @endif
            </div>

            {{-- Cache --}}
            <div class="px-5 py-3.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        @if($cacheOk)
                        <div class="w-2 h-2 rounded-full bg-emerald-500 status-dot shrink-0"></div>
                        @else
                        <div class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></div>
                        @endif
                        <span class="font-semibold text-gray-800">Cache</span>
                    </div>
                    @if($cacheOk)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">OK</span>
                    @else
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-rose-50 text-rose-700 border-rose-100">KO</span>
                    @endif
                </div>
                @if($cacheError)
                <p class="mt-1.5 text-xs font-mono text-rose-600">{{ $cacheError }}</p>
                @else
                <p class="mt-1 text-xs text-gray-400">driver: {{ config('cache.default') }}</p>
                @endif
            </div>

            {{-- Queue --}}
            <div class="px-5 py-3.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        @if($queueAsync)
                        <div class="w-2 h-2 rounded-full bg-emerald-500 status-dot shrink-0"></div>
                        @else
                        <div class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></div>
                        @endif
                        <span class="font-semibold text-gray-800">Queue worker</span>
                    </div>
                    @if($queueAsync)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">{{ $queueDriver }}</span>
                    @else
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-amber-50 text-amber-700 border-amber-100">{{ $queueDriver }}</span>
                    @endif
                </div>
                @if($queueDriver === 'sync')
                <p class="mt-1.5 text-xs text-amber-600">Mode sync — les jobs s'exécutent en ligne, pas en background.</p>
                @else
                <p class="mt-1 text-xs text-gray-400">Vérifier que <code class="bg-gray-100 px-1 py-0.5 rounded">queue:work</code> tourne en background.</p>
                @endif
            </div>

            {{-- OpenAI --}}
            <div class="px-5 py-3.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        @if($openaiOk)
                        <div class="w-2 h-2 rounded-full bg-emerald-500 status-dot shrink-0"></div>
                        @elseif($openaiKey)
                        <div class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></div>
                        @else
                        <div class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></div>
                        @endif
                        <span class="font-semibold text-gray-800">OpenAI</span>
                    </div>
                    @if(!$openaiKey)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-rose-50 text-rose-700 border-rose-100">Clé manquante</span>
                    @elseif($openaiPing && ($openaiPing['ok'] ?? false))
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">
                        Joignable · {{ $openaiPing['ms'] ?? 0 }}ms
                    </span>
                    @elseif($openaiPing)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-rose-50 text-rose-700 border-rose-100">
                        KO · {{ $openaiPing['ms'] ?? 0 }}ms
                    </span>
                    @else
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-gray-100 text-gray-600 border-gray-200">Clé ok</span>
                    @endif
                </div>
                @if($openaiPing && !($openaiPing['ok'] ?? true) && !empty($openaiPing['error']))
                <p class="mt-1.5 text-xs font-mono text-rose-600">{{ $openaiPing['error'] }}</p>
                @else
                <p class="mt-1 text-xs text-gray-400">Modèle : {{ $openaiModel }}</p>
                @endif
            </div>

            {{-- GSC --}}
            <div class="px-5 py-3.5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    @if($gscEnabled)
                    <div class="w-2 h-2 rounded-full bg-emerald-500 status-dot shrink-0"></div>
                    @else
                    <div class="w-2 h-2 rounded-full bg-gray-300 shrink-0"></div>
                    @endif
                    <span class="font-semibold text-gray-800">Search Console</span>
                </div>
                @if($gscEnabled)
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">Activé</span>
                @else
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-gray-100 text-gray-500 border-gray-200">Désactivé</span>
                @endif
            </div>

            {{-- Embeddings --}}
            <div class="px-5 py-3.5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    @if($embeddingsEnabled)
                    <div class="w-2 h-2 rounded-full bg-emerald-500 status-dot shrink-0"></div>
                    @else
                    <div class="w-2 h-2 rounded-full bg-gray-300 shrink-0"></div>
                    @endif
                    <span class="font-semibold text-gray-800">Embeddings</span>
                </div>
                @if($embeddingsEnabled)
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">Activé</span>
                @else
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-gray-100 text-gray-500 border-gray-200">Désactivé</span>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── Scheduler ─── --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-violet-50 rounded-lg flex items-center justify-center">
                    <svg class="w-3.5 h-3.5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="font-bold text-gray-900 text-sm">Scheduler SEO</h2>
            </div>
            @if($schedulerEnabled)
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">Activé</span>
            @else
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-rose-50 text-rose-700 border-rose-100">Désactivé</span>
            @endif
        </div>

        @if(!$schedulerEnabled)
        <div class="px-5 py-5 text-sm text-rose-600">
            Désactivé dans <code class="bg-rose-50 px-1 py-0.5 rounded text-xs">seo-engine.scheduler.enabled</code>. Les commandes nocturnes ne s'exécutent pas.
        </div>
        @elseif(empty($schedulerCommands))
        <div class="px-5 py-5 text-sm text-amber-600">
            Aucune commande dans <code class="bg-amber-50 px-1 py-0.5 rounded text-xs">seo-engine.scheduler.commands</code>.
        </div>
        @else
        <div class="px-4 py-3 bg-amber-50 border-b border-amber-100">
            <p class="text-xs text-amber-700">
                <strong>Important :</strong> vérifier que <code class="font-mono">* * * * * php artisan schedule:run</code> est dans le crontab VPS.
            </p>
        </div>
        <div class="divide-y divide-gray-50 text-xs">
            @foreach($schedulerCommands as $cmd)
            @php
                $cmdName = is_array($cmd) ? ($cmd['command'] ?? (string)$cmd) : (string)$cmd;
                $cmdTime = is_array($cmd) ? ($cmd['at'] ?? null) : null;
            @endphp
            <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                <span class="font-mono text-gray-700 truncate">{{ $cmdName }}</span>
                @if($cmdTime)
                <span class="shrink-0 text-gray-400 font-semibold">{{ $cmdTime }}</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- ═══ SEO DOCTOR ═══ --}}
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6 anim-fade-up delay-100"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="font-bold text-gray-900">SEO Engine Doctor</h2>
            <p class="text-xs text-gray-400 mt-0.5">Contrats, configuration, bindings Laravel</p>
        </div>
        @if($doctorResult['ok'])
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">OK</span>
        @else
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold border bg-rose-50 text-rose-700 border-rose-100">{{ count($doctorResult['errors']) }} erreur(s)</span>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-px" style="background:#f3f4f6;">
        @foreach($doctorResult['checks'] as $check)
        @if($check['status'] === 'ok')
        <div class="bg-white px-5 py-4">
            <div class="flex items-center justify-between gap-2 mb-1.5">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></div>
                    <span class="text-sm font-semibold text-gray-900">{{ $check['label'] }}</span>
                </div>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">ok</span>
            </div>
            <p class="text-xs text-emerald-700 ml-4 leading-relaxed">{{ $check['details'] }}</p>
        </div>
        @elseif($check['status'] === 'warning')
        <div class="bg-white px-5 py-4">
            <div class="flex items-center justify-between gap-2 mb-1.5">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></div>
                    <span class="text-sm font-semibold text-gray-900">{{ $check['label'] }}</span>
                </div>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-amber-50 text-amber-700 border-amber-100">warning</span>
            </div>
            <p class="text-xs text-amber-700 ml-4 leading-relaxed">{{ $check['details'] }}</p>
        </div>
        @else
        <div class="bg-rose-50 px-5 py-4">
            <div class="flex items-center justify-between gap-2 mb-1.5">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></div>
                    <span class="text-sm font-semibold text-gray-900">{{ $check['label'] }}</span>
                </div>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold border bg-rose-50 text-rose-700 border-rose-100">{{ $check['status'] }}</span>
            </div>
            <p class="text-xs text-rose-700 ml-4 leading-relaxed">{{ $check['details'] }}</p>
        </div>
        @endif
        @endforeach
    </div>

    @if(!empty($doctorResult['warnings']))
    <div class="px-5 py-4 border-t border-gray-100 space-y-1.5">
        @foreach($doctorResult['warnings'] as $warning)
        <div class="flex items-start gap-2 text-xs text-amber-700">
            <span class="mt-0.5 shrink-0">⚠</span>
            <span>{{ $warning }}</span>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- ═══ LOG ERRORS / WARNINGS ═══ --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6 anim-fade-up delay-150">

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-gray-900">Erreurs récentes</h2>
                <p class="text-xs text-gray-400 mt-0.5">50 dernières lignes ERROR dans laravel.log</p>
            </div>
            @if(!empty($logErrors))
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold bg-rose-50 text-rose-700 border border-rose-100">{{ count($logErrors) }}</span>
            @endif
        </div>
        @if(empty($logErrors))
        <div class="px-5 py-10 text-center text-sm text-gray-400">Aucune erreur récente dans le log. ✓</div>
        @else
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            @foreach($logErrors as $line)
            <div class="px-5 py-2.5">
                <p class="text-xs font-mono text-rose-700 leading-relaxed break-all">{{ $line }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
         style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-gray-900">Avertissements récents</h2>
                <p class="text-xs text-gray-400 mt-0.5">20 dernières lignes WARNING dans laravel.log</p>
            </div>
            @if(!empty($logWarnings))
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold bg-amber-50 text-amber-700 border border-amber-100">{{ count($logWarnings) }}</span>
            @endif
        </div>
        @if(empty($logWarnings))
        <div class="px-5 py-10 text-center text-sm text-gray-400">Aucun warning récent dans le log. ✓</div>
        @else
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            @foreach($logWarnings as $line)
            <div class="px-5 py-2.5">
                <p class="text-xs font-mono text-amber-700 leading-relaxed break-all">{{ $line }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- ═══ VPS CHECKLIST ═══ --}}
<div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-200"
     style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-bold text-gray-900">Checklist VPS</h2>
        <p class="text-xs text-gray-400 mt-0.5">À vérifier manuellement en SSH — non vérifiable depuis PHP</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-px" style="background:#f3f4f6;">
        @foreach([
            ['Crontab actif',                '* * * * * php artisan schedule:run >> /dev/null 2>&1', 'crontab -l'],
            ['Queue worker en background',   'php artisan queue:work --daemon',                       'ps aux | grep queue'],
            ['Logs rotatifs configurés',     'logrotate ou daily dans logging.php',                   'cat config/logging.php'],
            ['Permissions storage/',         'chmod -R 775 storage/ bootstrap/cache/',               'ls -la storage/'],
            ['APP_KEY défini',               'php artisan key:generate si vide',                      'php artisan key:generate --show'],
            ['.env production',              'APP_ENV=production, APP_DEBUG=false',                   'cat .env | grep APP_'],
            ['Migrations à jour',            'php artisan migrate --force',                           'php artisan migrate:status'],
            ['OpenAI key dans .env',         'OPENAI_API_KEY=sk-...',                                 'grep OPENAI .env'],
        ] as [$label, $action, $cmd])
        <div class="bg-white px-5 py-4 hover:bg-gray-50/60 transition-colors">
            <div class="flex items-start gap-3">
                <div class="w-5 h-5 rounded border-2 border-gray-200 hover:border-indigo-400 mt-0.5 shrink-0 transition-colors cursor-pointer"></div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-gray-900">{{ $label }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ $action }}</div>
                    <code class="inline-block mt-1 text-xs font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded-lg">{{ $cmd }}</code>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-pct]').forEach(function (el) {
    var pct = el.getAttribute('data-pct');
    setTimeout(function () { el.style.width = pct + '%'; }, 150);
});
</script>
@endpush
