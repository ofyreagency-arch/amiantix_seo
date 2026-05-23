<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SEO Engine — Intelligence SEO automatisée par Ofyre</title>
    <meta name="description" content="SEO Engine pilote votre stratégie SEO en temps réel. Crawl, analyse sémantique, recommandations IA, rewrite automatisé. Le moteur SEO qui ne dort jamais.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        * { font-family: 'Inter', sans-serif; }
        .hero-orb-1 {
            position:absolute;width:600px;height:600px;border-radius:50%;
            background:radial-gradient(circle,rgba(99,102,241,0.18) 0%,transparent 70%);
            top:-200px;left:-100px;pointer-events:none;
            animation:floatOrb 8s ease-in-out infinite;
        }
        .hero-orb-2 {
            position:absolute;width:500px;height:500px;border-radius:50%;
            background:radial-gradient(circle,rgba(139,92,246,0.14) 0%,transparent 70%);
            top:100px;right:-150px;pointer-events:none;
            animation:floatOrb 10s ease-in-out infinite reverse;
        }
        .hero-orb-3 {
            position:absolute;width:400px;height:400px;border-radius:50%;
            background:radial-gradient(circle,rgba(6,182,212,0.1) 0%,transparent 70%);
            bottom:-100px;left:30%;pointer-events:none;
            animation:floatOrb 12s ease-in-out infinite;
        }
        @keyframes floatOrb {
            0%,100%{transform:translate(0,0) scale(1);}
            33%{transform:translate(20px,-30px) scale(1.05);}
            66%{transform:translate(-15px,20px) scale(0.97);}
        }
        .grid-bg {
            background-image:
                linear-gradient(rgba(99,102,241,0.035) 1px,transparent 1px),
                linear-gradient(90deg,rgba(99,102,241,0.035) 1px,transparent 1px);
            background-size:60px 60px;
        }
        .grad-text {
            background:linear-gradient(135deg,#a78bfa 0%,#818cf8 35%,#60a5fa 65%,#34d399 100%);
            background-size:200% auto;
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
            background-clip:text;
            animation:gradMove 4s linear infinite;
        }
        @keyframes gradMove{0%{background-position:0% center;}100%{background-position:200% center;}}
        .btn-glow {
            background:linear-gradient(135deg,#6366f1,#8b5cf6);
            box-shadow:0 0 30px rgba(99,102,241,0.4),0 4px 16px rgba(99,102,241,0.3);
            transition:all 0.25s ease;
        }
        .btn-glow:hover{transform:translateY(-2px);box-shadow:0 0 40px rgba(99,102,241,0.55),0 8px 24px rgba(99,102,241,0.35);}
        .feature-card{transition:transform 0.25s ease,box-shadow 0.25s ease,border-color 0.25s ease;}
        .feature-card:hover{transform:translateY(-4px);box-shadow:0 20px 50px rgba(0,0,0,0.25),0 0 0 1px rgba(99,102,241,0.25);border-color:rgba(99,102,241,0.3);}
        .reveal{opacity:0;transform:translateY(28px);transition:opacity 0.7s ease,transform 0.7s cubic-bezier(.23,1,.32,1);}
        .reveal.visible{opacity:1;transform:none;}
        .badge-pill{background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(139,92,246,0.1));border:1px solid rgba(99,102,241,0.25);}
        .nav-glass{background:rgba(9,10,20,0.85);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid rgba(255,255,255,0.06);}
        .terminal{background:#0d0f1c;border:1px solid rgba(255,255,255,0.08);font-family:'Fira Code','Courier New',monospace;}
        .cursor::after{content:'|';animation:blink 1s step-end infinite;color:#6366f1;}
        @keyframes blink{0%,100%{opacity:1}50%{opacity:0}}
        @keyframes scrollDot{0%,100%{transform:translateY(0);opacity:1;}80%{transform:translateY(12px);opacity:0;}}
        .scroll-dot{animation:scrollDot 1.8s ease-in-out infinite;}
        .stat-item{animation:fadeInUp 0.7s cubic-bezier(.23,1,.32,1) both;}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:none;}}
    </style>
</head>
<body class="bg-[#060810] text-white antialiased overflow-x-hidden">

{{-- ═══════════ NAV ═══════════ --}}
<nav class="nav-glass fixed top-0 inset-x-0 z-50">
    <div class="max-w-7xl mx-auto px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center"
                 style="background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 4px 14px rgba(99,102,241,0.45);">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span class="font-bold text-white text-sm tracking-tight">SEO Engine</span>
            <span class="hidden sm:inline text-[10px] font-semibold uppercase tracking-widest text-indigo-400/60 border border-indigo-500/20 px-1.5 py-0.5 rounded">by Ofyre</span>
        </a>
        <div class="hidden md:flex items-center gap-8">
            <a href="#features" class="text-sm text-white/50 hover:text-white transition-colors">Fonctionnalités</a>
            <a href="#how" class="text-sm text-white/50 hover:text-white transition-colors">Comment ça marche</a>
            <a href="#stats" class="text-sm text-white/50 hover:text-white transition-colors">Résultats</a>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.login') }}" class="text-sm text-white/50 hover:text-white transition-colors hidden sm:block">Connexion</a>
            <a href="{{ route('admin.login') }}" class="btn-glow text-white text-sm font-semibold px-5 py-2.5 rounded-xl">
                Accéder au moteur →
            </a>
        </div>
    </div>
</nav>

{{-- ═══════════ HERO ═══════════ --}}
<section class="relative min-h-screen flex items-center justify-center overflow-hidden grid-bg pt-16">
    <div class="hero-orb-1"></div>
    <div class="hero-orb-2"></div>
    <div class="hero-orb-3"></div>

    <div class="relative z-10 max-w-5xl mx-auto px-6 lg:px-8 text-center">
        <div class="inline-flex items-center gap-2 badge-pill rounded-full px-4 py-2 mb-8" style="animation:fadeInUp 0.5s ease both;">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" style="animation:pulse 2s ease-in-out infinite;"></span>
            <span class="text-xs font-semibold text-indigo-300 tracking-wide">Moteur IA en production · Runtime actif</span>
        </div>

        <h1 class="text-5xl sm:text-6xl lg:text-7xl font-black tracking-tight leading-[1.08] mb-6"
            style="animation:fadeInUp 0.5s 0.1s ease both;">
            Le SEO qui<br>
            <span class="grad-text">se pilote seul.</span>
        </h1>

        <p class="text-lg sm:text-xl text-white/50 max-w-2xl mx-auto leading-relaxed mb-10"
           style="animation:fadeInUp 0.5s 0.2s ease both;">
            SEO Engine observe, analyse et optimise vos sites en continu.
            Crawl automatisé, graph sémantique, recommandations IA, rewrite piloté.
            <strong class="text-white/70">Zéro intervention manuelle.</strong>
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16"
             style="animation:fadeInUp 0.5s 0.3s ease both;">
            <a href="{{ route('admin.login') }}" class="btn-glow text-white font-bold px-8 py-4 rounded-2xl text-base">
                Accéder au dashboard →
            </a>
            <a href="#how" class="flex items-center gap-2 text-sm text-white/50 hover:text-white transition-colors px-4 py-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Voir comment ça marche
            </a>
        </div>

        {{-- Terminal / hero card --}}
        <div class="terminal rounded-3xl overflow-hidden max-w-3xl mx-auto"
             style="box-shadow:0 40px 100px rgba(0,0,0,0.6),0 0 0 1px rgba(99,102,241,0.15),0 0 60px rgba(99,102,241,0.08);
                    animation:fadeInUp 0.6s 0.4s ease both;">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-white/5">
                <div class="w-2.5 h-2.5 rounded-full bg-rose-500/70"></div>
                <div class="w-2.5 h-2.5 rounded-full bg-amber-500/70"></div>
                <div class="w-2.5 h-2.5 rounded-full bg-emerald-500/70"></div>
                <div class="flex-1 flex items-center justify-center">
                    <div class="bg-white/5 rounded px-3 py-1 text-xs text-white/30 font-mono">seo-engine · runtime · live</div>
                </div>
            </div>
            <div class="p-6 text-left space-y-3 text-sm font-mono">
                <div class="text-emerald-400/80"><span class="text-indigo-400/60">$</span> seo-engine analyze --site=mon-site.fr</div>
                <div class="text-white/40">✓ Crawl terminé · 847 URLs indexées</div>
                <div class="text-white/40">✓ Graph sémantique · 12 clusters détectés</div>
                <div class="text-amber-400/80">⚠ 23 pages orphelines · 8 risques de cannibalisation</div>
                <div class="text-cyan-400/80">→ 41 recommandations générées · priorité P1–P3</div>
                <div class="text-emerald-400/80">✓ Autopilot activé · rewrite en cours <span class="cursor"></span></div>
            </div>
            <div class="grid grid-cols-3 border-t border-white/5">
                @foreach([['847','URLs crawlées'],['41','Recommandations'],['12','Clusters SEO']] as $item)
                <div class="px-4 py-3 text-center {{ !$loop->last ? 'border-r border-white/5' : '' }}">
                    <div class="text-base font-bold text-white">{{ $item[0] }}</div>
                    <div class="text-[10px] text-white/30 mt-0.5">{{ $item[1] }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="absolute bottom-10 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2">
        <div class="text-[10px] uppercase tracking-widest text-white/20">Découvrir</div>
        <div class="w-5 h-8 rounded-full border border-white/10 flex items-start justify-center pt-1.5">
            <div class="w-1 h-2 rounded-full bg-white/30 scroll-dot"></div>
        </div>
    </div>
</section>

{{-- ═══════════ TECH STRIP ═══════════ --}}
<section class="py-14 border-y border-white/5" style="background:rgba(255,255,255,0.02);">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <p class="text-center text-xs uppercase tracking-widest text-white/20 mb-8">Technologies au cœur du moteur</p>
        <div class="flex flex-wrap items-center justify-center gap-8 md:gap-16">
            @foreach(['Laravel','Chart.js','D3.js','Google Search Console','Tailwind CSS','OpenAI'] as $tech)
            <span class="text-sm font-semibold text-white/25 hover:text-white/50 transition-colors cursor-default">{{ $tech }}</span>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════ STATS ═══════════ --}}
