#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

VIRTUAL_HOST=$1
ENV=$2
KEY=$3
GENERATE_CERT=$4
VIRTUAL_HOST_PATH=$5

param_or_die "Usage: virtualhost-public-set.sh <virtualhost> <env> <key> [generatecert] [path]" "$VIRTUAL_HOST"
param_or_die "Usage: virtualhost-public-set.sh <virtualhost> <env> <key> [generatecert] [path]" "$ENV"
param_or_die "Usage: virtualhost-public-set.sh <virtualhost> <env> <key> [generatecert] [path]" "$KEY"

compose_exec php-fpm php /var/www/artisan set:virtualhost:public "$VIRTUAL_HOST" "$ENV" "$KEY" "$VIRTUAL_HOST_PATH"
generate_nginx_virtualhost "$PROJECT_ROOT" "$VIRTUAL_HOST" "$GENERATE_CERT"

echo "✓ Public '$VIRTUAL_HOST' saved"
echo "✅ All done"