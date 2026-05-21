@extends('admin.layout')

@section('title', 'System Status')

@section('breadcrumb')
    <span class="font-medium text-gray-900">System Status</span>
@endsection

@section('content')

@php
    $ok   = fn(bool $v) => $v ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700';
    $okDot = fn(bool $v) => $v ? 'bg-emerald-500' : 'bg-rose-500';
    $fmt  = fn(int $bytes) => $bytes > 1073741824
        ? round($bytes / 1073741824, 1).' GB'
        : round($bytes / 1048576).' MB';
@endphp

{{-- Page title --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900">System Status</h1>
        <p class="text-sm text-gray-500 mt-0.5">État réel du VPS, des services, et du moteur SEO — {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
    <a href="{{ route('admin.system') }}"
       class="flex items-center gap-2 px-4 py-2 border border-gray-200 text-gray-600 hover:border-gray-300 text-sm rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Rafraîchir
    </a>
</div>

{{-- Global doctor status --}}
@php $doctorOk = $doctorResult['ok'] && $dbOk && $cacheOk; @endphp
<div class="rounded-2xl border {{ $doctorOk ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }} px-6 py-5 mb-6 flex items-center gap-4">
    <div class="w-12 h-12 rounded-full {{ $doctorOk ? 'bg-emerald-500' : 'bg-rose-500' }} flex items-center justify-center flex-shrink-0">
        @if($doctorOk)
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
        @else
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        @endif
    </div>
    <div>
        <div class="font-bold text-lg {{ $doctorOk ? 'text-emerald-800' : 'text-rose-800' }}">
            {{ $doctorOk ? 'Tout opérationnel' : 'Problèmes détectés' }}
        </div>
        <div class="text-sm {{ $doctorOk ? 'text-emerald-700' : 'text-rose-700' }} mt-0.5">
            {{ count($doctorResult['errors']) }} erreur(s) · {{ count($doctorResult['warnings']) }} avertissement(s)
            @if(!$dbOk) · DB KO @endif
            @if(!$cacheOk) · Cache KO @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

    {{-- Infrastructure --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900 text-sm">Infrastructure VPS</h2>
        </div>
        <div class="divide-y divide-gray-50 text-sm">
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">PHP</span>
                <span class="font-mono font-medium text-gray-800">{{ $infrastructure['php_version'] }}</span>
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Laravel</span>
                <span class="font-mono font-medium text-gray-800">{{ $infrastructure['laravel_version'] }}</span>
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Environnement</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $infrastructure['environment'] === 'production' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $infrastructure['environment'] }}
                </span>
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Debug mode</span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $infrastructure['debug_mode'] ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                    {{ $infrastructure['debug_mode'] ? 'ON (risque)' : 'OFF' }}
                </span>
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Timezone</span>
                <span class="font-mono text-gray-700 text-xs">{{ $infrastructure['timezone'] }}</span>
            </div>
            <div class="px-5 py-3">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-gray-500">Disque</span>
                    <span class="text-xs text-gray-600">{{ $diskUsedPct }}% utilisé · {{ $fmt($diskFree) }} libre</span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full {{ $diskUsedPct >= 90 ? 'bg-rose-500' : ($diskUsedPct >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                         style="width: {{ $diskUsedPct }}%"></div>
                </div>
            </div>
            <div class="px-5 py-3 flex justify-between items-center">
                <span class="text-gray-500">Mémoire PHP</span>
                <span class="text-gray-700 text-xs font-mono">{{ $memoryUsageMb }} MB / {{ $memoryLimitMb }} MB</span>
            </div>
        </div>
    </div>

    {{-- Services --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900 text-sm">Services connectés</h2>
        </div>
        <div class="divide-y divide-gray-50 text-sm">

            {{-- Database --}}
            <div class="px-5 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $dbOk ? 'bg-emerald-500' : 'bg-rose-500' }}"></div>
                        <span class="font-medium text-gray-800">Base de données</span>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ok($dbOk) }}">
                        {{ $dbOk ? 'OK' : 'KO' }}
                    </span>
                </div>
                @if($dbError)
                    <p class="mt-1.5 text-xs text-rose-600 font-mono">{{ $dbError }}</p>
                @else
                    <p class="mt-1 text-xs text-gray-400">driver: {{ config('database.default') }}</p>
                @endif
            </div>

            {{-- Cache --}}
            <div class="px-5 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $cacheOk ? 'bg-emerald-500' : 'bg-rose-500' }}"></div>
                        <span class="font-medium text-gray-800">Cache</span>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ok($cacheOk) }}">
                        {{ $cacheOk ? 'OK' : 'KO' }}
                    </span>
                </div>
                @if($cacheError)
                    <p class="mt-1.5 text-xs text-rose-600 font-mono">{{ $cacheError }}</p>
                @else
                    <p class="mt-1 text-xs text-gray-400">driver: {{ config('cache.default') }}</p>
                @endif
            </div>

            {{-- Queue --}}
            <div class="px-5 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $queueDriver !== 'sync' ? 'bg-emerald-500' : 'bg-amber-500' }}"></div>
                        <span class="font-medium text-gray-800">Queue worker</span>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $queueDriver !== 'sync' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $queueDriver }}
                    </span>
                </div>
                @if($queueDriver === 'sync')
                    <p class="mt-1.5 text-xs text-amber-600">Mode sync — les jobs s'exécutent en ligne, pas en background.</p>
                @else
                    <p class="mt-1 text-xs text-gray-400">Vérifier que <code>php artisan queue:work</code> tourne en background sur le VPS.</p>
                @endif
            </div>

            {{-- OpenAI --}}
            <div class="px-5 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $openaiKey && ($openaiPing['ok'] ?? false) ? 'bg-emerald-500' : ($openaiKey ? 'bg-amber-500' : 'bg-rose-500') }}"></div>
                        <span class="font-medium text-gray-800">OpenAI</span>
                    </div>
                    @if(!$openaiKey)
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-rose-100 text-rose-700">Clé manquante</span>
                    @elseif($openaiPing)
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $ok($openaiPing['ok']) }}">
                            {{ $openaiPing['ok'] ? 'Joignable' : 'KO' }} · {{ $openaiPing['ms'] }}ms
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-600">Clé ok, non testé</span>
                    @endif
                </div>
                @if($openaiPing && !$openaiPing['ok'] && $openaiPing['error'])
                    <p class="mt-1.5 text-xs text-rose-600 font-mono">{{ $openaiPing['error'] }}</p>
                @else
                    <p class="mt-1 text-xs text-gray-400">Modèle configuré : {{ $openaiModel }}</p>
                @endif
            </div>

            {{-- GSC --}}
            <div class="px-5 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $gscEnabled ? 'bg-emerald-500' : 'bg-gray-300' }}"></div>
                        <span class="font-medium text-gray-800">Search Console</span>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $gscEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $gscEnabled ? 'Activé' : 'Désactivé' }}
                    </span>
                </div>
            </div>

            {{-- Embeddings --}}
            <div class="px-5 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $embeddingsEnabled ? 'bg-emerald-500' : 'bg-gray-300' }}"></div>
                        <span class="font-medium text-gray-800">Embeddings sémantiques</span>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $embeddingsEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $embeddingsEnabled ? 'Activé' : 'Désactivé' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Scheduler --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900 text-sm">Scheduler SEO</h2>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $schedulerEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                {{ $schedulerEnabled ? 'Activé' : 'Désactivé' }}
            </span>
        </div>

        @if(!$schedulerEnabled)
        <div class="px-5 py-4 text-sm text-rose-600">
            Le scheduler est désactivé dans <code>seo-engine.scheduler.enabled</code>. Les commandes nocturnes ne s'exécutent pas.
        </div>
        @elseif(empty($schedulerCommands))
        <div class="px-5 py-4 text-sm text-amber-600">
            Aucune commande configurée dans <code>seo-engine.scheduler.commands</code>.
        </div>
        @else
        <div class="px-4 py-3 bg-amber-50 border-b border-amber-100">
            <p class="text-xs text-amber-700">
                <strong>Important :</strong> Vérifier que <code>* * * * * php artisan schedule:run</code> est dans le crontab du VPS.
                Sans cela, aucune commande ci-dessous ne s'exécute.
            </p>
        </div>
        <div class="divide-y divide-gray-50 text-xs">
            @foreach($schedulerCommands as $cmd)
            @php
                $name = is_array($cmd) ? ($cmd['command'] ?? (string)$cmd) : (string)$cmd;
                $time = is_array($cmd) ? ($cmd['at'] ?? null) : null;
            @endphp
            <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                <span class="font-mono text-gray-700 truncate">{{ $name }}</span>
                @if($time)
                    <span class="flex-shrink-0 text-gray-400">{{ $time }}</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- SEO Doctor checks --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-6">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="font-semibold text-gray-900 text-sm">SEO Engine Doctor</h2>
            <p class="text-xs text-gray-500 mt-0.5">Contrats, configuration, bindings Laravel</p>
        </div>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $doctorResult['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
            {{ $doctorResult['ok'] ? 'OK' : count($doctorResult['errors']).' erreur(s)' }}
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-px bg-gray-100">
        @foreach($doctorResult['checks'] as $check)
        @php
            $tone = match($check['status']) {
                'ok'      => ['dot' => 'bg-emerald-500', 'bg' => 'bg-white', 'text' => 'text-emerald-700', 'badge' => 'bg-emerald-100 text-emerald-700'],
                'warning' => ['dot' => 'bg-amber-400',   'bg' => 'bg-white', 'text' => 'text-amber-700',   'badge' => 'bg-amber-100 text-amber-700'],
                default   => ['dot' => 'bg-rose-500',    'bg' => 'bg-rose-50', 'text' => 'text-rose-700',  'badge' => 'bg-rose-100 text-rose-700'],
            };
        @endphp
        <div class="{{ $tone['bg'] }} px-5 py-4">
            <div class="flex items-center justify-between gap-2 mb-1">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full {{ $tone['dot'] }} flex-shrink-0"></div>
                    <span class="text-sm font-medium text-gray-900">{{ $check['label'] }}</span>
                </div>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $tone['badge'] }}">{{ $check['status'] }}</span>
            </div>
            <p class="text-xs {{ $tone['text'] }} ml-4 leading-relaxed">{{ $check['details'] }}</p>
        </div>
        @endforeach
    </div>

    @if(!empty($doctorResult['warnings']))
    <div class="px-5 py-4 border-t border-gray-100 space-y-1.5">
        @foreach($doctorResult['warnings'] as $warning)
        <div class="flex items-start gap-2 text-xs text-amber-700">
            <span class="mt-0.5 text-amber-400 flex-shrink-0">⚠</span>
            <span>{{ $warning }}</span>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- Recent log errors --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-900 text-sm">Erreurs récentes (laravel.log)</h2>
                <p class="text-xs text-gray-500 mt-0.5">Les 50 dernières lignes ERROR dans le log</p>
            </div>
            @if(!empty($logErrors))
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-rose-100 text-rose-700">
                {{ count($logErrors) }}
            </span>
            @endif
        </div>

        @if(empty($logErrors))
        <div class="px-5 py-8 text-center text-sm text-gray-400">
            Aucune erreur récente dans le log. ✓
        </div>
        @else
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            @foreach($logErrors as $line)
            <div class="px-5 py-2.5">
                <p class="text-[11px] font-mono text-rose-700 leading-relaxed break-all">{{ $line }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-900 text-sm">Avertissements récents (laravel.log)</h2>
                <p class="text-xs text-gray-500 mt-0.5">Les 20 dernières lignes WARNING dans le log</p>
            </div>
            @if(!empty($logWarnings))
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-amber-100 text-amber-700">
                {{ count($logWarnings) }}
            </span>
            @endif
        </div>

        @if(empty($logWarnings))
        <div class="px-5 py-8 text-center text-sm text-gray-400">
            Aucun warning récent dans le log. ✓
        </div>
        @else
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            @foreach($logWarnings as $line)
            <div class="px-5 py-2.5">
                <p class="text-[11px] font-mono text-amber-700 leading-relaxed break-all">{{ $line }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Checklist VPS --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900 text-sm">Checklist VPS — à vérifier manuellement</h2>
        <p class="text-xs text-gray-500 mt-0.5">Ces éléments ne peuvent pas être vérifiés depuis PHP — à contrôler en SSH</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-px bg-gray-100">
        @foreach([
            ['Crontab actif', '* * * * * php artisan schedule:run >> /dev/null 2>&1', 'crontab -l'],
            ['Queue worker en background', 'php artisan queue:work --daemon', 'ps aux | grep queue'],
            ['Logs rotatifs configurés', 'logrotate ou daily dans logging.php', 'cat config/logging.php'],
            ['Permissions storage/', 'chmod -R 775 storage/ bootstrap/cache/', 'ls -la storage/'],
            ['APP_KEY défini', 'php artisan key:generate si vide', 'php artisan key:generate --show'],
            ['.env production', 'APP_ENV=production, APP_DEBUG=false', 'cat .env | grep APP_'],
            ['Migrations à jour', 'php artisan migrate --force', 'php artisan migrate:status'],
            ['OpenAI key dans .env', 'OPENAI_API_KEY=sk-...', 'grep OPENAI .env'],
        ] as [$label, $action, $cmd])
        <div class="bg-white px-5 py-4">
            <div class="flex items-start gap-3">
                <div class="w-5 h-5 rounded border-2 border-gray-300 mt-0.5 flex-shrink-0"></div>
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ $label }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ $action }}</div>
                    <code class="inline-block mt-1 text-[11px] font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $cmd }}</code>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

@endsection
