<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SEO Engine — Connexion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #060810; }

        .login-orb-1 {
            position:fixed;width:700px;height:700px;border-radius:50%;pointer-events:none;
            background:radial-gradient(circle,rgba(99,102,241,0.14) 0%,transparent 70%);
            top:-200px;left:-200px;
            animation:orbFloat 10s ease-in-out infinite;
        }
        .login-orb-2 {
            position:fixed;width:500px;height:500px;border-radius:50%;pointer-events:none;
            background:radial-gradient(circle,rgba(139,92,246,0.1) 0%,transparent 70%);
            bottom:-100px;right:-100px;
            animation:orbFloat 13s ease-in-out infinite reverse;
        }
        @keyframes orbFloat {
            0%,100%{transform:translate(0,0);}
            50%{transform:translate(30px,-40px);}
        }

        .grid-bg {
            background-image:
                linear-gradient(rgba(99,102,241,0.03) 1px,transparent 1px),
                linear-gradient(90deg,rgba(99,102,241,0.03) 1px,transparent 1px);
            background-size:50px 50px;
        }

        .login-card {
            background: rgba(13,15,28,0.9);
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            animation: cardIn 0.5s cubic-bezier(.23,1,.32,1) both;
        }
        @keyframes cardIn {
            from{opacity:0;transform:translateY(24px) scale(0.97);}
            to{opacity:1;transform:none;}
        }

        .btn-submit {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 28px rgba(99,102,241,0.5);
        }
        .btn-submit:active { transform: translateY(0); }

        .pwd-input {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            color: white;
            transition: all 0.2s ease;
        }
        .pwd-input:focus {
            outline: none;
            border-color: rgba(99,102,241,0.6);
            background: rgba(99,102,241,0.06);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        .pwd-input::placeholder { color: rgba(255,255,255,0.2); }

        .logo-icon {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            box-shadow: 0 8px 30px rgba(99,102,241,0.5);
        }

        .back-link { transition: all 0.2s ease; }
        .back-link:hover { color: rgba(255,255,255,0.7); }
    </style>
</head>
<body class="min-h-screen grid-bg flex flex-col items-center justify-center p-4 relative overflow-hidden">

    <div class="login-orb-1"></div>
    <div class="login-orb-2"></div>

    {{-- Back link --}}
    <a href="/" class="back-link absolute top-6 left-6 flex items-center gap-2 text-sm text-white/30 z-10">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Retour au site
    </a>

    <div class="relative z-10 w-full max-w-sm">

        {{-- Logo + Brand --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl logo-icon mb-5">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">SEO Engine</h1>
            <p class="text-sm text-white/30 mt-1.5">Administration · Ofyre Agency</p>
        </div>

        {{-- Card --}}
        <div class="login-card rounded-3xl p-8">

            {{-- Status bar --}}
            <div class="flex items-center gap-2 mb-7 px-3 py-2.5 rounded-xl"
                 style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.15);">
                <span class="w-2 h-2 rounded-full bg-emerald-400" style="animation:pulse 2s ease-in-out infinite;"></span>
                <span class="text-xs text-emerald-300/80 font-medium">Runtime actif · Moteur en ligne</span>
            </div>

            @if($errors->any())
            <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-2xl px-4 py-3.5 mb-6 text-sm">
                <svg class="w-4 h-4 mt-0.5 shrink-0 text-rose-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span>{{ $errors->first() }}</span>
            </div>
            @endif

            <form method="POST" action="{{ route('admin.login.post') }}">
                @csrf
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-white/60 mb-2.5">
                        Mot de passe administrateur
                    </label>
                    <div class="relative">
                        <input type="password"
                               name="password"
                               id="password"
                               autofocus
                               class="pwd-input w-full rounded-xl px-4 py-3 text-sm pr-10"
                               placeholder="••••••••••••">
                        <button type="button"
                                onclick="togglePwd()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-white/20 hover:text-white/50 transition-colors">
                            <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="btn-submit w-full text-white font-bold rounded-xl px-4 py-3.5 text-sm flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Accéder au moteur
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-white/5">
                <div class="grid grid-cols-3 gap-3 text-center">
                    @foreach(['Crawl','Graph','IA'] as $feature)
                    <div class="rounded-xl py-2 px-1" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);">
                        <div class="text-xs font-semibold text-white/30">{{ $feature }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="text-center mt-6">
            <p class="text-xs text-white/20">
                SEO Engine · Ofyre Agency · {{ date('Y') }}
            </p>
        </div>
    </div>

    <script>
    function togglePwd() {
        var input = document.getElementById('password');
        input.type = input.type === 'password' ? 'text' : 'password';
    }
    </script>
</body>
</html>
