# Audit moteur éditorial — 7 juin 2026

## Objectif
Comparer l'ancien moteur de génération avec le moteur actuel pour comprendre pourquoi un article long peut aujourd'hui sortir avec un rendu trop générique.

Le sujet ici n'est pas l'UI. Le sujet est la confiance dans la qualité éditoriale réellement produite.

## Chaîne réelle actuelle

### 1. Entrée
- service: `seo-engine-app/app/Runtime/PremiumArticleGenerationService.php`
- rôle:
  - choisit une requête GSC candidate
  - évite les doublons par rapprochement `keyword / slug / title`
  - ne juge pas la qualité éditoriale finale

### 2. Blueprint métier
- fichier: `seo-engine-app/app/SeoPresets/Amiantix/AmiantixBlueprintProvider.php`
- rôle:
  - construit un `topic`
  - déduit un `cluster`
  - déduit une `family`
  - injecte des tableaux de risques, obligations, cas, mistakes, FAQ, work units

Constat:
- la base métier est riche
- mais elle reste très "catalogue de blocs"
- beaucoup de matière sert ensuite à remplir une structure plus qu'à faire émerger un angle fort

### 3. Prompt
- fichier: `seo-engine-app/app/SeoPresets/Amiantix/AmiantixPromptProfile.php`
- rôle:
  - `generationCorePrompt()`
  - `generationFaqPrompt()`
  - `improvementPrompt()`
  - `rewritePrompt()`

Constat:
- le prompt est plus strict qu'avant sur la structure
- mais il pousse surtout:
  - profondeur
  - sections
  - tableau
  - FAQ
  - jargon métier
- il pousse moins un angle différenciant précis, une thèse forte, un parti éditorial ou une progression argumentative singulière

En pratique:
- on force un bon "squelette expert"
- on ne force pas assez un article mémorable ou tranché

### 4. Génération
- fichier: `src/Services/Generation/SeoGenerationService.php`

Historique important:
- avant: un seul appel AI, payload complet ou fallback
- maintenant:
  - `core` AI
  - puis `faq` AI
  - si partiel: fusion avec fallback
  - source possible: `ai`, `hybrid`, `fallback`

Constat majeur:
- le moteur actuel est beaucoup plus permissif vis-à-vis des payloads partiels
- un contenu partiel AI est conservé puis comblé automatiquement par le fallback
- cela stabilise la production
- mais cela augmente fortement la probabilité d'un rendu "correct mais générique"

Autrement dit:
- avant, un payload partiel tombait plus facilement
- maintenant, il survit en `hybrid`
- donc on publie plus de contenus "sauvés par le fallback"

### 5. Fallback content profile
- fichier: `seo-engine-app/app/SeoPresets/Amiantix/AmiantixContentProfile.php`

Constat majeur:
- c'est aujourd'hui la source la plus probable de la sensation générique

Pourquoi:
- le fallback ne génère pas juste une base courte
- il construit déjà un article quasi complet
- il contient des paragraphes très écrits, réutilisables, solides, mais fortement templatisés
- `ensureContentDepth()` complète encore ce contenu jusqu'à environ `1450+` mots
- si besoin, il ajoute des `Zoom terrain N`

Conséquence:
- même quand l'AI ne fait qu'une partie du travail, le fallback finit la page avec du texte propre, long, crédible
- mais ce texte reste mécaniquement proche d'un article à l'autre

## Différence principale avec l'ancien moteur

### Ancien moteur
- prompt unique
- payload complet attendu
- fallback plus direct
- moins de couches narratives intermédiaires

### Moteur actuel
- split `core + faq`
- acceptation des payloads partiels
- fusion fallback / AI
- enrichissement narratif
- sélection de blocs
- bridges de transition
- padding profond pour atteindre la densité attendue

Conclusion:
- l'ancien moteur dépendait plus franchement de la réussite AI
- le moteur actuel dépend davantage d'une structure de secours très élaborée
- il est plus robuste
- mais il produit plus facilement des contenus propres, longs, cohérents, et pourtant trop semblables

## Pourquoi un article de 1800 mots peut rester faible

### 1. Le score valorise surtout la structure, pas la singularité

#### Qualité
- fichier: `src/Services/Quality/SeoQualityGateService.php`
- valorise:
  - `wordCount`
  - `faqCount`
  - `H2/H3`
  - présence d'un tableau
  - couverture des sections blueprint
  - présence de signaux métier

Ce qu'il ne mesure presque pas:
- originalité argumentative
- densité d'insights non répétitifs
- niveau de redondance inter-articles
- banalité des phrases de liaison
- force de l'angle éditorial

### 2. Le score SEO final reste structurel

#### Scoring
- fichier: `src/Services/Scoring/SeoScoringService.php`
- pénalise:
  - contenu trop court
  - FAQ trop courte
  - maillage faible
  - structure de headings
  - absence schema
  - titre/meta
  - image

Ce qu'il ne pénalise pas assez:
- texte générique mais long
- transitions toutes faites
- article "correct" sans tension éditoriale
- répétition d'une même rhétorique Amiantix d'un sujet à l'autre

