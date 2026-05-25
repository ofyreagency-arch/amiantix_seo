# Symfony Bridge

Premier bridge officiel simple pour connecter un vrai site Symfony a Praeviseo sans exposer de webhook ni de payloads au client.

## Experience client voulue

1. Le client installe le bridge
2. Il lance :

```bash
php bin/console praeviseo:connect ABCD-EFGH-IJKL
```

3. Le bridge connecte automatiquement le site
4. Praeviseo affiche :
   - Site connecte ✅
   - Publication active ✅
   - Monitoring actif ✅

## Fichiers a copier

- `src/Command/PraeviseoConnectCommand.php`
- `src/Controller/PraeviseoBridgeController.php`
- `src/Controller/PraeviseoPublishedPageController.php`
- `src/Entity/PraeviseoPublishedPage.php`
- `src/Service/PraeviseoBridgeService.php`
- `templates/praeviseo/published_page.html.twig`

## Variables d environnement

```env
APP_URL=https://client.com
PRAEVISEO_BRIDGE_SECRET=change-me
PRAEVISEO_BRIDGE_SITE_ID=amiantix
PRAEVISEO_BRIDGE_PREFIX=ressources
```

## Ce que fait le bridge

- recoit une publication signee depuis Praeviseo
- met a jour une vraie page publique Symfony
- renvoie une reponse claire succes/echec
- laisse a Praeviseo le monitoring SEO, GSC et la reouverture intelligente

## Route publique

Une fois les fichiers copies, la vraie page publique sort sous :

```text
https://client.com/ressources/mon-slug
```

Le prefixe est pilote par :

```env
PRAEVISEO_BRIDGE_PREFIX=ressources
```

## Ce que le bridge ne pretend pas corriger

- DNS
- infra
- robots serveur
- redirects cassees
- bugs CMS hors publication

Ces cas restent des revues techniques humaines.
