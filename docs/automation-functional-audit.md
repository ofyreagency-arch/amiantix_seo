# Audit fonctionnel des automatisations

## Objectif

Répondre clairement, pour chaque automatisation visible côté client :

- ce qui est réellement exécuté aujourd hui
- quel endpoint, service, job ou commande est lancé
- où le résultat est stocké
- ce qui devient visible dans l interface
- ce qui reste simulé ou partiellement branché

Automatisations auditées :

- Crawl
- Créer un article
- Réécriture
- Maillage
- Image SEO
- Publication

## Résumé exécutif

### Fonctionnel

- `Crawl`
- `Créer un article`
- `Réécriture`
- `Maillage`
- `Image SEO`
- `Publication`

### Non branché

- aucune des 6 actions auditées n est totalement non branchée côté backend

Le vrai sujet n est donc plus “est-ce que les boutons existent ?”  
Le vrai sujet est :

- certaines actions produisent une valeur **réelle**
- d autres produisent surtout une **préparation** ou dépendent d une config externe

## Important : ce qui est mocké

Le frontend peut encore simuler toutes les actions si le backend n est pas configuré.

Point de contrôle :

- `frontend/src/lib/praeviseo-api.ts`
- chaque méthode `requestPremium...()` contient un fallback `if (!backendConfigured())`

Conséquence :

- en environnement non branché, les cartes et statuts peuvent bouger sans moteur réel
- en environnement branché, les actions passent bien par l API Laravel

Donc l audit ci-dessous parle du **mode backend réellement configuré**.

---

## 1. Crawl

### Statut

- `Fonctionnel`

### Ce qui est réellement exécuté

Le bouton `Lancer un crawl` déclenche un vrai crawl observé.

Chaîne d exécution :

1. UI
   - `frontend/src/app/(app)/sites/[siteId]/automation/page.tsx`
   - bouton `Lancer un crawl`
2. action serveur
   - `frontend/src/app/(app)/sites/[siteId]/connect/actions.ts`
   - `launchPremiumCrawlAction()`
3. API client
   - `POST /api/client/sites/{siteId}/crawl`
   - `routes/api.php`
4. contrôleur
   - `ClientSitesController::startObservedCrawl()`
5. backend
   - crée un `SeoSiteCrawl`
   - puis dispatch `RunObservedSiteCrawlJob`
6. job
   - `RunObservedSiteCrawlJob`
   - queue `observed-crawls`
7. moteur réel
   - `SiteCrawlerService::crawlQueued()`
   - puis `PremiumAutomationLoopService::runForSite()`

### Job / commande / service lancé

- job queue : `RunObservedSiteCrawlJob`
- service crawl : `SiteCrawlerService`
- service boucle premium ensuite : `PremiumAutomationLoopService`

### Où le résultat est stocké

- table / modèle `seo_site_crawls` via `SeoSiteCrawl`
- table / modèle `seo_site_crawl_issues`
- données observées du site via les tables/pages observées du runtime
- historique d exécution dans :
  - `seo_sites.settings_json -> automation.history`

### Ce qui devient visible dans l interface

- `site.crawl`
- `site.action_statuses.crawl`
- `site.execution_history`
- puis effets indirects dans :
  - `Automatisations`
  - `Activité`
  - `Pages`
  - `Queries`
  - `Publications`

### Limites actuelles

- dépend d un worker queue réel
- dépend de la qualité du crawl et de l observation live
- l impact SEO n est pas direct : le crawl alimente les autres actions

### Verdict

- le crawl est **réel et branché**
- il produit surtout de la **matière de décision**
- ce n est pas un simple changement d état UI

---

## 2. Créer un article

### Statut

- `Fonctionnel`

### Ce qui est réellement exécuté

Le bouton `Créer un article` déclenche une vraie création d article dans le moteur.

Chaîne d exécution :

1. UI
   - bouton `Créer un article`
2. action serveur
   - `launchPremiumGenerationAction()`
3. API client
   - `POST /api/client/sites/{siteId}/generate`
4. contrôleur
   - `ClientSitesController::startPremiumArticleGeneration()`
5. décision de sujet
   - `PremiumArticleGenerationService::resolveCandidateKeyword()`
6. génération réelle
   - `SeoGeneratePageRunner::run()`
   - avec `SeoEngineContext`

### Job / commande / service lancé

- pas de job queue dédié ici
- service de décision : `PremiumArticleGenerationService`
- service/génération réelle : `SeoGeneratePageRunner`
- moteur de contenu sous-jacent : `SeoGenerationService`

