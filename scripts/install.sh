#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

CODE_DIR="$PROJECT_ROOT/code-engine"
LARADOCK_DIR="$PROJECT_ROOT/laradock"
DATA_PATH_HOST="$LARADOCK_DIR/data"

# ── Generate .env files ──────────────────────────────────────────────────────

cp "$CODE_DIR/.env.example" "$CODE_DIR/.env"

# Use envsubst to replace placeholders in laradock .env (more robust than sed)
export DATA_PATH_HOST UID_VAR="$UID"
sed "
  s/#DATA_PATH_HOST#/$DATA_PATH_HOST/g
  s/#UID#/$UID/g
" "$LARADOCK_DIR/env-example" > "$LARADOCK_DIR/.env"

# ── Start services ───────────────────────────────────────────────────────────

compose_run up -d php-fpm nginx redis php-worker postgres

# ── Wait for postgres to be healthy ──────────────────────────────────────────

echo "Waiting for postgres to be ready..."
if ! compose_run exec -T postgres pg_isready -U postgres --timeout=30 2>/dev/null; then
    echo "ERROR: postgres did not become ready in time." >&2
    exit 1
fi
echo "✓ postgres is ready"

# ── Install Composer (if not already installed) ──────────────────────────────

if ! compose_exec php-fpm test -f /var/www/composer.phar 2>/dev/null; then
    echo "Installing Composer..."
    compose_run cp "$LARADOCK_DIR/php-fpm/composer-install.sh" php-fpm:/var/www/composer-install.sh
    compose_exec php-fpm bash -c "chmod +x /var/www/composer-install.sh && /var/www/composer-install.sh && rm /var/www/composer-install.sh && chmod +x /var/www/composer.phar"
    echo "✓ Composer installed"
else
    echo "✓ Composer already installed"
fi

# ── Install project dependencies ─────────────────────────────────────────────

compose_exec php-fpm php /var/www/composer.phar install

# ── Configure application ────────────────────────────────────────────────────

cd "$CODE_DIR"
mkdir -p storage/app/host

compose_exec php-fpm php /var/www/artisan set:virtualhost:admin localhost
config_cache

echo "✅ All done"