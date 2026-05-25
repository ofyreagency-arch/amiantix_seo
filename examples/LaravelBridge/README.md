# Laravel Bridge

Premier adaptateur concret Praeviseo pour un vrai site client Laravel.

Ce bridge couvre un workflow réel :

1. Praeviseo valide une page
2. Praeviseo pousse la page vers un endpoint Laravel signé
3. Le site client met à jour une vraie page publique
4. Praeviseo recrawl ensuite la vraie URL, lit Google Search Console et décide s il faut réouvrir une amélioration

## Ce que fait ce bridge

- auth signée par secret partagé
- endpoint de publication JSON
- upsert d une vraie page publique Laravel
- mise à jour :
  - title
  - meta description
  - contenu HTML
  - FAQ
  - schema
  - maillage interne
  - image
  - canonical
  - noindex
- réponse JSON claire succès / échec

## Installation côté site client Laravel

Copier les fichiers suivants dans le vrai site client Laravel :

- `app/Http/Controllers/PraeviseoBridgeController.php`
- `app/Http/Controllers/PraeviseoPublishedPageController.php`
- `app/Console/Commands/PraeviseoConnectCommand.php`
- `app/Models/PraeviseoPublishedPage.php`
- `app/Services/PraeviseoBridgeService.php`
- `database/migrations/2026_05_24_000000_create_praeviseo_published_pages_table.php`
- `resources/views/praeviseo/published-page.blade.php`

Puis déclarer les routes ci-dessous.

## Variables d environnement

```env
PRAEVISEO_BRIDGE_SECRET=change-me
PRAEVISEO_BRIDGE_SITE_ID=amiantix
PRAEVISEO_BRIDGE_PREFIX=ressources
```

## Routes Laravel client

```php
use App\Http\Controllers\PraeviseoBridgeController;
use App\Http\Controllers\PraeviseoPublishedPageController;
use Illuminate\Support\Facades\Route;

Route::post('/api/praeviseo/bridge/publish', [PraeviseoBridgeController::class, 'publish']);

Route::get('/'.trim((string) env('PRAEVISEO_BRIDGE_PREFIX', 'ressources'), '/').'/{slug}', [PraeviseoPublishedPageController::class, 'show'])
    ->name('praeviseo.published-page');
```

## Connexion simple côté client

Le client n a rien à configurer à la main dans Praeviseo.

Il lance juste :

```bash
php artisan praeviseo:connect ABCD-EFGH-IJKL
```

Puis il copie les 3 variables `.env` affichées par la commande.

Le résultat attendu côté Praeviseo :

- Site connecté ✅
- Publication active ✅
- Monitoring actif ✅

## Réponse attendue

Le bridge répond :

```json
{
  "status": "ok",
  "updated": true,
  "slug": "mon-slug",
  "live_url": "https://client.com/ressources/mon-slug"
}
```

## Ce que ce bridge ne prétend pas corriger

Le bridge ne corrige pas :

- DNS
- serveur
- infra
- robots serveur
- redirects cassées
- bugs CMS hors publication

Ces cas restent des revues techniques humaines.
