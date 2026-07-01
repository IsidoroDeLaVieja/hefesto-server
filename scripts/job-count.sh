#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

VIRTUAL_HOST=$1

param_or_die "Usage: job-count.sh <virtualhost>" "$VIRTUAL_HOST"

RESULT=$(compose_exec php-fpm php /var/www/artisan count:jobs "$VIRTUAL_HOST" 2>&1)
EXIT_CODE=$?

if [ "$EXIT_CODE" -ne 0 ]; then
    echo "$RESULT" >&2
    exit 1
fi

echo "$RESULT" | python3 -m json.tool 2>/dev/null || echo "$RESULT"