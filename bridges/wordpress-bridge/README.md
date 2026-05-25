# PraeviSEO WordPress Bridge

Official WordPress plugin bridge for connecting a client site to PraeviSEO in a self-serve flow.

## Client flow

1. Install the `PraeviSEO Bridge` plugin.
2. Open `PraeviSEO` in the WordPress admin.
3. Paste the connection code from the cockpit.
4. Click `Connect site`.

Then the plugin automatically:

- contacts PraeviSEO
- saves the shared secret
- enables signed remote publication
- exposes the publish API route
- keeps the site linked for monitoring

## Honest boundaries

The plugin publishes and reports.

It does not pretend to fix:

- DNS
- infra
- hosting
- broken redirects
- server robots rules
- unrelated WordPress/plugin bugs
