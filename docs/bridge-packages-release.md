# Bridge Packages Release

This document describes how PraeviSEO bridge packages become normal Composer packages for clients.

## Goal

Client sites must install bridges like any standard package:

### Laravel

```bash
composer require praeviseo/laravel-bridge
php artisan praeviseo:connect PRV-8X92-LKQ1
php artisan migrate
```

### Symfony

```bash
composer require praeviseo/symfony-bridge
php bin/console praeviseo:connect PRV-8X92-LKQ1
```

No path repositories.
No copied files.
No manual webhook wiring.

## Why split repositories are required

Packagist expects one package per repository root.

The main PraeviSEO engine repository is a monorepo, so each bridge must be mirrored into its own repository:

- `praeviseo/laravel-bridge`
- `praeviseo/symfony-bridge`

## What is already prepared

The monorepo now contains:

- `bridges/laravel-bridge`
- `bridges/symfony-bridge`
- CI validation workflow
- subtree split workflow template

## Required GitHub repositories

Create two repositories:

1. `praeviseo/laravel-bridge`
2. `praeviseo/symfony-bridge`

They should be the public package roots later consumed by Packagist.

## Required GitHub secrets

Add these secrets to the monorepo GitHub repository:

- `BRIDGE_SPLIT_TOKEN`
  A GitHub token allowed to push into the bridge repositories.
- `BRIDGE_LARAVEL_REPOSITORY`
  Example: `praeviseo/laravel-bridge`
- `BRIDGE_SYMFONY_REPOSITORY`
  Example: `praeviseo/symfony-bridge`

## Automatic split flow

The workflow `.github/workflows/bridge-package-split.yml`:

- watches `bridges/**`
- runs `git subtree split`
- force-pushes each bridge subtree to its dedicated repository

This keeps the client package repositories clean and Packagist-ready.

## Packagist publication

Once the dedicated repositories exist:

1. log in to Packagist
2. submit the GitHub URL of each bridge repository
3. confirm the package import
4. configure GitHub hook/webhook if needed

Then normal client installs become available:

```bash
composer require praeviseo/laravel-bridge
composer require praeviseo/symfony-bridge
```

## Recommended next step

After split repositories are live:

1. tag `v0.1.0` on each bridge repository
2. import both into Packagist
3. test install in one real Laravel site
4. test install in one real Symfony site
5. only then promote the bridges as official client onboarding flow
