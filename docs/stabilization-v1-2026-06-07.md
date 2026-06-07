# Stabilization V1

Date: 2026-06-07

## Objectif

Remettre PraeviSEO dans un état fiable, lisible et vérifiable avant toute nouvelle évolution UI ou SEO.

## Tableau de vérité actuel

| Fonction | État | Test réel | Résultat |
| --- | --- | --- | --- |
| GSC | OK | `seo:smoke-check` + connexion Amiantix | La connexion Google existe et les métriques remontent. |
| Crawl | OK | Crawl Amiantix observé | Le crawl complet remonte des pages, des issues et un historique visible. |
| Génération article | Partiel | Action premium génération | Le brouillon moteur peut être créé, mais la qualité éditoriale reste à confirmer. |
| Publication | KO | Test réel live | Des URLs live enregistrées peuvent encore répondre en 404. |
| Images | À vérifier | Action premium image | Le flux image existe, mais les erreurs CTA/redirect devaient être stabilisées avant validation. |
| Réécriture | À vérifier | Action premium rewrite | Le flux existe, mais le retour visible et la qualité doivent être confirmés à l’écran. |
| Maillage | À vérifier | Action premium linking | Le flux existe, mais le résultat utile doit encore être validé sans logs serveur. |

## Règles de Stabilization V1

- Ne jamais afficher `Publié`, `Visible`, `Succès` ou `Terminé` si la réalité n’est pas confirmée.
- Ne jamais considérer `published_live` comme preuve suffisante.
- Si `live_url` existe mais que la lecture observée répond `404`, l’état est `Publication à vérifier`.
- Si un score n’a pas assez de signal réel, le masquer ou le qualifier comme `à confirmer`.

## Sources de vérité utilisées

- `seo_pages.status`
- `seo_pages.published_live`
- `seo_pages.published_live_at`
- `seo_pages.live_url`
- `seo_site_pages.last_status_code`
- `seo_sites.settings_json.automation.actions`
- `seo_sites.settings_json.automation.history`

## Corrections engagées dans cette passe

1. Correction du faux traitement de `NEXT_REDIRECT` dans les server actions premium.
2. Tableau `Vérité du système` visible dans la page santé technique.
3. Historique réel des actions + dernières erreurs visibles dans `Automatisations`.
4. Article complet replié par défaut dans `Publications`.

## Étapes suivantes

1. Vérifier en vrai les actions `image`, `rewrite`, `linking` après correction `NEXT_REDIRECT`.
2. Supprimer les faux statuts restants dans `Dashboard` et `Cockpit site`.
3. Faire l’audit score par score avant tout nouvel affichage “fort”.
