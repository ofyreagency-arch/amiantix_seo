<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') — SEO Engine</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js"></script>
    @stack('styles')
</head>
<body class="bg-[#f4f5f9] antialiased h-full" style="font-family:'Inter',sans-serif;">

{{-- Mobile overlay --}}
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-20 hidden lg:hidden" onclick="toggleSidebar()"></div>

<div class="flex h-screen overflow-hidden">

    {{-- ═══════════ SIDEBAR ═══════════ --}}
    <aside id="sidebar"
           class="w-[240px] shrink-0 flex flex-col z-30
                  fixed inset-y-0 left-0 lg:static
                  -translate-x-full lg:translate-x-0
                  transition-transform duration-300 ease-out"
           style="background: linear-gradient(180deg, #0d0f1c 0%, #111827 60%, #0d0f1c 100%);">

        {{-- Brand --}}
        <div class="h-16 flex items-center px-5 border-b border-white/5 shrink-0">
            <div class="flex items-center gap-3">
                <div class="relative w-8 h-8 shrink-0">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center"
                         style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                                box-shadow: 0 4px 14px rgba(99,102,241,0.5);">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                                  d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    {{-- Live dot --}}
                    <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-[#0d0f1c] status-dot"></span>
                </div>
                <div>
                    <div class="text-white font-bold text-sm tracking-tight leading-none">SEO Engine</div>
                    <div class="text-[10px] text-indigo-300/70 mt-0.5 tracking-widest uppercase">Intelligence</div>
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-5 overflow-y-auto space-y-0.5">

            {{-- Precompute active states to avoid inline Blade ternaries inside style="" --}}
            @php
                $activeStyle   = 'background:linear-gradient(135deg,rgba(99,102,241,0.25),rgba(139,92,246,0.15));';
                $isDashboard   = request()->routeIs('admin.dashboard');
                $isSites       = request()->routeIs('admin.sites.*');
                $isSystem      = request()->routeIs('admin.system');
            @endphp

            {{-- Section label --}}
            <div class="px-3 mb-2">
                <span class="text-[9px] uppercase tracking-[0.2em] font-semibold text-white/20">Cockpit</span>
            </div>

            <a href="{{ route('admin.dashboard') }}"
               class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200 {{ $isDashboard ? 'nav-active-glow text-white' : 'text-white/50 hover:text-white hover:bg-white/5' }}"
               @if($isDashboard) style="{{ $activeStyle }}" @endif>
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-colors {{ $isDashboard ? 'bg-indigo-500/30 text-indigo-300' : 'text-white/30 group-hover:text-white/60' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-3a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1v-7z"/>
                    </svg>
                </span>
                <span class="font-medium sidebar-label">Dashboard</span>
                @if($isDashboard)
                <span class="ml-auto w-1 h-4 rounded-full bg-indigo-400"></span>
                @endif
            </a>

            <a href="{{ route('admin.sites.index') }}"
               class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200 {{ $isSites ? 'nav-active-glow text-white' : 'text-white/50 hover:text-white hover:bg-white/5' }}"
               @if($isSites) style="{{ $activeStyle }}" @endif>
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-colors {{ $isSites ? 'bg-indigo-500/30 text-indigo-300' : 'text-white/30 group-hover:text-white/60' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                    </svg>
                </span>
                <span class="font-medium sidebar-label">Sites clients</span>
                @if($isSites)
                <span class="ml-auto w-1 h-4 rounded-full bg-indigo-400"></span>
                @endif
            </a>

            <div class="px-3 mt-5 mb-2">
                <span class="text-[9px] uppercase tracking-[0.2em] font-semibold text-white/20">Système</span>
            </div>

            <a href="{{ route('admin.system') }}"
               class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200 {{ $isSystem ? 'nav-active-glow text-white' : 'text-white/50 hover:text-white hover:bg-white/5' }}"
               @if($isSystem) style="{{ $activeStyle }}" @endif>
                <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-colors {{ $isSystem ? 'bg-indigo-500/30 text-indigo-300' : 'text-white/30 group-hover:text-white/60' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                    </svg>
                </span>
                <span class="font-medium sidebar-label">Système</span>
                @if(request()->routeIs('admin.system'))
                <span class="ml-auto w-1 h-4 rounded-full bg-indigo-400"></span>
                @endif
            </a>

            {{-- Quick stats widget --}}
            <div class="mt-6 mx-1 rounded-2xl p-4 border border-white/5 sidebar-label"
                 style="background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(139,92,246,0.08));">
                <div class="text-[10px] uppercase tracking-widest text-indigo-300/60 mb-2">Moteur actif</div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 status-dot"></span>
                    <span class="text-xs text-white/70">Runtime en ligne</span>
                </div>
                <div class="text-[10px] text-white/30 mt-2">{{ now()->format('d M Y · H:i') }}</div>
            </div>
        </nav>

        {{-- User + Logout --}}
        <div class="px-3 py-4 border-t border-white/5 shrink-0">
            <div class="flex items-center gap-3 px-3 py-2 rounded-xl mb-1">
                <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                     style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                    <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="sidebar-label">
                    <div class="text-xs font-medium text-white/80">Admin</div>
                    <div class="text-[10px] text-white/30">Ofyre SEO Engine</div>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-white/40
                           hover:bg-white/5 hover:text-white/70 transition-all duration-200">
                    <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </span>
                    <span class="sidebar-label font-medium">Déconnexion</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- ═══════════ MAIN CONTENT ═══════════ --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        {{-- Top Header --}}
        <header class="h-16 bg-white border-b border-gray-100/80 flex items-center justify-between px-6 lg:px-8 shrink-0"
                style="box-shadow: 0 1px 0 rgba(0,0,0,0.04);">

            {{-- Mobile hamburger --}}
            <button onclick="toggleSidebar()"
                    class="lg:hidden w-9 h-9 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 transition-colors mr-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Breadcrumb --}}
            <div class="flex items-center gap-2 text-sm flex-1">
                <div class="flex items-center gap-1.5 text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span class="hidden sm:inline">SEO Engine</span>
                    <svg class="w-3 h-3 text-gray-300 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
                @yield('breadcrumb')
            </div>

            {{-- Right actions --}}
            <div class="flex items-center gap-2">
                {{-- Live badge --}}
                <div class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium
                            bg-emerald-50 text-emerald-700 border border-emerald-100">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 status-dot"></span>
                    Runtime actif
                </div>
                {{-- Date --}}
                <div class="text-sm text-gray-400 font-medium hidden md:block">
                    {{ now()->format('d/m/Y') }}
                </div>
            </div>
        </header>

        {{-- Main Scrollable Area --}}
        <main class="flex-1 overflow-y-auto p-6 lg:p-8">

            {{-- Flash messages --}}
            @if(session('success'))
            <div class="anim-slide-down bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl px-5 py-4 mb-6 flex items-center gap-3 shadow-sm">
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
            @endif

            @if(session('new_token'))
            <div class="anim-slide-down rounded-2xl border border-amber-200 px-6 py-5 mb-6 shadow-sm"
                 style="background:linear-gradient(135deg,#fffbeb,#fef3c7);">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-amber-800 mb-2">
                            Token API pour {{ session('new_token_site') }} — copiez-le maintenant
                        </p>
                        <code class="block bg-white/60 text-amber-900 rounded-xl px-4 py-3 text-xs font-mono break-all select-all border border-amber-200">
                            {{ session('new_token') }}
                        </code>
                    </div>
                </div>
            </div>
            @endif

            @yield('content')
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
