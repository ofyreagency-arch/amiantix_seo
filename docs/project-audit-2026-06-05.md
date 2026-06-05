# Audit Projet SEO - 2026-06-05

## But

Ce document fixe l'etat reel du projet apres audit, pour eviter de confondre :

- ce qui marche deja
- ce qui est branche mais fragile
- ce qui n'est pas encore pret produit

## Ce qui fonctionne

- Frontend Next.js : `npm run build` passe.
- API client SEO : `php artisan seo:doctor` passe.
- Smoke check SEO : passe, mais sans connexion Search Console locale configuree.
- Tests verifies :
  - `PremiumAutomationLoopTest` : OK
  - `ObservedSiteHealthRegressionTest` : OK
- Publication live pipeline :
  - la plupart des tests passent
  - un wording admin a ete remis en place (`Surveillance active`)

## Ce qui a ete corrige pendant cet audit

### CTA automation

- Les actions `crawl`, `article`, `reecriture`, `maillage`, `image`, `publication` renvoient maintenant un feedback visible meme en cas d'erreur.
- Les CTA article / reecriture / image / publication renvoient vers le studio editorial avec une vraie cible.
- Les liens de focus mal formes ont ete corriges.

### Logs d'actions

- Cote Next/server actions :
  - log `start`
  - log `success`
  - log `error`
- Cote backend Laravel :
  - log explicite sur les actions premium :
    - `generation`
    - `rewrite`
    - `linking`
    - `images`
    - `publication`

### Publication / bridge

- L'ecran Automatisations ne doit plus considerer le bridge comme "pas pret" si `publication_target.engine_actionable` est deja vrai.

## Ou lire les erreurs

### Frontend Next

Chercher dans les logs du process frontend :

- `[praeviseo][action] start`
- `[praeviseo][action] success`
- `[praeviseo][action] error`
- `[praeviseo][api] appFetch:http_error`

### Backend Laravel

Chercher dans `storage/logs/laravel.log` :

- `premium action`
- `premium action failed`
- `SEO engine API request`

## Etat reel par brique

### Disponible maintenant

- Crawl premium
- Lecture GSC
- Relecture / rewrite moteur
- Maillage interne moteur
- Generation d'image SEO
- Publication live via bridge quand le site est vraiment configure
- Studio editorial `/publications`

### Branche mais encore fragile

- Promesse "full auto" depuis le cockpit
- Distinction brouillon / image / validation / live
- Messages de readiness parfois trop ambitieux si la data site est incoherente

### Encore en preparation produit

- vrai CMS editorial complet
- preview totalement au style du site client
- orchestration "un clic = article + image + preview + publication" sans controle intermediaire

## Commandes de check utiles

### Frontend

```bash
cd /var/www/seo-engine/frontend
npm run build
```

### Backend

```bash
cd /var/www/seo-engine/seo-engine-app
php artisan seo:doctor
php artisan seo:smoke-check
```

### Tests cibles

```bash
php artisan test --filter=PremiumAutomationLoopTest
php artisan test --filter=ObservedSiteHealthRegressionTest
php artisan test --filter=LivePublicationPipelineTest
```

## Point d'attention local

`php artisan schedule:list` peut echouer localement si l'environnement attend `phpredis` mais que l'extension PHP `redis` n'est pas chargee.

Ce n'est pas un blocage produit du moteur lui-meme, mais un point d'environnement a corriger sur la machine de travail si on veut auditer tout le scheduler localement.

## Direction produit recommandee

1. Observer
2. Prioriser
3. Agir
4. Automatiser ensuite

Concretement :

- garder un cockpit honnete
- garder un studio editorial central
- ne pas afficher comme "pret" ce qui n'a pas encore un workflow vraiment fiable
