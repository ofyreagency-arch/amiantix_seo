# Architecture Stabilization Checklist

Phase active :

- stabiliser le socle complet avant d ajouter de nouvelles couches produit
- verifier chaque liaison entre frontend, backend, VPS et GSC
- imposer une separation nette entre concepts moteur internes et objets UX client

## 1. Regle produit non negociable

Le frontend client ne doit jamais etre une fenetre brute sur le moteur interne.

Le backend peut garder :

- `seo_sites`
- `seo_pages`
- `runtime`
- `scoring`
- `monitoring`
- `indexability`
- `feedback loop`
- `semantic linking`

Le frontend client doit parler uniquement :

- trafic SEO
- pages qui montent
- pages qui chutent
- priorites
- opportunites
- indexation
- recommandations simples
- prochaines actions

Corollaire :

- aucune page client ne doit dependre mentalement de `seo_pages`
- aucun wording client ne doit exposer `runtime`, `feedback loop`, `indexability`, `semantic linking`
- toute reponse API client doit etre orientee insight UX, pas structure interne

Regle de phase :

- tant que la partie free n est pas stable, lisible et utile seule, le pack installateur payant ne redevient pas prioritaire

Positionnement fige pendant la stabilisation :

- free = comprendre son SEO avec Google Search Console
- installateur = couche premium d execution et d automatisation
- on ne remet pas l installateur au centre tant que le free n explique pas deja clairement :
  - ce qui fonctionne
  - ce qui baisse
  - ou agir

Le free doit deja donner au client :

- impressions
- clics
- CTR
- positions Google
- pages indexees
- pages non indexees
- requetes SEO
- pages qui montent
- pages qui chutent
- opportunites SEO
- pages proches du top 10
- pages avec CTR faible
- tendances SEO
- priorites d optimisation
- recommandations simples

## 2. Invariants a respecter

### Frontend

- le frontend doit pouvoir tourner sans installateur
- le mode free doit fonctionner avec GSC seul
- un build Next ne doit pas necessiter de manipulation manuelle de PID
- chaque page client doit avoir :
  - etat loading propre
  - etat vide comprehensible
  - fallback API explicite
  - wording non technique

### Backend Laravel

- les routes client ne doivent pas exiger `seo_pages` pour fonctionner en mode free
- les APIs client doivent retourner des objets UX stables
- les services internes peuvent rester complexes, mais doivent etre traduits avant la couche client
- les donnees GSC doivent pouvoir alimenter :
  - dashboard
  - opportunites
  - indexation
  - tendances
  sans dependre du runtime installe

### VPS / Infra

- Nginx doit pointer vers le bon frontend et le bon backend
- Supervisor doit gerer correctement le frontend Node
- le port `3000` ne doit plus rester bloque apres restart
- les workers Laravel doivent rester actifs
- le cron doit etre en place
- SSL et domaine doivent etre verifies

### Sync local / VPS

- tout correctif necessaire au VPS doit etre pousse avant verification distante
- aucune validation ne doit supposer un commit local non deploye
- les migrations doivent etre a jour
- les configs `.env` critiques doivent etre connues et comparees

## 3. Audit par couche

### A. Frontend client

Verifier :

- routes existantes
- auth frontend
- appels API
- hydration
- fallback si API vide
- wording
- separation free vs installateur
- coherence des CTA

Pages a valider :

- `/dashboard`
- `/sites`
- `/sites/[siteId]`
- `/optimizations`
- `/publications`
- `/settings`

Questions systematiques :

- est-ce que le client comprend ce qu il voit ?
- est-ce que la page reste utile sans installateur ?
- est-ce qu un objet backend brut fuit dans l UX ?

### B. Backend client API

Verifier :

- `/api/client/...`
- structures JSON
- noms de champs
- fallback sans `seo_pages`
- compatibilite GSC-only
- mapping vers objets UX

Questions systematiques :

- est-ce que l API renvoie une structure exploitable sans connaissance du moteur ?
- est-ce que l API suppose encore un runtime installe ?
- est-ce qu une valeur attendue cote frontend est absente en base ou juste mal mappee ?

### C. Moteur GSC free

Verifier :

- connexion GSC
- import proprietes
- import clics / impressions / CTR / position
- import indexation URL
- opportunites sans installateur
- tendances
- alertes

Objectif :

- le mode free doit rester utile sans `seo_pages`, sans runtime, sans publication live

### D. VPS

Verifier :

- `git pull` a la racine
- build frontend
- script de restart frontend
- Supervisor
- Nginx
- workers
- cron
- SSL
- domaine

Commandes de base :

```bash
cd /var/www/seo-engine
git pull
```

```bash
cd /var/www/seo-engine/frontend
npm run build
```

```bash
cd /var/www/seo-engine
bash /var/www/seo-engine/deploy/scripts/restart-praeviseo-frontend.sh
```

```bash
cd /var/www/seo-engine/seo-engine-app
php artisan about
php artisan queue:failed
php artisan schedule:list
```

## 4. Flux a retester un par un

- connexion GSC
- lecture des proprietes
- import des performances
- import des pages
- import indexation
- dashboard client
- opportunites
- publications free
- alertes / tendances
- activation installateur

Regle :

- un flux est considere stable seulement si frontend, API, base et VPS donnent tous le meme resultat attendu

## 5. Discipline de travail

Pendant cette phase :

- priorite aux bugs de synchro et d architecture
- priorite aux objets UX stables
- priorite au mode free GSC-only
- ne pas ajouter de nouvelle couche produit tant que la precedente n est pas stable

Avant chaque nouvelle feature :

- verifier si le besoin n est pas deja couvert par le moteur existant
- verifier si le frontend a deja une version UX propre de cette donnee
- verifier si le VPS sert bien le dernier build

## 6. Definition of done de la phase

La phase de stabilisation sera consideree terminee quand :

- le dashboard free est coherent sans installateur
- les pages client ne fuitent plus les concepts moteur
- le deploiement frontend VPS est fiable
- les flux GSC critiques sont testes de bout en bout
- la separation free / installateur est claire produitement
