# PraeviSEO Installer Flow

PraeviSEO now ships a transparent installer layer above the official bridge packages:

- `praeviseo-install.sh`
- `praeviseo-install.ps1`

These scripts are intentionally readable and minimal.

## UX goal

The client or integrator downloads one official file, runs it, enters:

- the PraeviSEO connection code
- the public site URL if `APP_URL` is missing

Then the script:

1. detects PHP
2. detects Composer
3. detects Laravel or Symfony from `composer.json`
4. installs or updates the correct Packagist bridge
5. prepares config
6. runs `praeviseo:connect`
7. verifies the bridge command is available

## Framework detection

- Laravel: `laravel/framework`
- Symfony: `symfony/framework-bundle`

## Output expected

- `PHP détecté`
- `Symfony détecté` or `Laravel détecté`
- `Bridge ... installé`
- `Connexion PraeviSEO active`
- `Monitoring actif`

## Honest boundaries

The installer improves the install UX.

It does not remove the need for:

- server access
- Composer
- a valid PHP project

So it is designed for:

- agencies
- freelancers
- internal technical teams

while WordPress remains the best self-serve path for non-technical clients.
