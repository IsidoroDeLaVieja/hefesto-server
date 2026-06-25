#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

VIRTUAL_HOST=$1

param_or_die "Usage: virtualhost-delete.sh <virtualhost>" "$VIRTUAL_HOST"

compose_exec php-fpm php /var/www/artisan delete:virtualhost "$VIRTUAL_HOST"
compose_exec nginx certbot delete --non-interactive --cert-name "$VIRTUAL_HOST" 2>/dev/null || true

echo "✓ '$VIRTUAL_HOST' deleted"
echo "✅ All done"