<section id="stats" class="py-24 reveal">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach([
                ['100+','Sites gérés en production','multi-niches','50ms'],
                ['10k+','Pages crawlées par semaine','observées & scorées','100ms'],
                ['98%','Uptime du moteur','24h/7j/365j','150ms'],
                ['3×','Gain de productivité SEO','vs manuel','200ms'],
            ] as $s)
            <div class="rounded-3xl border border-white/6 p-6 text-center stat-item"
                 style="background:linear-gradient(135deg,rgba(255,255,255,0.03),rgba(255,255,255,0.01));animation-delay:{{ $s[3] }};">
                <div class="text-4xl lg:text-5xl font-black grad-text mb-2">{{ $s[0] }}</div>
                <div class="text-sm font-semibold text-white/70">{{ $s[1] }}</div>
                <div class="text-xs text-white/30 mt-1">{{ $s[2] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════ FEATURES ═══════════ --}}
<section id="features" class="py-24">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-16 reveal">
            <div class="inline-flex items-center gap-2 badge-pill rounded-full px-3 py-1.5 mb-5">
                <span class="text-xs font-semibold text-indigo-300 uppercase tracking-widest">Fonctionnalités</span>
            </div>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white mb-4 leading-tight">
                Tout ce dont votre SEO<br>
                <span class="grad-text">a besoin, en un moteur.</span>
            </h2>
            <p class="text-white/40 text-base leading-relaxed">Un seul système intégré. Pas de bricolage entre 10 outils.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @php
            $features = [
                ['icon'=>'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
                 'grad'=>'#6366f1,#7c3aed','glow'=>'rgba(99,102,241,0.25)',
                 'title'=>'Crawl automatisé',
                 'desc'=>'Exploration continue de vos sites. Détection URLs, analyse indexabilité, suivi des changements en temps réel.',
                 'tags'=>['Crawl profond','Indexabilité','Monitoring']],
                ['icon'=>'M7 20l4-16m2 16l4-16M6 9h14M4 15h14',
                 'grad'=>'#0ea5e9,#0891b2','glow'=>'rgba(6,182,212,0.25)',
                 'title'=>'Graph sémantique',
                 'desc'=>'Visualisation et analyse des relations sémantiques. Détection des clusters, cannibalisations et opportunités.',
                 'tags'=>['Clusters','Cannibalisation','Linking interne']],
                ['icon'=>'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
                 'grad'=>'#d946ef,#db2777','glow'=>'rgba(217,70,239,0.25)',
                 'title'=>'Recommandations IA',
                 'desc'=>'Le moteur analyse vos données observées et génère des recommandations priorisées P1–P3 basées sur impact réel.',
                 'tags'=>['Priorité P1–P3','Impact réel','Runtime']],
                ['icon'=>'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
                 'grad'=>'#f59e0b,#ea580c','glow'=>'rgba(245,158,11,0.25)',
                 'title'=>'Rewrite automatisé',
                 'desc'=>'Génération et réécriture de contenu piloté par les signaux SEO. Feedback loop intégré pour améliorer continuellement.',
                 'tags'=>['Autopilot','Feedback loop','Signaux GSC']],
                ['icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                 'grad'=>'#10b981,#0d9488','glow'=>'rgba(16,185,129,0.25)',
                 'title'=>'Health scoring',
                 'desc'=>'Score de santé en temps réel par page et par site. Détection proactive des pages faibles et orphelines.',
                 'tags'=>['Score santé','Pages faibles','Orphelines']],
                ['icon'=>'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
                 'grad'=>'#f43f5e,#dc2626','glow'=>'rgba(244,63,94,0.25)',
                 'title'=>'Intégration GSC',
                 'desc'=>'Connexion directe à Google Search Console. Requêtes réelles pour alimenter les recommandations et observer les positions.',
                 'tags'=>['Search Console','Requêtes réelles','Positions']],
            ];
            @endphp

            @foreach($features as $i => $f)
            <div class="feature-card rounded-3xl border border-white/6 p-6 reveal"
                 style="background:linear-gradient(135deg,rgba(255,255,255,0.04),rgba(255,255,255,0.01));animation-delay:{{ $i * 80 }}ms;">
                <div class="w-11 h-11 rounded-2xl mb-5 flex items-center justify-center"
                     style="background:linear-gradient(135deg,{{ $f['grad'] }});box-shadow:0 4px 16px {{ $f['glow'] }};">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-white mb-2">{{ $f['title'] }}</h3>
                <p class="text-sm text-white/40 leading-relaxed mb-4">{{ $f['desc'] }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($f['tags'] as $tag)
                    <span class="text-[11px] font-medium text-white/40 border border-white/8 px-2.5 py-1 rounded-full"
                          style="background:rgba(255,255,255,0.03);">{{ $tag }}</span>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════ HOW IT WORKS ═══════════ --}}
