@extends('admin.layout')

@section('title', 'Sites clients')

@section('breadcrumb')
    <span class="font-semibold text-gray-900">Sites clients</span>
@endsection

@section('content')
<div class="flex items-start gap-6">

    {{-- ═══ SITES TABLE ═══ --}}
    <div class="flex-1 min-w-0">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Sites clients</h1>
                <p class="text-sm text-gray-400 mt-0.5">{{ $sites->count() }} site(s) configuré(s)</p>
            </div>
        </div>

        {{-- Table card --}}
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up"
             style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="background:#f8f9fc;">
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Site</th>
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden md:table-cell">URL</th>
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden lg:table-cell">Niche</th>
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden lg:table-cell">Preset</th>
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 hidden xl:table-cell">GSC</th>
                            <th class="text-left px-6 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Statut</th>
                            <th class="text-right px-6 py-3.5 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($sites as $site)
                        <tr class="hover:bg-gray-50/60 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 font-bold text-sm"
                                         style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;">
                                        {{ strtoupper(substr($site->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.sites.show', $site->site_id) }}"
                                           class="font-semibold text-gray-900 hover:text-indigo-600 transition-colors">
                                            {{ $site->name }}
                                        </a>
                                        <div class="text-xs text-gray-400 font-mono mt-0.5">{{ $site->site_id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <span class="text-xs text-gray-400 font-mono">{{ $site->url }}</span>
                            </td>
                            <td class="px-6 py-4 hidden lg:table-cell">
                                <span class="text-sm text-gray-600">{{ $site->niche }}</span>
                            </td>
                            <td class="px-6 py-4 hidden lg:table-cell">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700">
                                    {{ $site->preset ?? 'generic' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 hidden xl:table-cell">
                                @php
                                    $gscStatus = $site->resolvedGscConnectionStatus();
                                    $gscCls = match($gscStatus) {
                                        'connected'                  => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                        'configured'                 => 'bg-amber-50 text-amber-700 border-amber-100',
                                        'failed', 'unauthorized'     => 'bg-rose-50 text-rose-700 border-rose-100',
                                        default                      => 'bg-gray-100 text-gray-500 border-gray-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $gscCls }}">
                                    {{ str_replace('_', ' ', $gscStatus) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($site->is_active)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 status-dot"></span>
                                    Actif
                                </span>
                                @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
                                    Inactif
                                </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.sites.show', $site->site_id) }}"
                                       class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 px-3 py-1.5 border border-indigo-100 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
                                        Voir
                                    </a>
                                    <form method="POST" action="{{ route('admin.sites.rotate-token', $site->site_id) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                onclick="return confirm('Rotation du token ? L\'ancien sera invalide immédiatement.')"
                                                class="text-xs font-semibold text-amber-600 hover:text-amber-700 px-3 py-1.5 border border-amber-100 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors">
                                            Token
                                        </button>
                                    </form>
                                    @if($site->is_active)
                                    <form method="POST" action="{{ route('admin.sites.destroy', $site->site_id) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                onclick="return confirm('Désactiver ce site ?')"
                                                class="text-xs font-semibold text-rose-500 hover:text-rose-600 px-3 py-1.5 border border-rose-100 bg-rose-50 rounded-lg hover:bg-rose-100 transition-colors">
                                            Désactiver
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                    </svg>
                                </div>
                                <div class="text-sm font-semibold text-gray-400">Aucun site configuré.</div>
                                <div class="text-xs text-gray-300 mt-1">Ajoutez votre premier site dans le formulaire →</div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ ADD SITE FORM ═══ --}}
    <div class="w-80 shrink-0">
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden anim-fade-up delay-100"
             style="box-shadow:0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b border-gray-50">
                <h2 class="font-bold text-gray-900">Ajouter un site</h2>
                <p class="text-xs text-gray-400 mt-0.5">Configurer un nouveau site client</p>
            </div>
            <form method="POST" action="{{ route('admin.sites.store') }}" class="px-6 py-5 space-y-4">
                @csrf
                @if($errors->any())
                <div class="flex items-start gap-2 bg-rose-50 border border-rose-200 text-rose-600 rounded-xl px-3 py-2.5 text-xs">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    {{ $errors->first() }}
                </div>
                @endif

                @php
                $inputCls = 'w-full border border-gray-200 bg-gray-50 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 focus:bg-white transition-all';
                $labelCls = 'block text-xs font-semibold text-gray-500 mb-1.5';
                @endphp

                <div>
                    <label class="{{ $labelCls }}">Identifiant (slug)</label>
                    <input type="text" name="site_id" value="{{ old('site_id') }}" placeholder="mon-site" class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">Nom</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Mon Site" class="{{ $inputCls }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}">URL</label>
                    <input type="url" name="url" value="{{ old('url') }}" placeholder="https://monsite.com" class="{{ $inputCls }}">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelCls }}">Niche</label>
                        <input type="text" name="niche" value="{{ old('niche') }}" placeholder="immobilier" class="{{ $inputCls }}">
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">Locale</label>
                        <input type="text" name="locale" value="{{ old('locale', 'fr') }}" placeholder="fr" class="{{ $inputCls }}">
                    </div>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Preset</label>
                    <select name="preset" class="{{ $inputCls }}">
                        @foreach($availablePresets as $presetKey => $presetLabel)
                        <option value="{{ $presetKey }}" @selected(old('preset', 'generic') === $presetKey)>{{ $presetLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Connexion site public</label>
                    <select name="publication_mode" class="{{ $inputCls }}">
                        <option value="runtime" @selected(old('publication_mode', 'runtime') === 'runtime')>Runtime interne</option>
                        <option value="laravel_bridge" @selected(old('publication_mode') === 'laravel_bridge')>Bridge Laravel officiel</option>
                        <option value="symfony_bridge" @selected(old('publication_mode') === 'symfony_bridge')>Bridge Symfony officiel</option>
                        <option value="webhook_api" @selected(old('publication_mode') === 'webhook_api')>Webhook/API avancé</option>
                        <option value="disabled" @selected(old('publication_mode') === 'disabled')>Désactivée</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Section publique <span class="text-gray-300 font-normal">(optionnel)</span></label>
                    <input type="text" name="publication_path_prefix" value="{{ old('publication_path_prefix', 'ressources') }}" placeholder="ressources" class="{{ $inputCls }}">
                </div>

                {{-- GSC Section --}}
                <div class="pt-3 border-t border-gray-100">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-4 h-4 rounded bg-indigo-100 flex items-center justify-center shrink-0">
                            <svg class="w-2.5 h-2.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-gray-600">Search Console <span class="text-gray-300 font-normal">(optionnel)</span></span>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="{{ $labelCls }}">Mode de connexion</label>
                            <select name="gsc_connection_mode" class="{{ $inputCls }}">
                                <option value="">Aucune connexion</option>
                                <option value="service_account" @selected(old('gsc_connection_mode') === 'service_account')>Service account</option>
                                <option value="oauth_google" @selected(old('gsc_connection_mode') === 'oauth_google')>OAuth Google</option>
                            </select>
                        </div>
                        <div>
                            <label class="{{ $labelCls }}">Propriété GSC</label>
                            <input type="text" name="gsc_property_url" value="{{ old('gsc_property_url') }}" placeholder="sc-domain:monsite.com" class="{{ $inputCls }}">
                        </div>
                        <div>
                            <label class="{{ $labelCls }}">Chemin credentials</label>
                            <input type="text" name="gsc_credentials_path" value="{{ old('gsc_credentials_path') }}" placeholder="/storage/google/site.json" class="{{ $inputCls }}">
                        </div>
                        <div>
                            <label class="{{ $labelCls }}">Compte Google</label>
                            <input type="email" name="gsc_account_email" value="{{ old('gsc_account_email') }}" placeholder="service@project.iam.gserviceaccount.com" class="{{ $inputCls }}">
                        </div>
                    </div>
                </div>

                <button type="submit"
                        class="w-full font-bold rounded-xl px-4 py-3 text-sm text-white transition-all hover:-translate-y-0.5"
                        style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 14px rgba(99,102,241,0.35);">
                    Créer le site →
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
