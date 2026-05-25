<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PraeviSEO</title>
    @vite(['resources/css/app.css'])
    <style>
        body {
            margin: 0;
            font-family: Inter, system-ui, sans-serif;
            background: #070711;
            color: #f4f7ff;
        }

        .shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background:
                radial-gradient(circle at top, rgba(99, 102, 241, 0.18), transparent 40%),
                radial-gradient(circle at bottom right, rgba(59, 130, 246, 0.12), transparent 35%),
                #070711;
        }

        .card {
            width: min(100%, 56rem);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1.75rem;
            background: rgba(255, 255, 255, 0.04);
            padding: 3rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(99, 102, 241, 0.12);
            color: #bfc8ff;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 1.25rem 0 0;
            font-size: clamp(2.5rem, 5vw, 4rem);
            line-height: 1.02;
            letter-spacing: -0.04em;
        }

        p {
            margin: 1.25rem 0 0;
            max-width: 42rem;
            color: rgba(229, 234, 255, 0.72);
            font-size: 1.05rem;
            line-height: 1.75;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 2rem;
        }

        .primary,
        .secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 13rem;
            padding: .95rem 1.25rem;
            border-radius: .95rem;
            text-decoration: none;
            font-weight: 700;
            transition: transform .15s ease, background .15s ease;
        }

        .primary {
            background: #6366f1;
            color: #fff;
        }

        .secondary {
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #f4f7ff;
            background: rgba(255, 255, 255, 0.03);
        }

        .primary:hover,
        .secondary:hover {
            transform: translateY(-1px);
        }

        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
            margin-top: 2.5rem;
        }

        .panel {
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1.1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
        }

        .panel strong {
            display: block;
            margin-bottom: .4rem;
            font-size: .95rem;
        }

        .panel span {
            color: rgba(229, 234, 255, 0.62);
            font-size: .9rem;
            line-height: 1.55;
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="card">
            <div class="eyebrow">PraeviSEO</div>
            <h1>Le cockpit SEO autonome, côté client comme côté équipe.</h1>
            <p>
                Connectez votre site, activez Search Console, puis laissez PraeviSEO piloter la publication,
                le monitoring et les opportunités SEO depuis un seul espace compréhensible.
            </p>

            <div class="actions">
                <a class="primary" href="/admin/login">Accéder à l’espace interne</a>
                <a class="secondary" href="http://localhost:3000">Ouvrir le site client</a>
            </div>

            <div class="grid">
                <div class="panel">
                    <strong>Connexion site</strong>
                    <span>Installez le bridge officiel sans copier de fichiers à la main.</span>
                </div>
                <div class="panel">
                    <strong>Search Console</strong>
                    <span>Branchez les signaux Google pour remonter clics, impressions et requêtes.</span>
                </div>
                <div class="panel">
                    <strong>Monitoring réel</strong>
                    <span>PraeviSEO suit ensuite la vraie page publique et réouvre seulement en cas de dérive.</span>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
