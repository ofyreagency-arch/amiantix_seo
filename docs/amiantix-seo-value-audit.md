# Audit Amiantix

## Objectif

Vérifier la valeur SEO réelle produite par PraeviSEO sur Amiantix, en séparant :

- les signaux **réels moteur/backend**
- les projections **mock/demo frontend**
- les recommandations réellement utiles
- le bruit produit par des heuristiques encore trop génériques

Ce document n est pas un audit d interface. C est un audit du **cerveau produit**.

## Résumé exécutif

Le moteur commence à raconter quelque chose d utile sur Amiantix, mais il y a encore trois fragilités majeures :

1. une dépendance cassée au preset `Examples\\AmiantixPreset` pouvait faire tomber les automatisations
2. plusieurs signaux visibles côté client restent encore partiellement alimentés par du **mock frontend**
3. les recommandations réelles sont encore trop souvent **structurelles/génériques** et pas assez **décisionnelles/site-first**

Correctif déjà appliqué dans ce sprint :

- remplacement des providers cassés `AmiantixInternalLinkProvider` et `AmiantixImagePromptProvider` par une logique **locale**, sans dépendance externe au preset d exemple

## Ce que le moteur voit aujourd hui

### 1. Signaux réels backend

Le backend produit déjà de vrais signaux depuis :

- `RecommendationEngineService`
- `RuntimeSeoMonitoringService`
- `ObservedPageHealthService`
- `SearchConsoleService`

Les familles de signaux réels aujourd hui sont :

- pages orphelines
- pages faibles
- overlaps / cannibalisation potentielle
- clusters sous-couverts
- monitoring observé
- métriques Search Console

### 2. Signaux encore projetés côté frontend

Le client affiche encore une partie de la réalité Amiantix via des données mockées dans :

- `frontend/src/lib/praeviseo-api.ts`

En particulier, plusieurs blocs Amiantix visibles dans le workspace sont aujourd hui encore définis dans des objets de projection locale :

- `top_queries`
- `top_rising_pages`
- `top_falling_pages`
- `gsc_opportunities`
- `recommendations`
- une partie des statuts de monitoring

Conclusion :

- l interface donne déjà une **bonne forme** au produit
- mais la **preuve de valeur** reste partiellement contaminée par des données de démo

Tant que ces signaux ne viennent pas tous du runtime, il faut éviter de confondre :

- un signal produit utile
- un exemple de signal utile

## Opportunités Amiantix actuellement visibles

Les opportunités visibles côté client aujourd hui sont surtout :

1. `near_top_10` sur `diagnostic-amiante-copropriete`
2. `low_ctr` sur `qui-sommes-nous`
3. `emerging_query` sur `guide-reperage-avant-travaux`

### 1. Diagnostic amiante en copropriete

- Pourquoi ça remonte :
  - page proche du top 10
  - visibilité déjà existante
  - refresh possible
- Pertinence :
  - `Pertinent`
- Pourquoi :
  - requête métier cohérente avec Amiantix
  - intention utile
  - potentiel de gain crédible
  - action concrète compréhensible
- Limite actuelle :
  - le moteur ne relie pas encore assez explicitement la recommandation à :
    - la requête précise
    - la faiblesse précise
    - le delta de gain attendu

### 2. Qui sommes nous

- Pourquoi ça remonte :
  - CTR faible malgré visibilité
- Pertinence :
  - `Moyen` à `Bruit`
- Pourquoi :
  - page corporate
  - valeur SEO business indirecte
  - faible priorité par rapport à une page d intention métier ou locale
- Décision utilisateur réellement utile :
  - ne pas traiter cela avant les pages services / requêtes business
- Conclusion moteur :
  - le moteur manque encore d un filtre `page business value`

### 3. Guide repérage avant travaux

- Pourquoi ça remonte :
  - requête émergente
  - visibilité en cours
- Pertinence :
  - `Pertinent`
- Pourquoi :
  - thématique très cohérente avec Amiantix
  - bon sujet de contenu de soutien
  - potentiel éditorial et maillage crédible
- Limite actuelle :
  - la recommandation `creer une section utile` est trop générique
  - il faut dire :
    - quelle section
    - quel angle
    - quelle requête renforcer

## Recommandations actuellement visibles

### 1. Refresh the FAQ cluster page

- Source probable :
  - mock frontend / recommandation projetée
- Pertinence :
  - `Moyen`
- Pourquoi :
  - la logique `page ranke deja, manque de profondeur` est saine
  - mais le titre est encore trop abstrait
  - `FAQ cluster page` ne parle pas assez au client
- À améliorer :
  - nommer la page réelle
  - nommer la requête réelle
  - nommer la faiblesse réelle

### 2. Reinforce the main diagnostic page with stronger internal links

- Source probable :
  - recommandation structurelle backend crédible
- Pertinence :
  - `Pertinent`
- Pourquoi :
  - le maillage interne est un vrai levier sur un petit site expert
  - surtout si une page business proche du top 10 existe déjà
- Limite actuelle :
  - il manque les pages sources exactes
  - il manque un impact attendu lisible

### 3. Expand cluster: réglementation

- Source probable :
  - logique `content_gap`
- Pertinence :
  - `Moyen`