<section id="how" class="py-24" style="background:rgba(255,255,255,0.015);">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="text-center max-w-xl mx-auto mb-16 reveal">
            <div class="inline-flex items-center gap-2 badge-pill rounded-full px-3 py-1.5 mb-5">
                <span class="text-xs font-semibold text-indigo-300 uppercase tracking-widest">Processus</span>
            </div>
            <h2 class="text-3xl sm:text-4xl font-black text-white mb-4 leading-tight">
                Comment le moteur<br>
                <span class="grad-text">travaille pour vous</span>
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @php
            $steps = [
                ['num'=>'01','grad'=>'#6366f1,#7c3aed','title'=>'Connexion & Config',
                 'desc'=>'Ajoutez vos sites clients, connectez Google Search Console, configurez le preset adapté à votre niche.',
                 'items'=>['Site ID + URL','Preset niche','Connexion GSC'],'delay'=>'0ms'],
                ['num'=>'02','grad'=>'#0ea5e9,#0891b2','title'=>'Observation & Analyse',
                 'desc'=>'Le moteur crawle en continu, construit le graph sémantique et observe les signaux réels de vos pages.',
                 'items'=>['Crawl automatisé','Graph sémantique','Signaux GSC'],'delay'=>'150ms'],
                ['num'=>'03','grad'=>'#10b981,#0d9488','title'=>'Recommandations & Action',
                 'desc'=>'Recommandations priorisées générées automatiquement. Autopilot optionnel pour les rewrites et optimisations.',
                 'items'=>['Recommandations P1–P3','Rewrite autopilot','Feedback loop'],'delay'=>'300ms'],
            ];
            @endphp

            @foreach($steps as $step)
            <div class="reveal" style="animation-delay:{{ $step['delay'] }};">
                <div class="rounded-3xl border border-white/6 p-7 h-full"
                     style="background:linear-gradient(135deg,rgba(255,255,255,0.04),rgba(255,255,255,0.01));">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-5"
                         style="background:linear-gradient(135deg,{{ $step['grad'] }});box-shadow:0 8px 24px rgba(99,102,241,0.2);">
                        <span class="text-xl font-black text-white">{{ $step['num'] }}</span>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">{{ $step['title'] }}</h3>
                    <p class="text-sm text-white/40 leading-relaxed mb-5">{{ $step['desc'] }}</p>
                    <ul class="space-y-2.5">
                        @foreach($step['items'] as $item)
                        <li class="flex items-center gap-2.5 text-sm text-white/60">
                            <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════ DASHBOARD PREVIEW ═══════════ --}}