### Où le résultat est stocké

Dans `seo_pages` / modèle `SeoPage` :

- `keyword`
- `slug`
- `title`
- `h1`
- `meta_description`
- `content`
- `faq_json`
- `schema_json`
- `internal_links_json`
- `generation_source`
- `generation_error`
- `generation_trace_json`

Et aussi :

- `seo_sites.settings_json -> automation.actions.generation`
- `seo_sites.settings_json -> automation.history`

### Ce qui devient visible dans l interface

- `action_statuses.generation`
- `execution_history`
- publications / contenus si la page entre dans les listes client
- cockpit / activity si du contenu est ensuite visible ou suivi

### Ce qui reste partiel

- le choix du sujet est réel, mais repose sur les métriques GSC disponibles
- la valeur réelle dépend ensuite de :
  - la qualité du sujet choisi
  - la qualité du contenu généré
  - la publication ensuite

### Verdict

- la création d article est **réellement branchée**
- elle crée une vraie `SeoPage`
- la vraie question maintenant est la **qualité du sujet et du contenu**, pas le branchement

---

## 3. Réécriture

### Statut

- `Fonctionnel`

### Ce qui est réellement exécuté

Le bouton `Préparer une réécriture` va maintenant jusqu à une vraie application de la réécriture, puis à une republication live si la cible est prête.

Chaîne d exécution :

1. UI
   - bouton `Préparer une réécriture`
2. action serveur
   - `launchPremiumRewriteAction()`
3. API client
   - `POST /api/client/sites/{siteId}/rewrite`
4. contrôleur
   - `ClientSitesController::startPremiumRewrite()`
5. sélection de page
   - `resolveRewriteCandidatePage()`
6. production de suggestion
   - `SeoRewriteService::createSuggestion(..., 'enrich')`
7. application réelle
   - `SeoSuggestionWorkflowService::apply()`
8. republication live si la cible est prête
   - `SeoLivePublicationService::publish()`
9. suivi après publication
   - relecture relancée via `scheduleObservedCrawlIfIdle(..., 'after_publication')`

### Job / commande / service lancé

- service : `SeoRewriteService`
- service d application : `SeoSuggestionWorkflowService`
- service de publication live : `SeoLivePublicationService`
- crawl post-publication :
  - `scheduleObservedCrawlIfIdle()`
  - `RunObservedSiteCrawlJob`

### Où le résultat est stocké

Dans `seo_suggestions` / modèle `SeoSuggestion` :

- `source`
- `signals_json`
- `suggestions_json`
- `status = applied`
- `applied_at`

Dans `seo_pages` :

- `title`
- `meta_description`
- `content`
- champs suggestion appliqués selon le payload
- `published_live`
- `published_live_at`
- `live_url`

Et aussi :

- `seo_sites.settings_json -> automation.actions.rewrite`
- `seo_sites.settings_json -> automation.history`

### Ce qui devient visible dans l interface

- `action_statuses.rewrite`
- `execution_history`
- `latest_suggestion` dans `publications`, `pages`, `activity`, `dashboard`
- puis, si la cible live est prête :
  - `published_live`
  - `live_url`
  - nouvelle relecture déclenchée

### Limites actuelles

- si la cible live n est pas encore actionnable, la réécriture s applique bien côté moteur mais attend la prochaine vraie publication
- la qualité du résultat dépend toujours de la qualité de la suggestion initiale

### Verdict

- la réécriture est maintenant **réellement exécutée de bout en bout**
- elle couvre sélection, génération, application, publication éventuelle et suivi
- la question suivante n est plus le branchement, mais la **qualité de la recommandation produite**

---

## 4. Maillage

### Statut

- `Fonctionnel`

### Ce qui est réellement exécuté

Le bouton `Renforcer le maillage` va plus loin qu une simple suggestion :

- il cherche ou crée une suggestion de liens internes
- puis l applique réellement au contenu stocké

Chaîne d exécution :

1. UI
   - bouton `Renforcer le maillage`
2. action serveur
   - `launchPremiumLinkingAction()`
3. API client
   - `POST /api/client/sites/{siteId}/linking`
4. contrôleur
   - `ClientSitesController::startPremiumInternalLinking()`
5. sélection cible
   - `resolveInternalLinkingCandidate()`
6. création suggestion si besoin
   - `SeoRewriteService::createSuggestion(..., 'add-internal-links-only')`
7. application réelle
   - `SeoSuggestionWorkflowService::apply()`
8. post-action
   - relance d un crawl observé `after_linking`