- Pourquoi :
  - la logique de cluster sous-couvert est valable
  - mais `réglementation` est trop large pour être un vrai brief de production
- Ce qu il faudrait à la place :
  - un angle précis
  - une requête observée
  - un besoin utilisateur clair

## Pages : ce qui semble utile vs bruit

### Pages utiles à pousser

- `diagnostic-amiante-copropriete`
  - `Pertinent`
  - page business claire
  - proche du top 10
  - logique de refresh + maillage crédible

- `guide-reperage-avant-travaux`
  - `Pertinent`
  - bon contenu de soutien
  - bon point d entrée documentaire

### Pages à faible priorité réelle

- `qui-sommes-nous`
  - `Moyen/Bruit`
  - utile pour confiance, pas prioritaire pour croissance SEO

## Requêtes : ce qui semble utile vs bruit

### Requêtes pertinentes

- `repérage amiante avant travaux`
  - `Pertinent`
  - forte cohérence métier
  - bon signal éditorial

- `faq amiante`
  - `Moyen`
  - bon sujet, mais à surveiller selon volume réel
  - peut être trop informationnel seul

- `combien coute un diagnostic amiante`
  - `Pertinent`
  - bon signal commercial
  - mérite probablement une page ou section dédiée si le volume confirme

### Requêtes à relativiser

- `amiantix`
  - `Bruit` pour la priorisation SEO de croissance
  - utile pour le suivi marque, pas pour guider les premiers efforts business

## Monitoring : valeur réelle attendue

Le monitoring devient utile si, pour chaque page importante, il dit :

- la page est saine ou non
- pourquoi
- quel risque concret existe
- quelle action PraeviSEO ouvre ensuite

Aujourd hui, l architecture monitoring est là, mais la projection client doit encore davantage parler du site :

Au lieu de :

- `PraeviSEO fait...`

il faut afficher :

- pages analysées
- opportunités trouvées
- contenus prêts
- actions en attente
- impact observé

## Faux positifs identifiés

1. `low_ctr` sur une page corporate peut être sur-priorisé
2. `cluster réglementation` est trop large pour être directement actionnable
3. des signaux faibles peuvent sembler crédibles alors qu ils sont encore alimentés par du mock

## Recommandations trop génériques

Aujourd hui, plusieurs recommandations restent encore trop vagues :

- `Improve coverage depth`
- `Strengthen headings`
- `Add contextual links`
- `Create supporting pages`

Le client a besoin de :

- quelle page
- quelle requête
- quel bloc
- quel angle
- quel gain attendu

## Priorités mal classées

À corriger dans le moteur :

1. une page corporate faible CTR ne doit pas passer avant une page business proche du top 10
2. une opportunité de cluster doit être reliée à une requête ou un gap concret, pas à une étiquette large
3. les opportunités doivent intégrer un score de **valeur business** et pas seulement un score structurel

## Signaux qui manquent

Les manques les plus visibles pour rendre le cerveau plus crédible :

- séparation brand / non-brand
- score de valeur business de la page
- score de confiance de l opportunité
- seuil minimum d impressions avant de pousser une action
- identification plus forte des pages services/locales à argent
- lien explicite entre requête, page, recommandation et impact attendu
- impact observé après action

## Corrections moteur recommandées

### Priorité 1

- supprimer les dépendances preset externes cassées
- taguer chaque signal client avec son origine :
  - `live`
  - `mock`
  - `projected`

### Priorité 2

- filtrer les opportunités par valeur business
- déclasser les pages corporate
- séparer brand / non-brand dans les requêtes

### Priorité 3

- rendre chaque recommandation plus concrète :
  - page cible
  - requête cible
  - faiblesse exacte
  - action précise
  - impact estimé

### Priorité 4

- injecter un score de confiance
- injecter un seuil de données minimales
- mieux relier monitoring et recommandations ouvertes

## Ce qu un utilisateur devrait réellement faire ensuite

Sur Amiantix, l ordre d action produit le plus crédible serait :

1. retravailler `diagnostic-amiante-copropriete`
2. enrichir `guide-reperage-avant-travaux`
3. ouvrir une action maillage précise vers les pages business
4. surveiller les requêtes informationnelles utiles
5. laisser en bas de pile les pages corporate

## Ce qu il faut mesurer maintenant sur le VPS

Pour sortir définitivement du mock et auditer la vraie valeur :

1. top opportunités **réelles** remontées par l API
2. pages réelles issues du crawl observé
3. requêtes réelles issues de GSC
4. recommandations réelles persistées dans `seo_recommendations`
5. monitoring réel des pages critiques
6. impact après une première action réelle :
   - impressions
   - CTR
   - position
   - indexation

## Conclusion

PraeviSEO commence à avoir un **bon squelette de décision SEO** sur Amiantix.

Ce qui fonctionne déjà :

- la logique de priorisation near-top-10
- la logique de contenu de soutien
- la logique maillage / refresh / monitoring

Ce qui décrédibilise encore le moteur :

- les dépendances cassées
- les projections mock encore visibles
- les recommandations trop génériques
- l absence de score de valeur business

La prochaine phase utile n est pas de refaire l interface.

C est de rendre les recommandations :

- plus vraies
- plus concrètes
- plus hiérarchisées
- plus reliées à la performance réelle du site
