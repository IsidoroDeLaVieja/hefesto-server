#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

if ! compose_exec php-worker php /var/www/artisan queue:restart > /dev/null 2>&1; then
    echo "ERROR: Failed to restart queue worker." >&2
    exit 1
fi

echo "✓ Queue worker restarted"
echo "✅ All done"