### Job / commande / service lancé

- service suggestion : `SeoRewriteService`
- service d application : `SeoSuggestionWorkflowService`
- crawl post-action :
  - `scheduleObservedCrawlIfIdle()`
  - `RunObservedSiteCrawlJob`

### Où le résultat est stocké

Dans `seo_pages` / modèle `SeoPage` :

- `internal_links_json`
- éventuellement autres champs suggestion si présents
- `status` peut passer vers revue selon workflow

Dans `seo_suggestions` :

- suggestion appliquée
- `status = applied`
- `applied_at`

Dans `seo_sites.settings_json` :

- `automation.actions.linking`
- `automation.history`

### Ce qui devient visible dans l interface

- `action_statuses.linking`
- `execution_history`
- `publications.items[].observed_content.internal_link_suggestions_count`
- `publications.items[].latest_suggestion`
- `pages`, `activity`, `site detail`, `automation`

### Limites actuelles

- dépend du fait qu il y ait assez de contexte observé
- peut échouer s il n y a pas de liens jugés assez sûrs

### Verdict

- le maillage est **réellement exécuté**
- ce n est pas juste une suggestion visuelle
- c est une des automatisations les plus concrètes actuellement

---

## 5. Image SEO

### Statut

- `Fonctionnel`

### Ce qui est réellement exécuté

Le bouton `Générer l’image SEO` déclenche une vraie génération d image via OpenAI, l associe à la page, puis republie la page si la cible live est prête.

Chaîne d exécution :

1. UI
   - bouton `Générer l’image SEO`
2. action serveur
   - `launchPremiumImageAction()`
3. API client
   - `POST /api/client/sites/{siteId}/images`
4. contrôleur
   - `ClientSitesController::startPremiumImageGeneration()`
5. sélection de page
   - `resolveImageCandidatePage()`
6. génération réelle
   - `SeoPageImageGenerator::generate()`
   - appel HTTP OpenAI `/v1/images/generations`
7. validation image
   - `SeoPageImageGenerator::approve()`
8. republication live si la cible est prête
   - `SeoLivePublicationService::publish()`
9. suivi après publication
   - relecture relancée via `scheduleObservedCrawlIfIdle(..., 'after_publication')`

### Job / commande / service lancé

- service : `SeoPageImageGenerator`
- appel HTTP réel OpenAI
- service de publication live : `SeoLivePublicationService`
- crawl post-publication :
  - `scheduleObservedCrawlIfIdle()`
  - `RunObservedSiteCrawlJob`

### Où le résultat est stocké

Dans le disque `public` :

- fichier image sous `storage/app/public/seo-pages/...`

Dans `seo_pages` :

- `image_prompt`
- `image_path`
- `image_alt`
- `image_status`
- `image_quality_json`
- `published_live`
- `published_live_at`
- `live_url`

Dans `seo_sites.settings_json` :

- `automation.actions.images`
- `automation.history`

### Ce qui devient visible dans l interface

- `action_statuses.images`
- `execution_history`
- publications/pages quand la page enrichie est listée
- score/indexabilité image côté moteur
- puis, si la cible live est prête :
  - `published_live`
  - `live_url`
  - nouvelle relecture déclenchée

### Limites actuelles

- dépend d `OPENAI_API_KEY`
- dépend du stockage public
- si la cible live n est pas prête, l image reste correctement associée côté moteur et attend la prochaine vraie publication

### Verdict

- backend réellement branché
- génération, stockage, association, publication et suivi sont couverts
- le vrai risque restant est surtout la dépendance externe OpenAI et stockage

---

## 6. Publication

### Statut

- `Fonctionnel`

### Ce qui est réellement exécuté

Le bouton `Publier` pousse une vraie page du moteur vers le site live ou vers le runtime selon le mode de publication.

Chaîne d exécution :

1. UI
   - bouton `Publier`
2. action serveur
   - `launchPremiumPublicationAction()`
3. API client
   - `POST /api/client/sites/{siteId}/publish`
4. contrôleur
   - `ClientSitesController::startPremiumPublication()`
5. sélection de page
   - `resolvePublicationCandidatePage()`
6. publication réelle
   - `SeoLivePublicationService::publish()`
7. selon le mode :
   - `laravel_bridge`
   - `symfony_bridge`
   - `wordpress_bridge`
   - `webhook_api`
   - `runtime`
8. post-publication
   - observation live
   - sitemap mémorisé
   - crawl relancé `after_publication`

### Job / commande / service lancé

