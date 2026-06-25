#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

if ! compose_exec redis redis-cli FLUSHALL > /dev/null 2>&1; then
    echo "ERROR: Failed to flush Redis." >&2
    exit 1
fi

echo "✓ Redis flushed"
echo "✅ All done"