<section class="py-24">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-14 items-center">

            <div class="reveal">
                <div class="inline-flex items-center gap-2 badge-pill rounded-full px-3 py-1.5 mb-6">
                    <span class="text-xs font-semibold text-indigo-300 uppercase tracking-widest">Dashboard</span>
                </div>
                <h2 class="text-3xl sm:text-4xl font-black text-white mb-5 leading-tight">
                    Un cockpit conçu pour<br>les agences SEO<br>
                    <span class="grad-text">sérieuses.</span>
                </h2>
                <p class="text-base text-white/40 leading-relaxed mb-8">
                    Toutes vos données SEO en un seul endroit. Queues en temps réel, santé multi-sites,
                    recommandations priorisées, historique des crawls. Pour des agences qui gèrent des dizaines de sites.
                </p>
                <ul class="space-y-3 mb-8">
                    @foreach(['Vue multi-sites en temps réel','Queues IA priorisées (P1–P3)','Graph sémantique interactif','Autopilot avec contrôle humain','Intégration GSC native'] as $item)
                    <li class="flex items-center gap-3 text-sm text-white/60">
                        <div class="w-5 h-5 rounded-full bg-indigo-500/20 flex items-center justify-center shrink-0">
                            <svg class="w-3 h-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        {{ $item }}
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.login') }}"
                   class="btn-glow inline-flex items-center gap-2 text-white font-bold px-7 py-3.5 rounded-xl">
                    Accéder au dashboard
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
            </div>

            {{-- Mock dashboard --}}
            <div class="reveal" style="animation-delay:200ms;">
                <div class="rounded-3xl overflow-hidden border border-white/8"
                     style="background:#0d0f1c;box-shadow:0 40px 80px rgba(0,0,0,0.5),0 0 0 1px rgba(99,102,241,0.1);">
                    <div class="h-10 border-b border-white/5 flex items-center px-4 gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-rose-500/50"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-amber-500/50"></div>
                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500/50"></div>
                        <div class="flex-1 text-center">
                            <span class="text-[10px] text-white/20 font-mono">SEO Engine · Dashboard</span>
                        </div>
                    </div>
                    <div class="flex">
                        <div class="w-12 border-r border-white/5 py-4 flex flex-col items-center gap-3">
                            @foreach(['active','inactive','inactive'] as $state)
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center {{ $state === 'active' ? 'bg-indigo-500/30' : 'bg-white/5' }}">
                                <div class="w-3 h-3 rounded {{ $state === 'active' ? 'bg-indigo-400/60' : 'bg-white/10' }}"></div>
                            </div>
                            @endforeach
                        </div>
                        <div class="flex-1 p-4 space-y-3">
                            <div class="rounded-2xl p-4 h-24"
                                 style="background:linear-gradient(135deg,#0f0c29,#1a1a3e);">
                                <div class="text-[9px] text-indigo-300/50 uppercase tracking-wider mb-1">SEO Brain Runtime</div>
                                <div class="text-sm font-bold text-white mb-2">Intelligence Active</div>
                                <div class="flex gap-2">
                                    @foreach([4,2,8,3] as $v)
                                    <div class="rounded-lg bg-white/8 px-2 py-1 text-center">
                                        <div class="text-xs font-bold text-white">{{ $v }}</div>
                                        <div class="text-[7px] text-white/30">sites</div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                @foreach(['#6366f1','#06b6d4','#d946ef','#10b981'] as $col)
                                <div class="rounded-xl p-2 border border-white/5" style="background:rgba(255,255,255,0.03);">
                                    <div class="w-3 h-3 rounded mb-1" style="background:{{ $col }}30;"></div>
                                    <div class="text-xs font-bold text-white">{{ rand(2,99) }}</div>
                                </div>
                                @endforeach
                            </div>
                            <div class="rounded-xl border border-white/5 p-3 h-20 flex items-center justify-center"
                                 style="background:rgba(255,255,255,0.02);">
                                <svg viewBox="0 0 160 50" class="w-full h-full opacity-50">
                                    <polyline points="0,40 20,32 40,38 60,20 80,28 100,12 120,18 140,8 160,14"
                                              fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <polyline points="0,40 20,32 40,38 60,20 80,28 100,12 120,18 140,8 160,14 160,50 0,50"
                                              fill="rgba(99,102,241,0.1)" stroke="none"/>
                                </svg>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach([['bg-emerald-500/20 text-emerald-400','completed'],['bg-amber-500/20 text-amber-400','running'],['bg-rose-500/20 text-rose-400','pending']] as $badge)
                                <div class="rounded-lg px-2 py-1.5 text-center {{ $badge[0] }}">
                                    <div class="text-[10px] font-semibold">{{ $badge[1] }}</div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════ CTA ═══════════ --}}