- service publication : `SeoLivePublicationService`
- éventuellement requête HTTP signée vers le bridge/site
- post-observation : `PublishedPageObservationService`
- crawl post-action : `RunObservedSiteCrawlJob`

### Où le résultat est stocké

Dans `seo_pages` :

- `published_live`
- `published_live_at`
- `live_url`
- éventuellement `canonical_url`

Dans `seo_site_sitemaps`

Dans `seo_sites.settings_json -> publication`

Dans `seo_sites.settings_json -> automation.history`

### Ce qui devient visible dans l interface

- `action_statuses.publication`
- `execution_history`
- `publications.items[].published_live`
- `published_live_at`
- `live_url`
- stats `live_published`
- activity / dashboard / publications

### Limites actuelles

- nécessite un bridge ou un mode de publication prêt
- peut être bloqué par endpoint, secret ou bridge mal branché

### Verdict

- la publication est **réellement branchée**
- elle touche un vrai état live
- c est une des actions les plus “productives” du moteur quand le bridge est sain

---

## Ce que voit réellement l interface après exécution

Les résultats ne remontent pas tous au même endroit.

### Source principale des statuts d action

- `ClientSitesController::actionStatuses()`
- sérialise :
  - `crawl`
  - `generation`
  - `rewrite`
  - `linking`
  - `images`
  - `publication`
  - `monitoring`

Stockage source :

- `seo_sites.settings_json -> automation.actions`
- plus `seo_site_crawls` pour le crawl

### Historique utilisateur

- `seo_sites.settings_json -> automation.history`
- alimenté par `appendExecutionHistory()`

### Contenus / résultats métier visibles

Exposés dans :

- `ClientWorkspaceController::publications()`

À partir de :

- `seo_pages`
- `seo_suggestions`
- `seo_search_console_metrics`
- `seo_site_page_snapshots`
- `seo_semantic_links`

Donc après exécution, la vraie valeur remonte surtout dans :

- `Automatisations`
- `Publications`
- `Pages`
- `Queries`
- `Activity`
- `Dashboard`

---

## Ce qui est encore simulé ou partiellement branché

### Simulé

Si `backendConfigured()` est faux dans le frontend :

- toutes les actions `requestPremium...()` renvoient des états mockés
- certaines listes client continuent d utiliser des projections locales

Donc :

- hors backend branché, les cartes ne prouvent rien

### Partiellement branché

1. `Réécriture`
- prépare une suggestion réelle
- mais ne pousse pas encore une réécriture entièrement appliquée/live via ce bouton

2. `Image SEO`
- backend réel
- mais dépend de la clé OpenAI et d un stockage public correct

3. `Monitoring`
- pas demandé dans la liste d audit, mais important
- c est un état dérivé du crawl, du bridge et de GSC
- pas une exécution manuelle autonome comme les 6 autres actions

---

## Tableau final

### Crawl

- Statut : `Fonctionnel`
- Valeur réelle : alimente le moteur observé
- Stockage : `seo_site_crawls`, `seo_site_crawl_issues`, données observées

### Créer un article

- Statut : `Fonctionnel`
- Valeur réelle : crée une vraie `SeoPage`
- Stockage : `seo_pages`, historique, statuts

### Réécriture

- Statut : `Partiellement fonctionnel`
- Valeur réelle : crée une vraie suggestion
- Stockage : `seo_suggestions`, statuts, historique

### Maillage

- Statut : `Fonctionnel`
- Valeur réelle : applique des liens internes sur la page
- Stockage : `seo_pages.internal_links_json`, `seo_suggestions`

### Image SEO

- Statut : `Partiellement fonctionnel`
- Valeur réelle : génère une vraie image si OpenAI est disponible
- Stockage : fichier public + champs image dans `seo_pages`

### Publication

- Statut : `Fonctionnel`
- Valeur réelle : pousse une page en live et relance l observation
- Stockage : `seo_pages.published_live/live_url`, sitemap, historique, statuts

---

## Conclusion produit

Les automatisations réellement solides aujourd hui sont :

- `Crawl`
- `Créer un article`
- `Maillage`
- `Publication`

Les automatisations encore à clarifier comme promesse produit sont :

- `Réécriture`
- `Image SEO`

Le prochain vrai chantier n est donc plus “brancher les boutons”.

Le prochain chantier est :

1. mesurer la valeur réelle des actions déjà fonctionnelles
2. rendre les actions partielles plus complètes
3. éliminer les zones mockées dans le client quand on veut prouver la valeur réelle du moteur
