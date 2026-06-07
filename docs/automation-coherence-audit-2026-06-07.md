# Audit de cohérence Automatisations — 7 juin 2026

Objectif : vérifier que chaque bloc visible dans `Automatisations` repose sur une donnée réelle, actuelle et traçable, sans lire MySQL ou Tinker.

## Périmètre

- Écran : `frontend/src/app/(app)/sites/[siteId]/automation/page.tsx`
- API site : `seo-engine-app/app/Http/Controllers/Api/ClientSitesController.php`
- API studio/publications : `seo-engine-app/app/Http/Controllers/Api/ClientWorkspaceController.php`

## Résumé

Deux incohérences majeures ont été corrigées pendant cet audit :

1. `Live vérifié` pouvait s’afficher à partir de `seo_pages.published_live = true`, sans confirmation HTTP observée.
2. `Problèmes de crawl` affichait un cumul historique de `seo_site_crawl_issues`, alors que le reste de la page parle du crawl de référence affiché.

## Tableau de vérité

| Bloc affiché | Source API réelle | Requête / source utilisée | Pourquoi la valeur affichée est celle-ci | État après audit |
| --- | --- | --- | --- | --- |
| `Installation à relancer` | `site.next_action` | `ClientSitesController::nextActionForSite()` lit `latestRemoteInstallation()` et son `status` | S’affiche si une installation distante existe et que son statut est `failed` alors que le bridge n’est pas connecté. | Cohérent |
| `Publication automatique` | `site.publication_target` + `publications.items` | Combinaison de `publicationTargetStatus($site)` et de `/api/client/publications` | Le bloc mélange l’état moteur de publication et l’état réel des contenus visibles. | Corrigé |
| `Live vérifié` | `publications.items[].live_verified` | `ClientWorkspaceController::publicationLiveVerified()` : `seo_pages.published_live = true` + `live_url` non vide + `seo_site_pages.last_status_code < 400` | Le badge ne doit apparaître que si la page est publiée live **et** confirmée par la dernière lecture observée. | Corrigé |
| `2 contenus live` | `publications.items` | Comptage frontend de `items.filter(item => item.live_verified)` | Le chiffre doit compter uniquement les contenus réellement confirmés en live, pas les simples `published_live`. | Corrigé |
| `241 problèmes de crawl` | `site.crawl_report.produced_data.crawl_issues` | Avant audit : `SeoSiteCrawlIssue::where(site_id)->count()` ; après audit : `SeoSiteCrawlIssue::where(site_id)->where(site_crawl_id, referenceCrawl)->count()` | L’ancien chiffre était un cumul historique. Le bloc simple doit parler du crawl de référence affiché sur la page. | Corrigé |
| `8 points à surveiller` | `site.last_successful_crawl.issues_count` | `SeoSiteCrawl.meta_json['issues_count']` via `serializeObservedCrawl()` | La valeur correspond au résumé du dernier crawl réussi affiché juste au-dessus. | Cohérent |
| `Créer un article bientôt prêt` | Carte `Actions à faire maintenant` | Frontend : `generationReady ? "now" : gscConnected ? "soon" : "prep"` ; donnée source = `site.summary.new_queries` issue de `searchConsoleSnapshot()` | `Bientôt prêt` signifie : GSC connectée, mais aucune `new_query` assez nette n’est encore remontée pour ouvrir un article maintenant. | Cohérent mais dérivé UI |

## Détail bloc par bloc

### 1. Installation à relancer

Source :
- `GET /api/client/sites/{siteId}`
- Champ : `site.next_action`

Code :
- `ClientSitesController::nextActionForSite()`

Logique :
```php
$latestInstallation = $site->latestRemoteInstallation()->first();

if ($latestInstallation && ! $bridgeConnected && $latestInstallation->status === RemoteInstallation::STATUS_FAILED) {
    return [
        'kind' => 'installation_failed',
        'label' => 'Installation PraeviSEO à relancer',
    ];
}
```

Pourquoi :
- Le produit n’invente rien ici.
- Ce bloc dépend directement du dernier enregistrement `remote_installations`.

### 2. Publication automatique

Sources combinées :
- `site.publication_target`
- `publications.items`

API :
- `GET /api/client/sites/{siteId}`
- `GET /api/client/publications`

Pourquoi :
- `site.publication_target` répond à : “le bridge et la cible moteur sont-ils actionnables ?”
- `publications.items` répond à : “y a-t-il de vrais contenus visibles ou seulement poussés côté moteur ?”

