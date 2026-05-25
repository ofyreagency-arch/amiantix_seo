@extends('admin.layout')

@section('title', 'Connecter '.$site->name)

@section('breadcrumb')
    <a href="{{ route('admin.sites.index') }}" class="hover:text-gray-700 transition-colors">Sites</a>
    <span class="mx-2 text-gray-300">›</span>
    <a href="{{ route('admin.sites.show', $site->site_id) }}" class="hover:text-gray-700 transition-colors">{{ $site->name }}</a>
    <span class="mx-2 text-gray-300">›</span>
    <span class="font-semibold text-gray-900">Connecter mon site</span>
@endsection

@section('content')
@php
    $officialBridge = in_array($site->resolvedPublicationMode(), ['laravel_bridge', 'symfony_bridge'], true);
@endphp

<div class="max-w-5xl mx-auto space-y-6 anim-fade-up">
    <div class="rounded-3xl border border-gray-100 overflow-hidden"
         style="background:linear-gradient(135deg,#f8f9ff 0%,#ffffff 100%);box-shadow:0 6px 24px rgba(15,23,42,0.06);">
        <div class="px-7 py-7 md:px-8 md:py-8 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="max-w-2xl">
                <div class="inline-flex items-center rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">
                    Connecter mon site
                </div>
                <h1 class="mt-4 text-2xl md:text-3xl font-black text-gray-900">Télécharger l’installateur PraeviSEO</h1>
                <p class="mt-3 text-sm md:text-base text-gray-500 leading-7">
                    Le client télécharge un script officiel, le lance, colle le code de connexion, et PraeviSEO active ensuite
                    le bridge, la publication distante et le monitoring.
                </p>
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                        {{ $site->resolvedPublicationModeLabel() }}
                    </span>
                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-bold text-gray-600">
                        {{ $installerVersion }}
                    </span>
                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-bold text-gray-600">
                        {{ $site->url }}
                    </span>
                </div>
            </div>

            <div class="w-full max-w-md rounded-2xl border border-gray-100 bg-white/90 px-5 py-5"
                 style="box-shadow:0 2px 10px rgba(15,23,42,0.04);">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-gray-400">Code de connexion</div>
                <div class="mt-2 text-2xl font-black text-gray-900 tracking-wide">{{ $site->publicationConnectCode() ?: '—' }}</div>
                <p class="mt-2 text-xs text-gray-500 leading-6">
                    Le script demandera simplement ce code et, si besoin, le chemin du projet à connecter.
                </p>
                <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-gray-400">État actuel</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">
                        {{ $site->publicationBridgeStatus() === 'connected' ? 'Site connecté ✅' : 'En attente de connexion' }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Publication active et monitoring réel seront activés dès que le bridge terminera la première connexion.
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(! $officialBridge)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
        Ce site n’utilise pas encore un bridge Laravel ou Symfony officiel. Sélectionnez d’abord un connecteur officiel dans la fiche site si vous voulez utiliser l’installateur script.
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($installers as $installer)
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
             style="box-shadow:0 2px 12px rgba(15,23,42,0.04);">
            <div class="px-6 py-5 border-b border-gray-100">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-gray-900">{{ $installer['label'] }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $installer['description'] }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-xs font-bold text-gray-600">
                        {{ $installer['filename'] }}
                    </span>
                </div>
            </div>
            <div class="px-6 py-5 space-y-4">
                <a href="{{ route('admin.sites.connect.installer', [$site->site_id, $installer['platform']]) }}"
                   class="inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-bold text-white transition-all hover:-translate-y-0.5"
                   style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 6px 16px rgba(99,102,241,0.24);">
                    Télécharger l’installateur {{ $installer['label'] }}
                </a>
                <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-4">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-gray-400">Lancement</div>
                    <code class="mt-2 block text-sm font-semibold text-gray-800">{{ $installer['command'] }}</code>
                    <p class="mt-2 text-xs text-gray-500">
                        Le script vérifie PHP, Composer, détecte automatiquement Laravel ou Symfony, installe le bon bridge,
                        configure la connexion, puis teste le runtime.
                    </p>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden"
         style="box-shadow:0 2px 12px rgba(15,23,42,0.04);">
        <div class="px-6 py-5 border-b border-gray-100">
            <h2 class="text-lg font-black text-gray-900">Ce que verra le client</h2>
            <p class="mt-1 text-sm text-gray-500">Le flow reste volontairement simple et honnête.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-px" style="background:#f3f4f6;">
            @foreach([
                ['step' => '1', 'title' => 'Télécharger', 'text' => 'Le client télécharge le script officiel depuis PraeviSEO.'],
                ['step' => '2', 'title' => 'Lancer', 'text' => 'Le script détecte le framework, vérifie PHP / Composer puis installe le bridge.'],
                ['step' => '3', 'title' => 'Connecter', 'text' => 'Le client colle le code de connexion. Le script termine la connexion et vérifie le runtime.'],
            ] as $step)
            <div class="bg-white px-6 py-5">
                <div class="w-9 h-9 rounded-2xl flex items-center justify-center text-sm font-black text-white"
                     style="background:linear-gradient(135deg,#111827,#374151);">
                    {{ $step['step'] }}
                </div>
                <div class="mt-4 text-sm font-black text-gray-900">{{ $step['title'] }}</div>
                <p class="mt-2 text-sm text-gray-500 leading-6">{{ $step['text'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
