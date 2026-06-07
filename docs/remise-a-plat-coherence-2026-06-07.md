# Remise à Plat Cohérence Produit - 7 juin 2026

Objectif : rétablir la confiance entre base de données, moteur et interface avant toute nouvelle couche SEO.

## Règle de lecture

- `Statut réel` : ce que l'écran devrait dire sans enjoliver.
- `Source SQL/API` : d'où vient réellement l'information.
- `Lien réel ouvert` : ce que le CTA ouvre effectivement.
- `Incohérence connue` : ce qui peut encore tromper l'utilisateur.

## Publications / Studio

### Statut réel

- Un contenu peut être :
  - `Encore dans le moteur`
  - `Publication à vérifier`
  - `Visible sur le site`
- Ce statut ne doit plus dépendre seulement de `published_live`.

### Source SQL/API

- Table principale : `seo_pages`
- Colonnes utilisées :
  - `status`
  - `published_at`
  - `published_live`
  - `published_live_at`
  - `live_url`
  - `content`
  - `meta_description`
  - `image_path`
  - `image_status`
- Vérification réelle du live :
  - relation `observedPage`
  - table observée derrière : `seo_site_pages`
  - colonne utilisée : `last_status_code`
- Endpoint :
  - `App\Http\Controllers\Api\ClientWorkspaceController::publications()`

### Lien réel ouvert

- `Voir l’article` :
  - ouvre `preview_url`
  - aujourd'hui : URL interne du studio `/publications?...#apercu-blog`
- `Voir le live` :
  - ouvre `live_url`
  - seulement si `live_verified = true`

### Incohérences connues

- `preview_url` n'est pas une vraie page publique client.
- C'est une ancre interne du studio.
- Si on veut un vrai preview client, il faudra une route de preview dédiée côté moteur ou côté bridge.

## Cockpit du site

### Statut réel

- Les cartes de contenu du cockpit site ne doivent plus dire `visible` sans vérification.

### Source SQL/API

- Même source que `Publications`
- Endpoint principal :
  - `App\Http\Controllers\Api\ClientSitesController`

### Lien réel ouvert

- `Ouvrir la cible` :
  - soit `live_url` si vérifié
  - soit renvoi vers le studio

### Incohérences connues

- Certains badges historiques peuvent encore donner une impression de “contenu prêt” alors que le live reste à confirmer.

## Automatisations

### Statut réel

- Les CTA sont branchés, mais leur effet visible dépend souvent du fait que le contenu remonte ensuite dans `Publications`.

### Source SQL/API

- Server actions :
  - `frontend/src/app/(app)/sites/[siteId]/connect/actions.ts`
- Backend :
  - `requestPremiumCrawl`
  - `requestPremiumGeneration`
  - `requestPremiumRewrite`
  - `requestPremiumLinking`
  - `requestPremiumImages`
  - `requestPremiumPublication`

### Lien réel ouvert

- CTA de vue :
  - navigation interne (`href`)
- CTA moteur :
  - `form action={...}`
  - puis redirect avec feedback

### Incohérences connues

- Si le contenu créé n'apparaît pas clairement dans `Publications`, l'utilisateur a l'impression que le CTA n'a rien fait.

## Pages

### Statut réel

- Les badges SEO doivent dépendre de signaux observés réels.

### Source SQL/API

- `seo_site_pages`
- `seo_site_page_snapshots`
- `seo_search_console_metrics`

### Lien réel ouvert

- navigation vers `Publications`, `Automatisations`, ou la page live selon le contexte

### Incohérences connues

- Une page peut avoir un score calculé sans encore avoir assez de signal Google.
- L'UI a commencé à le signaler via :
  - `Signal insuffisant`
  - `Signal léger`
  - `Score à confirmer`

## Opportunités

### Statut réel

- Les opportunités sont des priorités moteur.
- Ce ne sont pas des certitudes business tant que le signal Google reste faible.

### Source SQL/API

- `seo_suggestions`
- `seo_recommendations`
- `seo_search_console_metrics`

### Lien réel ouvert

- vers `Queries`, `Pages`, ou `Publications` selon le type

### Incohérences connues

- L'impact affiché reste une estimation moteur, pas un gain confirmé.

## Requêtes Google

### Statut réel

- Sert à repérer :
  - sujet à créer
  - page à renforcer
  - page à relier

### Source SQL/API

- `seo_search_console_metrics`
- filtré par site, fenêtre, et présence ou non d'une requête

### Lien réel ouvert

- selon le cas :
  - `Publications`
  - `Pages`
  - `Automatisations`

### Incohérences connues

- si la requête n'est pas encore assez forte, le CTA peut être “techniquement branché” mais produit peu de valeur visible.

## Dashboard SEO

### Statut réel

- Le dashboard doit être une entrée de priorisation, pas une preuve finale.

### Source SQL/API

- agrégats de sites
- signaux GSC
- contenus suivis
- état publication

### Lien réel ouvert

- renvoie vers les écrans spécialisés

### Incohérences connues

- certains regroupements restent encore trop denses pour un utilisateur non SEO.

## Décisions prises dans ce sprint

- Ne plus afficher `Voir le live` si l'URL n'est pas vérifiée.
- Ne plus afficher `visible sur le site` si `last_status_code` ne confirme pas le live.
- Exposer le corps complet du contenu moteur dans l'API `publications`.
- Afficher l'article complet dans le studio au lieu d'un simple extrait.

## Ce qu'il reste à assainir

- Créer un vrai système de preview public ou semi-public si on veut autre chose qu'un preview interne du studio.
- Uniformiser tous les badges `prêt / live / visible / à vérifier` sur les mêmes règles.
- Réduire la densité des écrans pour qu'un artisan comprenne :
  - le problème
  - la cause
  - l'action
  - le gain
  en moins de 30 secondes.
