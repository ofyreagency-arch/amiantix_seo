# Automatisations — Audit priorité et génération

Date : 2026-06-07

## Action prioritaire

Le bloc `Action prioritaire` ne choisit plus une action générique par défaut.

Ordre de priorité actuel :

1. `indexation_alerts`
   Source : `ClientSitesController::searchConsoleSnapshot()`
   Requête : `seo_search_console_metrics` page-level (`query IS NULL`)
   Raison : une page hors index reste le blocage le plus direct pour la visibilité.

2. `observed_link_gap_pages`
   Source : `observedSiteSnapshot()`
   Requête : pages observées du crawl de référence
   Raison : si Google n’a pas d’alerte forte mais que le site manque de maillage, l’action suivante utile est de renforcer les liens internes.

3. `generation_audit`
   Source : `ClientSitesController::generationAudit()`
   Requête : `seo_search_console_metrics` query-level (`query IS NOT NULL`, `window_days = 28`)
   Raison : si aucun sujet article n’est encore retenu, le client doit voir pourquoi avant de cliquer au hasard.

4. `next_action`
   Source : moteur runtime existant
   Raison : fallback quand aucun blocage plus concret n’est détecté.

## Audit `Créer un article`

Le bloc utilise maintenant `summary.generation_audit`.

### Données exposées

- `queries_analyzed_count`
- `eligible_queries_count`
- `rejected_queries_count`
- `minimum_query_impressions`
- `maximum_query_position`
- `min_hours_between_articles`
- `max_articles_per_28_days`
- `limit_reason`
- `best_query`
- `rejection_breakdown`

### Règles de rejet visibles

- `volume_trop_faible`
- `position_inconnue`
- `position_trop_lointaine`
- `deja_couverte`

### Pourquoi ce bloc existe

Avant cette passe, `Créer un article` disait seulement :

> PraeviSEO n'a pas encore trouvé une recherche Google assez utile

Sans dire :

- combien de requêtes ont été lues
- quel seuil était demandé
- quelle requête était la meilleure
- pourquoi elle était refusée

Le blocage paraissait donc arbitraire.

Maintenant, même sans sujet retenu, l’écran répond :

1. combien de requêtes ont été analysées
2. quel seuil minimum le moteur applique
3. quelle est la meilleure requête vue
4. pourquoi elle ne part pas encore