<section class="py-24">
    <div class="max-w-4xl mx-auto px-6 lg:px-8 text-center reveal">
        <div class="relative rounded-3xl overflow-hidden px-8 py-16"
             style="background:linear-gradient(135deg,#0f0c29 0%,#1a1a3e 50%,#0d1117 100%);
                    border:1px solid rgba(99,102,241,0.2);
                    box-shadow:0 0 80px rgba(99,102,241,0.12),0 40px 80px rgba(0,0,0,0.4);">
            <div class="absolute inset-0 pointer-events-none"
                 style="background:radial-gradient(600px circle at 50% -100px,rgba(99,102,241,0.12),transparent);"></div>
            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 badge-pill rounded-full px-3 py-1.5 mb-6">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" style="animation:pulse 2s ease-in-out infinite;"></span>
                    <span class="text-xs font-semibold text-indigo-300 uppercase tracking-widest">Prêt à démarrer</span>
                </div>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white mb-5 leading-tight">
                    Votre SEO sur pilote<br>
                    <span class="grad-text">automatique dès aujourd'hui.</span>
                </h2>
                <p class="text-base text-white/40 max-w-xl mx-auto mb-10">
                    Connectez votre premier site, lancez le crawl, et laissez le moteur
                    générer vos premières recommandations en quelques minutes.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ route('admin.login') }}" class="btn-glow text-white font-bold px-8 py-4 rounded-2xl text-base">
                        Accéder au moteur →
                    </a>
                    <div class="text-sm text-white/30">Aucune installation · Juste se connecter</div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════ FOOTER ═══════════ --}}
<footer class="border-t border-white/5 py-12">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center"
                     style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <span class="font-bold text-white/80 text-sm">SEO Engine</span>
                <span class="text-white/20 text-sm">by Ofyre</span>
            </div>
            <div class="flex items-center gap-6">
                <a href="#features" class="text-sm text-white/30 hover:text-white/60 transition-colors">Fonctionnalités</a>
                <a href="#how" class="text-sm text-white/30 hover:text-white/60 transition-colors">Processus</a>
                <a href="{{ route('admin.login') }}" class="text-sm text-white/30 hover:text-white/60 transition-colors">Administration</a>
                <span class="text-white/10">·</span>
                <span class="text-sm text-white/20">© {{ date('Y') }} Ofyre Agency</span>
            </div>
        </div>
    </div>
</footer>

<script>
const revealEls = document.querySelectorAll('.reveal');
const io = new IntersectionObserver((entries) => {
    entries.forEach(function(e) {
        if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); }
    });
}, { threshold: 0.1 });
revealEls.forEach(function(el) { io.observe(el); });

document.querySelectorAll('a[href^="#"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
        var target = document.querySelector(a.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
});
</script>
</body>
</html>