Conclusion :
- Le bloc doit rester un mélange de moteur + réalité observée.
- Mais il ne doit jamais présenter `Live vérifié` sans validation observée.

### 3. Live vérifié

Source :
- `publications.items[].live_verified`

Code :
- `ClientWorkspaceController::publicationLiveVerified()`

Logique réelle :
```php
if (! $page->isPublishedLive() || blank($page->live_url)) {
    return false;
}

$observedPage = $page->observedPage;
$statusCode = (int) ($observedPage->last_status_code ?? 0);

return $statusCode > 0 && $statusCode < 400;
```

Pourquoi :
- `published_live` seul ne suffit pas.
- Une URL live 404 ne doit jamais être comptée comme vérifiée.

### 4. Nombre de contenus live

Avant audit :
- Le frontend utilisait `published_live` et `site.readiness.has_live_pages`.
- Donc une page “poussée” mais non confirmée pouvait gonfler le chiffre.

Après audit :
- Le frontend compte uniquement :
```ts
sitePublications.filter((item) => item.live_verified).length
```

Pourquoi :
- Le chiffre affiché doit représenter la réalité du site client, pas l’intention du moteur.

### 5. Problèmes de crawl

Avant audit :
- `crawl_report.produced_data.crawl_issues`
- Source backend :
```php
'crawl_issues' => SeoSiteCrawlIssue::query()->where('site_id', $siteId)->count()
```

Problème :
- C’était le cumul historique de toutes les issues du site.
- Donc possible d’afficher `241` alors que le crawl de référence montre `8 points à surveiller`.

Après audit :
- `crawl_issues` vient du crawl de référence affiché :
```php
$issueRows = SeoSiteCrawlIssue::query()
    ->where('site_id', $site->site_id)
    ->where('site_crawl_id', $referenceCrawl->id)
    ->get();

'crawl_issues' => $issueRows->count()
```

Pourquoi :
- Le bloc `Vue simple du site` doit parler de la même photographie que `Suivi du crawl`.

### 6. Points à surveiller

Source :
- `site.last_successful_crawl.issues_count`

Code :
- `serializeObservedCrawl()`

Logique :
```php
'issues_count' => (int) ($meta['issues_count'] ?? 0)
```

Pourquoi :
- Cette valeur vient du résumé stocké dans `seo_site_crawls.meta_json`.
- Elle reflète le résultat synthétique du crawl affiché comme référence.

### 7. Créer un article bientôt prêt

Source UI :
- Carte `Actions à faire maintenant`

Source data :
- `site.summary.new_queries`
- `site.readiness.gsc_connected`

Code backend :
- `ClientSitesController::searchConsoleSnapshot()`

Requêtes de base :
```php
SeoSearchConsoleMetric::query()
    ->where('site_id', $siteId)
    ->whereNotNull('query')
    ->where('window_days', 28)
    ->whereDate('metric_date', '>=', latestMetricDate - 45 jours)
```

Puis :
- groupement par `query`
- calcul des impressions courantes vs précédentes
- `new_queries = previous_impressions === 0 && impressions > 0`

Code frontend :
```ts
stage: generationReady ? "now" : gscConnected ? "soon" : "prep"
```

Pourquoi :
- `Bientôt prêt` ne veut pas dire “cassé”.
- Cela veut dire : Search Console remonte bien, mais aucune requête assez nette n’est encore montée au niveau “ouvrir un article maintenant”.

## Décisions de stabilisation prises

### Corrigé immédiatement

- `Live vérifié` ne repose plus sur `published_live` seul.
- `X contenus live` compte les contenus `live_verified`.
- `Problèmes de crawl` ne montre plus un cumul historique quand la page parle du dernier crawl.

### Gardé tel quel

- `8 points à surveiller` : cohérent avec le dernier crawl réussi.
- `Installation à relancer` : cohérent avec `remote_installations`.
- `Créer un article bientôt prêt` : cohérent, mais explicitement dérivé côté UI à partir de données GSC réelles.

## Règle de vérité retenue pour Stabilization V1

Un bloc `Automatisations` ne doit jamais afficher un statut plus fort que sa preuve réelle :

- `Live vérifié` exige une URL observée en HTTP `< 400`
- `visible` exige `live_verified = true`
- `problèmes de crawl` doit parler du crawl affiché, pas de tout l’historique
- `bientôt prêt` est acceptable si le produit explique que le moteur attend encore un signal suffisant
