# PraeviSEO Free Roadmap

Date: 2026-05-26

Phase en cours :

- stabilisation architecture complete
- verification de synchro frontend / backend / VPS / GSC
- decouplage final entre concepts moteur internes et objets UX client

## Positionnement

PraeviSEO doit d abord exister comme un vrai service SEO gratuit base sur Google Search Console.

- Free: intelligence SEO, analyse, priorisation, recommandations
- Paid: activation sur le site, automatisation, monitoring avance, publication

Le produit ne doit plus raconter:

- "sans installateur, PraeviSEO ne fait rien"

Le produit doit raconter:

- "PraeviSEO analyse deja votre SEO avec Google"
- "l activation sur le site debloque ensuite l automatisation"

## Ce qui est termine

### 1. Front client deploie sur le VPS

- Front Next.js deploie sur `https://seo.amiantix.com`
- Routage client / admin separe
- Supervisor et Nginx branches

### 2. Connexion GSC reelle

- propriete GSC reliee cote client
- lectures reelles des clics, impressions, CTR
- import 28 jours valide
- wording client clarifie sur la fenetre 28 jours

### 3. Indexation GSC reelle

- URL Inspection branchee dans l import
- pages indexees / non indexees disponibles en base
- compteur client aligne sur la canonique Google

### 4. UX client recentree sur la valeur GSC

- le dashboard n est plus presente comme vide sans installateur
- statuts clients simplifies:
  - `Analyse GSC active`
  - `Automatisation en preparation`
  - `Activer l automatisation`
- la fiche site explique que Google apporte deja une vraie valeur SEO

### 5. Vue Optimisations free branchee sur le moteur existant

- la page `Optimisations` remonte maintenant les vraies opportunites GSC du backend
- opportunites exposees:
  - CTR faible
  - proche du top 10
  - requete emergente
  - baisse durable
- les suggestions moteur historiques restent visibles en second niveau
- un test backend protege l endpoint client enrichi

## Priorite immediate

Avant d ajouter une nouvelle couche produit, stabiliser :

- frontend
- backend
- VPS
- build Next
- Nginx / Supervisor
- sync local / VPS
- mode free GSC-only sans dependance implicite a `seo_pages`

Checklist de reference :

- `docs/architecture-stabilization-checklist.md`

## Ce que le free doit offrir

Avec GSC seul, PraeviSEO doit deja fournir:

- impressions
- clics
- CTR
- positions
- pages indexees
- pages non indexees
- requetes SEO
- tendances
- pages qui montent
- pages qui chutent
- pages avec CTR faible
- pages proches du top 10
- opportunites SEO
- priorisation automatique
- recommandations IA
- alertes simples

## Ce que le payant debloque plus tard

- activation PraeviSEO sur le site
- monitoring avance
- automatisation
- publications
- execution directe sur le site

## Prochaine etape immediate

La prochaine brique produit a construire est:

## Vue Publications Free

Objectif:

- montrer ce que PraeviSEO peut recommander ou produire ensuite a partir des signaux GSC

Le dashboard montre maintenant les chiffres.
La page optimisations montre maintenant quoi faire.
La suite doit montrer:

- quelles pages ont deja une bonne traction
- quelles pages meritent d etre poussees ou enrichies
- quels contenus sont prets a devenir des actions editoriales

### Bloc 1. Pages qui meritent une action editoriale

Afficher des cartes du type:

- pages qui performent deja
- pages qui montent
- pages avec opportunite de refresh
- pages qui peuvent nourrir une future publication

### Bloc 2. Interpretation simple

Pour chaque page:

- pourquoi elle compte
- quel signal Google le justifie
- quelle action editoriale est recommandee

### Bloc 3. Lien avec le moteur

La vue doit relier:

- performances GSC
- opportunites
- suggestions
- futur travail editorial

### Bloc 4. CTA premium discret

Une fois la valeur free prouvee:

- "Activez PraeviSEO sur votre site pour automatiser cette action"

Le CTA premium ne doit jamais ecraser la valeur gratuite.

## Ordre de travail recommande

1. Dashboard free clair
2. Vue `Optimisations` free branchee
3. Enrichir `Publications`
4. Puis ajouter alertes / tendances
5. Ensuite revenir sur l installateur premium

## Definition de succes

La partie free est reussie si un client comprend:

- ce que son SEO fait en ce moment
- quelles pages demandent une action
- quelles opportunites ont le plus de valeur
- pourquoi PraeviSEO est deja utile avant toute installation
