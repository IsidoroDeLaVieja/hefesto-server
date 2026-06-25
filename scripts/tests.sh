#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

PHPUNIT_PATH="vendor/phpunit/phpunit/phpunit"

# ── Run tests ────────────────────────────────────────────────────────────────

echo "Running tests..."
echo ""

if ! compose_exec php-fpm php "$PHPUNIT_PATH"; then
    echo ""
    echo "ERROR: Tests failed." >&2
    exit 1
fi

echo ""
echo "✅ All tests passed"