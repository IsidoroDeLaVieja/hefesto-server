#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

CACHE_DIR="/etc/nginx/cache"

# ── Check if cache directory exists before flushing ──────────────────────────

if ! compose_exec nginx test -d "$CACHE_DIR" 2>/dev/null; then
    echo "Nothing to flush."
    exit 0
fi

# ── Flush cache ──────────────────────────────────────────────────────────────

if ! compose_exec nginx rm -Rf "$CACHE_DIR" 2>/dev/null; then
    echo "ERROR: Failed to remove nginx cache directory." >&2
    exit 1
fi

echo "✓ Nginx cache flushed"
echo "✅ All done"