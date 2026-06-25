#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

VIRTUAL_HOST=$1
GENERATE_CERT=$2

param_or_die "Usage: virtualhost-admin-set.sh <virtualhost> [generatecert]" "$VIRTUAL_HOST"

compose_exec php-fpm php /var/www/artisan set:virtualhost:admin "$VIRTUAL_HOST"
generate_nginx_virtualhost "$PROJECT_ROOT" "$VIRTUAL_HOST" "$GENERATE_CERT"

echo "✓ Admin '$VIRTUAL_HOST' saved"
echo "✅ All done"