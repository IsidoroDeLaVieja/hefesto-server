#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

VIRTUAL_HOST=$1
JOB_ID=$2

param_or_die "Usage: job-get.sh <virtualhost> <id>" "$VIRTUAL_HOST"
param_or_die "Usage: job-get.sh <virtualhost> <id>" "$JOB_ID"

RESULT=$(compose_exec php-fpm php /var/www/artisan get:job "$VIRTUAL_HOST" "$JOB_ID" 2>&1)
EXIT_CODE=$?

if [ "$EXIT_CODE" -ne 0 ]; then
    echo "$RESULT" >&2
    exit 1
fi

echo "$RESULT" | python3 -m json.tool 2>/dev/null || echo "$RESULT"