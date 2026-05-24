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

## Configuration Praeviseo

Dans Praeviseo, pour le site :

- mode de publication : `Bridge Laravel`
- endpoint : `https://client.com/api/praeviseo/bridge/publish`
- secret bridge/client : la même valeur que `PRAEVISEO_BRIDGE_SECRET`

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
