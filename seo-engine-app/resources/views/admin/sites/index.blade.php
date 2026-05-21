@extends('admin.layout')

@section('title', 'Sites clients')

@section('breadcrumb')
    <span class="font-medium text-gray-900">Sites clients</span>
@endsection

@section('content')
<div class="flex items-start gap-6">

    {{-- Sites list --}}
    <div class="flex-1 bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Tous les sites</h2>
            <span class="text-xs text-gray-400">{{ $sites->count() }} site(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Nom</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">URL</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Niche</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Preset</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Locale</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">GSC</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Statut</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($sites as $site)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.sites.show', $site->site_id) }}"
                               class="font-medium text-indigo-600 hover:text-indigo-700">{{ $site->name }}</a>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $site->site_id }}</div>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs">{{ $site->url }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $site->niche }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $site->preset ?? 'generic' }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $site->locale }}</td>
                        <td class="px-6 py-4">
                            @php
                                $gscStatus = $site->resolvedGscConnectionStatus();
                                $gscTone = match ($gscStatus) {
                                    'connected' => 'bg-emerald-100 text-emerald-700',
                                    'configured' => 'bg-amber-100 text-amber-700',
                                    'failed', 'unauthorized' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-gray-100 text-gray-500',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $gscTone }}">
                                {{ str_replace('_', ' ', $gscStatus) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($site->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Actif</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.sites.show', $site->site_id) }}"
                                   class="text-xs text-gray-600 hover:text-gray-900 px-3 py-1.5 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors">
                                    Pages
                                </a>
                                <form method="POST" action="{{ route('admin.sites.rotate-token', $site->site_id) }}" class="inline">
                                    @csrf
                                    <button type="submit" onclick="return confirm('Rotation du token ? L\'ancien sera invalide immédiatement.')"
                                        class="text-xs text-amber-600 hover:text-amber-700 px-3 py-1.5 border border-amber-200 rounded-lg hover:border-amber-300 transition-colors">
                                        Rotation token
                                    </button>
                                </form>
                                @if($site->is_active)
                                <form method="POST" action="{{ route('admin.sites.destroy', $site->site_id) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" onclick="return confirm('Désactiver ce site ?')"
                                        class="text-xs text-red-500 hover:text-red-600 px-3 py-1.5 border border-red-200 rounded-lg hover:border-red-300 transition-colors">
                                        Désactiver
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                            Aucun site configuré. Ajoutez votre premier site ci-contre.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add site form --}}
    <div class="w-80 flex-shrink-0 bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Ajouter un site</h2>
        </div>
        <form method="POST" action="{{ route('admin.sites.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-600 rounded-lg px-3 py-2 text-xs">
                    {{ $errors->first() }}
                </div>
            @endif
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Identifiant (slug)</label>
                <input type="text" name="site_id" value="{{ old('site_id') }}" placeholder="mon-site"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Nom</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Mon Site"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">URL</label>
                <input type="url" name="url" value="{{ old('url') }}" placeholder="https://monsite.com"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Niche</label>
                <input type="text" name="niche" value="{{ old('niche') }}" placeholder="ex: immobilier"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Locale</label>
                <input type="text" name="locale" value="{{ old('locale', 'fr') }}" placeholder="fr"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Preset</label>
                <select name="preset"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    @foreach($availablePresets as $presetKey => $presetLabel)
                        <option value="{{ $presetKey }}" @selected(old('preset', old('niche') === 'amiante' ? 'amiantix' : 'generic') === $presetKey)>{{ $presetLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">Webhook URL (optionnel)</label>
                <input type="url" name="webhook_url" value="{{ old('webhook_url') }}" placeholder="https://monsite.com/seo-webhook"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <div class="pt-1 border-t border-gray-100">
                <div class="text-xs font-semibold text-gray-700 mb-3">Search Console (optionnel)</div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Mode de connexion</label>
                        <select name="gsc_connection_mode"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option value="">Aucune connexion</option>
                            <option value="service_account" @selected(old('gsc_connection_mode') === 'service_account')>Service account</option>
                            <option value="oauth_google" @selected(old('gsc_connection_mode') === 'oauth_google')>OAuth Google</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Propriété GSC</label>
                        <input type="text" name="gsc_property_url" value="{{ old('gsc_property_url') }}" placeholder="sc-domain:monsite.com"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Chemin credentials</label>
                        <input type="text" name="gsc_credentials_path" value="{{ old('gsc_credentials_path') }}" placeholder="/var/www/seo-engine/seo-engine-app/storage/google/site.json"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Compte Google</label>
                        <input type="email" name="gsc_account_email" value="{{ old('gsc_account_email') }}" placeholder="service-account@project.iam.gserviceaccount.com"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
            </div>
            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg px-4 py-2.5 text-sm transition-colors">
                Créer le site
            </button>
        </form>
    </div>

</div>
@endsection
