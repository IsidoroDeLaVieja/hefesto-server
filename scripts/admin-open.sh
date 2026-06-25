#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_ROOT="$SCRIPT_DIR/.."
ENV_FILE="$PROJECT_ROOT/code-engine/.env"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

# ── Validations ──────────────────────────────────────────────────────────────

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: Environment file not found at $ENV_FILE" >&2
    exit 1
fi

# ── Check if already open ────────────────────────────────────────────────────

if grep -q "^ADMIN_CLOSED=false" "$ENV_FILE"; then
    echo "INFO: Admin is already open. Nothing to do."
    exit 0
fi

# ── Apply change ─────────────────────────────────────────────────────────────

if ! grep -q "^ADMIN_CLOSED=true" "$ENV_FILE"; then
    echo "ERROR: ADMIN_CLOSED=true not found in $ENV_FILE. Unexpected configuration." >&2
    exit 1
fi

sed -i "s/^ADMIN_CLOSED=true/ADMIN_CLOSED=false/g" "$ENV_FILE"
echo "✓ Admin opened"

# ── Rebuild config cache ─────────────────────────────────────────────────────

if ! config_cache > /dev/null 2>&1; then
    echo "ERROR: Failed to rebuild config cache." >&2
    exit 1
fi

echo "✓ Config cache rebuilt"
echo "✅ All done"
