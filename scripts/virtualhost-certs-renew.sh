#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

if ! compose_exec nginx certbot renew > /dev/null 2>&1; then
    echo "ERROR: Failed to renew certificates." >&2
    exit 1
fi

echo "✓ Certificates renewed"
echo "✅ All done"