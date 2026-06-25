#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

GENERATE_CERT=$1
NGINX_SITES_DIR="$PROJECT_ROOT/laradock/nginx/sites"

# ── Read current virtual hosts from artisan ──────────────────────────────────

HOSTS=$(compose_exec php-fpm php /var/www/artisan read:virtualhost)

# ── Backup and clean nginx sites ─────────────────────────────────────────────

mv "$NGINX_SITES_DIR/capture-trash.conf" "$NGINX_SITES_DIR/capture-trash.conf.bck" 2>/dev/null || true
mv "$NGINX_SITES_DIR/localhost.conf" "$NGINX_SITES_DIR/localhost.conf.bck" 2>/dev/null || true
rm -f "$NGINX_SITES_DIR"/*.conf
mv "$NGINX_SITES_DIR/capture-trash.conf.bck" "$NGINX_SITES_DIR/capture-trash.conf" 2>/dev/null || true
mv "$NGINX_SITES_DIR/localhost.conf.bck" "$NGINX_SITES_DIR/localhost.conf" 2>/dev/null || true

# ── Regenerate nginx configs ─────────────────────────────────────────────────

for i in $HOSTS; do
    if [ "$i" != "localhost" ]; then
        generate_nginx_virtualhost "$PROJECT_ROOT" "$i" "$GENERATE_CERT"
    fi
done

echo "✓ Virtualhosts refreshed"
echo "✅ All done"