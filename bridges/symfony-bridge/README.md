# PraeviSEO Symfony Bridge

Official lightweight bridge to connect a Symfony site to PraeviSEO without copying controllers, routes or payload logic by hand.

## Client flow

```bash
composer require praeviseo/symfony-bridge
php bin/console praeviseo:connect PRV-8X92-LKQ1
```

Then the bridge automatically:

- contacts PraeviSEO
- registers the site
- saves the shared secret
- enables remote publication
- exposes the publish endpoint
- exposes the public page route

The client should then see in PraeviSEO:

- Site connecté ✅
- Symfony détecté ✅
- Publication active ✅
- Monitoring actif ✅

## Environment

The connect command writes these values into `.env.local`:

```env
PRAEVISEO_URL=https://app.praeviseo.com
PRAEVISEO_BRIDGE_SECRET=...
PRAEVISEO_BRIDGE_SITE_ID=...
PRAEVISEO_BRIDGE_PREFIX=ressources
```

## Local development install

Until the package is split/published, a Symfony app can use it via a path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../ofyre-seo-engine-main/bridges/symfony-bridge"
    }
  ]
}
```

Then:

```bash
composer require praeviseo/symfony-bridge:*
```

This follows Symfony's documented local path bundle workflow.

## Honest boundaries

The bridge publishes and reports.

It does not pretend to fix:

- DNS
- infra
- hosting
- broken redirects
- server robots rules
- unrelated CMS/framework bugs