### 3. Les warnings "generic phrases" sont faibles

#### Signaux métier
- fichier: `seo-engine-app/app/Presets/Signals/AmiantixContentSignalProvider.php`

Constat:
- les warnings couvrent seulement quelques expressions:
  - `service de qualité`
  - `solution innovante`
  - `accompagnement personnalisé`

Donc:
- un texte peut rester très générique sans jamais tomber sur ces warnings

### 4. Le fallback est trop bon pour bloquer

Le vrai paradoxe:
- le fallback protège le moteur contre les échecs OpenAI
- mais il masque aussi la faiblesse réelle de la génération AI

On finit avec:
- un article long
- bien balisé
- avec FAQ
- avec tableau
- avec lexique métier
- donc bien scoré

Mais:
- pas forcément assez vivant
- pas assez unique
- pas assez lié à la requête précise

## Hypothèse la plus probable sur la baisse qualitative

La régression qualitative ne vient pas d'un prompt devenu "mauvais" au sens strict.

Elle vient surtout de la combinaison suivante:

1. `partial_generation` acceptée
2. fusion `AI + fallback`
3. fallback Amiantix très dense et très réutilisable
4. enrichissement automatique qui complète la longueur
5. scoring qui récompense la complétude structurelle plus que la force éditoriale

Résultat:
- le moteur produit des pages plus sûres
- mais moins tranchées
- et la baisse de personnalité éditoriale passe sous le radar des scores

## Verdict par composant

### Prompts actuels
- plutôt bons pour imposer une forme expert
- insuffisants pour forcer une vraie différenciation éditoriale

### Content profile
- trop dominant
- trop textuel
- trop proche d'un auteur de secours complet
- doit redevenir un garde-fou, pas un quasi-rédacteur principal

### Blueprint provider
- riche
- pas le vrai problème principal
- il fournit assez de matière, mais cette matière est ensuite rendue trop uniforme

### Enrichment pipeline
- utile pour la couverture
- nocif pour la singularité quand il complète mécaniquement

### FAQ builder
- correct structurellement
- mais secondaire dans le problème de fond
- la FAQ ne sauve pas un corps trop lisse

### Scoring
- trop indulgent avec les contenus bien structurés mais éditorialement plats

## Ce que je recommande de corriger en premier

### Priorité 1
Réduire le pouvoir du fallback.

À faire:
- ne plus laisser le fallback écrire tout le corps "quasi publiable"
- le limiter à:
  - structure
  - sections minimales
  - aides rédactionnelles courtes
- si AI est partiel, mieux vaut marquer `needs_review` que remplir toute la page avec du texte template

### Priorité 2
Rendre visible la source réelle du rendu.

À stocker et afficher côté admin:
- `generation_source`
  - `ai`
  - `hybrid`
  - `fallback`
- `% de contenu fallback`
- `partial_generation` oui/non

Sans ça:
- on croit juger l'AI
- alors qu'on juge parfois surtout le fallback

### Priorité 3
Ajouter une note de singularité éditoriale.

Exemples de signaux:
- répétition des mêmes paragraphes fallback entre articles
- répétition des mêmes bridges narratifs
- faible diversité de verbes d'action
- densité trop forte de phrases de liaison génériques
- trop faible ancrage à la requête exacte

### Priorité 4
Durcir l'acceptation hybride.

Aujourd'hui:
- partiel AI + fallback = contenu sauvable

Je recommande:
- si `content` AI est trop court ou trop peu spécifique:
  - ne pas publier en `review` comme si le texte était solide
  - marquer explicitement `draft_hybrid_low_confidence`

### Priorité 5
Faire du fallback un assistant, pas un ghostwriter complet.

Concrètement:
- garder:
  - titres de section
  - checklist
  - tableaux de structure
  - squelette FAQ
- réduire:
  - longs paragraphes rédigés
  - transitions toutes faites
  - zooms terrain quasi interchangeables

## Réponse courte à la question de fond

Pourquoi un article de 1800 mots obtient un rendu aussi générique ?

Parce que:
- la longueur vient en partie d'un enrichissement automatique
- la profondeur vient en partie d'un fallback très écrit
- les scores récompensent surtout la structure et la couverture
- et le moteur accepte aujourd'hui trop facilement des sorties `hybrid` qui paraissent solides sans être vraiment distinctives

## Ordre recommandé pour le prochain sprint

1. audit et journalisation de `generation_source` sur les pages existantes
2. réduction du fallback rédactionnel
3. durcissement du mode `hybrid`
4. ajout d'un score de singularité éditoriale
5. recalibrage du quality gate avec pénalité anti-générique

## Fichiers clés à reprendre ensuite
- `src/Services/Generation/SeoGenerationService.php`
- `seo-engine-app/app/SeoPresets/Amiantix/AmiantixContentProfile.php`
- `seo-engine-app/app/SeoPresets/Amiantix/AmiantixPromptProfile.php`
- `src/Services/Quality/SeoQualityGateService.php`
- `src/Services/Scoring/SeoScoringService.php`
