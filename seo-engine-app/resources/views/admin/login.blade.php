<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SEO Engine — Connexion</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-900 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-indigo-600 rounded-xl mb-4">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">SEO Engine</h1>
            <p class="text-gray-400 text-sm mt-1">Administration</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-8">
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-6 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.post') }}">
                @csrf
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mot de passe</label>
                    <input type="password" name="password" autofocus
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        placeholder="••••••••">
                </div>
                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg px-4 py-2.5 text-sm transition-colors">
                    Se connecter
                </button>
            </form>
        </div>
    </div>
</body>
</html>
