# PraeviSEO Laravel Bridge

Official lightweight bridge to connect a Laravel site to PraeviSEO without copying controllers or wiring webhook internals by hand.

## Client flow

```bash
composer require praeviseo/laravel-bridge
php artisan praeviseo:connect PRV-8X92-LKQ1
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
- Laravel détecté ✅
- Publication active ✅
- Monitoring actif ✅

## Environment

The connect command writes these values into `.env`:

```env
PRAEVISEO_URL=https://app.praeviseo.com
PRAEVISEO_BRIDGE_SECRET=...
PRAEVISEO_BRIDGE_SITE_ID=...
PRAEVISEO_BRIDGE_PREFIX=ressources
```

## Production goal

The expected client install flow is:

```bash
composer require praeviseo/laravel-bridge
php artisan praeviseo:connect PRV-8X92-LKQ1
```

No copied files.
No second migration command for the client in the normal flow.
No custom Composer path repository in the client project.

## Honest boundaries

The bridge publishes and reports.

It does not pretend to fix:

- DNS
- infra
- hosting
- broken redirects
- server robots rules
- unrelated CMS/framework bugs
