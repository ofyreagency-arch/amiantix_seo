<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — SEO Engine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
    <style>
        @media (min-width: 1024px) {
            #sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                width: 220px;
                transform: none !important;
            }

            #admin-shell {
                padding-left: 220px;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="admin-body antialiased h-full" style="font-family:'Inter',sans-serif;">

{{-- Mobile overlay --}}
<div id="sidebar-overlay" class="fixed inset-0 bg-black/30 z-20 hidden lg:hidden" onclick="toggleSidebar()"></div>

<div id="admin-shell" class="flex h-screen overflow-hidden">

    {{-- ═══════════ SIDEBAR ═══════════ --}}
    <aside id="sidebar"
           class="w-[220px] shrink-0 flex flex-col z-30 bg-white border-r border-gray-100
                  fixed inset-y-0 left-0 lg:static
                  -translate-x-full lg:translate-x-0
                  transition-transform duration-300 ease-out">

        {{-- Brand --}}
        <div class="h-14 flex items-center px-4 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 border border-slate-200 bg-slate-50 text-slate-500">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-900 leading-none tracking-tight">SEO Engine</div>
                    <div class="text-[10px] text-gray-400 mt-0.5 tracking-wide">Intelligence</div>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-4 overflow-y-auto space-y-0.5">

            @php
                $isDashboard = request()->routeIs('admin.dashboard');
                $isSites     = request()->routeIs('admin.sites.*');
                $isSystem    = request()->routeIs('admin.system');
            @endphp

            {{-- Section label --}}
            <div class="px-2 pb-1 pt-1">
                <span class="text-[10px] uppercase tracking-[0.15em] font-semibold text-gray-400">Cockpit</span>
            </div>

            <a href="{{ route('admin.dashboard') }}"
               class="group flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm transition-all duration-150
                      {{ $isDashboard ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50 font-medium' }}">
                <span class="w-6 h-6 rounded-md flex items-center justify-center shrink-0
                             {{ $isDashboard ? 'bg-indigo-100 text-indigo-600' : 'text-gray-400 group-hover:text-gray-600' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-3a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1v-7z"/>
                    </svg>
                </span>
                Dashboard
            </a>

            <a href="{{ route('admin.sites.index') }}"
               class="group flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm transition-all duration-150
                      {{ $isSites ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50 font-medium' }}">
                <span class="w-6 h-6 rounded-md flex items-center justify-center shrink-0
                             {{ $isSites ? 'bg-indigo-100 text-indigo-600' : 'text-gray-400 group-hover:text-gray-600' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                    </svg>
                </span>
                Sites clients
            </a>

            <div class="px-2 pb-1 pt-4">
                <span class="text-[10px] uppercase tracking-[0.15em] font-semibold text-gray-400">Système</span>
            </div>

            <a href="{{ route('admin.system') }}"
               class="group flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm transition-all duration-150
                      {{ $isSystem ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50 font-medium' }}">
                <span class="w-6 h-6 rounded-md flex items-center justify-center shrink-0
                             {{ $isSystem ? 'bg-indigo-100 text-indigo-600' : 'text-gray-400 group-hover:text-gray-600' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                    </svg>
                </span>
                Système
            </a>

            {{-- Runtime status widget --}}
            <div class="mt-6 mx-0.5 rounded-xl border border-gray-100 bg-gray-50 px-3 py-3">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 status-dot shrink-0"></span>
                    <span class="text-xs font-semibold text-gray-700">Runtime actif</span>
                </div>
                <div class="text-[10px] text-gray-400">{{ now()->format('d M Y · H:i') }}</div>
            </div>
        </nav>

        {{-- User + Logout --}}
        <div class="px-3 py-3 border-t border-gray-100 shrink-0">
            <div class="flex items-center gap-2.5 px-2 py-1.5 mb-1 rounded-lg">
                <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 bg-indigo-100">
                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <div class="text-xs font-semibold text-gray-800">Admin</div>
                    <div class="text-[10px] text-gray-400">Ofyre SEO Engine</div>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit"
                    class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm text-gray-500
                           hover:bg-gray-50 hover:text-gray-800 transition-all duration-150 font-medium">
                    <span class="w-6 h-6 rounded-md flex items-center justify-center shrink-0 text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </span>
                    Déconnexion
                </button>
            </form>
        </div>
    </aside>

    {{-- ═══════════ MAIN CONTENT ═══════════ --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        {{-- Top Header --}}
        <header class="h-14 bg-white border-b border-gray-100 flex items-center justify-between px-5 lg:px-6 shrink-0">

            {{-- Mobile hamburger --}}
            <button onclick="toggleSidebar()"
                    class="lg:hidden w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 transition-colors mr-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Breadcrumb --}}
            <div class="flex items-center gap-1.5 text-sm flex-1 min-w-0">
                <span class="text-gray-400 hidden sm:inline text-xs">SEO Engine</span>
                <svg class="w-3 h-3 text-gray-300 hidden sm:block shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <div class="min-w-0 text-sm text-gray-600">
                    @yield('breadcrumb')
                </div>
            </div>

            {{-- Right actions --}}
            <div class="flex items-center gap-3 shrink-0">
                <div class="hidden sm:flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 status-dot"></span>
                    <span>Runtime actif</span>
                </div>
                <div class="text-xs text-gray-400 hidden md:block">{{ now()->format('d/m/Y') }}</div>
            </div>
        </header>

        {{-- Main Scrollable Area --}}
        <main class="flex-1 overflow-y-auto p-5 lg:p-6">
            <div class="admin-main max-w-[1480px] mx-auto">

            {{-- Flash messages --}}
            @if(session('success'))
            <div class="anim-slide-down bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 mb-5 flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
            @endif

            @if(session('new_token'))
            <div class="anim-slide-down rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 mb-5">
                <div class="flex items-start gap-3">
                    <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-amber-800 mb-2">
                            Token API pour {{ session('new_token_site') }} — copiez-le maintenant
                        </p>
                        <code class="block bg-white text-amber-900 rounded-lg px-3 py-2.5 text-xs font-mono break-all select-all border border-amber-200">
                            {{ session('new_token') }}
                        </code>
                    </div>
                </div>
            </div>
            @endif

            @yield('content')
            </div>
        </main>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}
</script>

@stack('scripts')
</body>
</html>
