#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${1:-$(pwd)}"
CONNECTION_CODE="${2:-}"
PRAEVISEO_URL="${PRAEVISEO_URL:-https://app.praeviseo.com}"

say() { printf '%s\n' "$1"; }
ok() { printf '✓ %s\n' "$1"; }
warn() { printf '! %s\n' "$1"; }
fail() { printf '✗ %s\n' "$1" >&2; exit 1; }

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "$1 non détecté."
}

detect_framework() {
  if grep -q '"laravel/framework"' composer.json 2>/dev/null; then
    printf 'laravel'
    return
  fi

  if grep -q '"symfony/framework-bundle"' composer.json 2>/dev/null; then
    printf 'symfony'
    return
  fi

  printf 'unknown'
}

read_env_value() {
  local key="$1"
  local file
  for file in .env.local .env; do
    if [[ -f "$file" ]]; then
      local line
      line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"
      if [[ -n "$line" ]]; then
        printf '%s' "${line#*=}" | sed 's/^"//; s/"$//'
        return
      fi
    fi
  done
}

write_env_local() {
  local key="$1"
  local value="$2"
  touch .env.local
  if grep -qE "^${key}=" .env.local; then
    python3 - <<PY
from pathlib import Path
path = Path(".env.local")
key = "$key"
value = "$value".replace('"', '\\"')
lines = path.read_text(encoding="utf-8").splitlines()
out = []
done = False
for line in lines:
    if line.startswith(key + "="):
        out.append(f'{key}="{value}"')
        done = True
    else:
        out.append(line)
if not done:
    out.append(f'{key}="{value}"')
path.write_text("\n".join(out) + "\n", encoding="utf-8")
PY
  else
    printf '%s="%s"\n' "$key" "$value" >> .env.local
  fi
}

ensure_symfony_database_url() {
  mkdir -p var
  if grep -E '^DATABASE_URL=' .env >/dev/null 2>&1 && ! grep -E '^DATABASE_URL=.*(postgresql://app:!ChangeMe!|postgresql://127\.0\.0\.1)' .env >/dev/null 2>&1; then
    ok "DATABASE_URL détecté"
    return
  fi

  sed -i '/^DATABASE_URL=/d' .env
  printf '%s\n' 'DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"' >> .env
  ok "DATABASE_URL configuré"
}

install_symfony_doctrine() {
  if [ -d vendor/doctrine/orm ]; then
    ok "Doctrine ORM détecté"
    return
  fi

  say "Installation de Doctrine ORM…"
  composer require symfony/orm-pack
  ensure_symfony_database_url
}

install_symfony_twig() {
  if [ -d vendor/symfony/twig-bundle ]; then
    ok "Twig détecté"
    return
  fi

  say "Installation de Twig…"
  composer require symfony/twig-pack
}

install_symfony_bridge() {
  composer config --no-plugins allow-plugins.praeviseo/symfony-bridge true >/dev/null 2>&1 || true
  ensure_symfony_database_url
  install_symfony_doctrine
  install_symfony_twig

  if composer show praeviseo/symfony-bridge >/dev/null 2>&1; then
    say "Mise à jour du bridge Symfony…"
    composer update praeviseo/symfony-bridge
  else
    say "Installation du bridge Symfony…"
    composer require praeviseo/symfony-bridge
  fi

  composer dump-autoload --no-interaction
}

install_laravel_bridge() {
  if composer show praeviseo/laravel-bridge >/dev/null 2>&1; then
    say "Mise à jour du bridge Laravel…"
    composer update praeviseo/laravel-bridge
  else
    say "Installation du bridge Laravel…"
    composer require praeviseo/laravel-bridge
  fi
}

prompt_connection_code() {
  if [[ -n "$CONNECTION_CODE" ]]; then
    return
  fi
  read -r -p "Code de connexion PraeviSEO : " CONNECTION_CODE
  [[ -n "$CONNECTION_CODE" ]] || fail "Code de connexion requis."
}

ensure_app_url() {
  local current_url
  current_url="$(read_env_value APP_URL || true)"
  if [[ -n "$current_url" ]]; then
    ok "APP_URL détecté"
    return
  fi

  read -r -p "URL publique du site (ex: https://monsite.com) : " current_url
  [[ -n "$current_url" ]] || fail "APP_URL requis pour connecter le site."
  write_env_local APP_URL "$current_url"
  ok "APP_URL configuré"
}

connect_symfony() {
  php bin/console cache:clear
  php bin/console praeviseo:connect "$CONNECTION_CODE" --praeviseo-url="$PRAEVISEO_URL"
}

connect_laravel() {
  php artisan praeviseo:connect "$CONNECTION_CODE"
}

post_install_checks() {
  local framework="$1"
  if [[ "$framework" == "symfony" ]]; then
    php bin/console praeviseo:connect --help >/dev/null
  else
    php artisan praeviseo:connect --help >/dev/null
  fi
  ok "Bridge prêt"
}

main() {
  say "PraeviSEO installer"
  say "Projet cible: $PROJECT_DIR"

  [[ -d "$PROJECT_DIR" ]] || fail "Chemin projet introuvable."
  cd "$PROJECT_DIR"

  [[ -f composer.json ]] || fail "composer.json introuvable dans $PROJECT_DIR."
  require_cmd php
  ok "PHP détecté"
  require_cmd composer
  ok "Composer détecté"

  FRAMEWORK="$(detect_framework)"
  case "$FRAMEWORK" in
    symfony)
      ok "Symfony détecté"
      ;;
    laravel)
      ok "Laravel détecté"
      ;;
    *)
      fail "Framework non supporté. Laravel ou Symfony attendu."
      ;;
  esac

  prompt_connection_code
  ensure_app_url

  if [[ "$FRAMEWORK" == "symfony" ]]; then
    install_symfony_bridge
    ok "Bridge Symfony installé"
    connect_symfony
  else
    install_laravel_bridge
    ok "Bridge Laravel installé"
    connect_laravel
  fi

  post_install_checks "$FRAMEWORK"
  ok "Connexion PraeviSEO active"
  ok "Monitoring actif"
}

main "$@"
