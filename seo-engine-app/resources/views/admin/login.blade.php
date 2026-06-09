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
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #070711;
            color: #f0f4ff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        body::after {
            content: '';
            position: fixed;
            top: -10rem; left: 50%;
            transform: translateX(-50%);
            width: 50rem; height: 25rem;
            background: radial-gradient(ellipse at top, rgba(99,102,241,.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .login-wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 22rem;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: .875rem;
            background: #6366f1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .login-title {
            font-size: 1.375rem;
            font-weight: 800;
            letter-spacing: -.03em;
            color: #f0f4ff;
            margin: 0;
        }

        .login-sub {
            font-size: .8125rem;
            color: rgba(176,192,220,.45);
            margin-top: .25rem;
        }

        .login-card {
            border-radius: 1rem;
            border: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.04);
            padding: 1.75rem;
        }

        .login-status {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .625rem .875rem;
            border-radius: .625rem;
            border: 1px solid rgba(16,185,129,.18);
            background: rgba(16,185,129,.06);
            margin-bottom: 1.5rem;
            font-size: .8rem;
            font-weight: 500;
            color: rgba(110,231,183,.8);
        }

        .login-status-dot {
            width: .4rem;
            height: .4rem;
            border-radius: 50%;
            background: #10b981;
            flex-shrink: 0;
        }

        .login-error {
            display: flex;
            align-items: flex-start;
            gap: .625rem;
            padding: .875rem 1rem;
            border-radius: .75rem;
            border: 1px solid rgba(244,63,94,.2);
            background: rgba(244,63,94,.08);
            color: rgba(252,165,165,.9);
            font-size: .875rem;
            margin-bottom: 1.25rem;
        }

        .login-label {
            display: block;
            font-size: .8125rem;
            font-weight: 600;
            color: rgba(176,192,220,.65);
            margin-bottom: .5rem;
        }

        .login-input {
            width: 100%;
            padding: .75rem 2.5rem .75rem 1rem;
            border-radius: .625rem;
            border: 1px solid rgba(255,255,255,.1);
            background: rgba(255,255,255,.05);
            color: #f0f4ff;
            font-family: inherit;
            font-size: .9rem;
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }

        .login-input:focus {
            border-color: rgba(99,102,241,.5);
            background: rgba(99,102,241,.06);
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }

        .login-input::placeholder { color: rgba(176,192,220,.25); }

        .login-eye {
            position: absolute;
            right: .875rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(176,192,220,.3);
            padding: 0;
            display: flex;
            align-items: center;
            transition: color .15s;
        }

        .login-eye:hover { color: rgba(176,192,220,.6); }

        .login-btn {
            width: 100%;
            padding: .875rem 1rem;
            border-radius: .625rem;
            border: none;
            background: #6366f1;
            color: #fff;
            font-family: inherit;
            font-size: .9375rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            transition: background .15s, transform .15s, box-shadow .15s;
            box-shadow: 0 0 0 1px rgba(99,102,241,.3), 0 8px 24px rgba(99,102,241,.2);
            margin-top: 1.25rem;
        }

        .login-btn:hover {
            background: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 0 0 1px rgba(99,102,241,.4), 0 12px 30px rgba(99,102,241,.28);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.25rem;
            font-size: .75rem;
            color: rgba(176,192,220,.25);
        }

        .back-link {
            position: fixed;
            top: 1.25rem;
            left: 1.25rem;
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .8125rem;
            font-weight: 500;
            color: rgba(176,192,220,.35);
            text-decoration: none;
            transition: color .15s;
        }

        .back-link:hover { color: rgba(176,192,220,.7); }
    </style>
</head>
<body>

    <a href="{{ route('home') }}" class="back-link">
        <svg style="width:.875rem;height:.875rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Retour au site
    </a>

    <div class="login-wrap">

        <div class="login-brand">
            <div class="login-icon">
                <svg style="width:1.25rem;height:1.25rem;color:#fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h1 class="login-title">SEO Engine</h1>
            <p class="login-sub">Espace privé · accès opérateur</p>
        </div>

        <div class="login-card">

            <div class="login-status">
                <span class="login-status-dot"></span>
                Runtime actif · environnement sécurisé
            </div>

            @if($errors->any())
            <div class="login-error">
                <svg style="width:.9rem;height:.9rem;flex-shrink:0;margin-top:.1rem;" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('admin.login.post') }}">
                @csrf
                <div>
                    <label class="login-label">Mot de passe privé</label>
                    <div style="position:relative;">
                        <input type="password" name="password" id="password"
                               autofocus class="login-input"
                               placeholder="••••••••••••">
                        <button type="button" class="login-eye" onclick="togglePwd()">
                            <svg style="width:.9rem;height:.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    <svg style="width:.9rem;height:.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Ouvrir l'espace privé
                </button>
            </form>
        </div>

        <div class="login-footer">SEO Engine · Ofyre · {{ date('Y') }}</div>
    </div>

    <script>
    function togglePwd() {
        var input = document.getElementById('password');
        input.type = input.type === 'password' ? 'text' : 'password';
    }
    </script>
</body>
</html>
