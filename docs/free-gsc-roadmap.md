# PraeviSEO Free Roadmap

Date: 2026-05-26

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

## Vue Opportunites Free

Objectif:

- transformer les donnees GSC en actions compréhensibles pour un client lambda

Le dashboard actuel montre les chiffres.
La suite doit montrer:

- quoi faire
- sur quelles pages
- dans quel ordre

### Bloc 1. Opportunites prioritaires

Afficher une liste de cartes du type:

- pages proches du top 10
- pages avec CTR faible
- pages qui perdent des impressions
- pages indexees sans traction

### Bloc 2. Recommandation IA

Pour chaque opportunite:

- titre du probleme
- explication simple
- impact estime
- action suggeree

Exemples:

- "Votre page est proche du top 10"
- "Votre CTR est faible par rapport a sa position"
- "Cette page perd de la visibilite"

### Bloc 3. Priorisation

PraeviSEO doit indiquer:

- priorite haute
- gain potentiel
- facilite estimee

### Bloc 4. CTA premium discret

Une fois la valeur free prouvee:

- "Activez PraeviSEO sur votre site pour automatiser cette action"

Le CTA premium ne doit jamais ecraser la valeur gratuite.

## Ordre de travail recommande

1. Construire la vue `Optimisations` version free
2. Afficher les vraies opportunites GSC
3. Ajouter une recommandation IA simple par opportunite
4. Ensuite seulement enrichir `Publications`
5. Ensuite revenir sur l installateur premium

## Definition de succes

La partie free est reussie si un client comprend:

- ce que son SEO fait en ce moment
- quelles pages demandent une action
- quelles opportunites ont le plus de valeur
- pourquoi PraeviSEO est deja utile avant toute installation
