#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

NETWORK="hefesto_backend"
CONTAINER=$1

param_or_die "Usage: network-container-add.sh <container-name>" "$CONTAINER"

# ── Check if container exists (stopped or running) ───────────────────────────

if ! docker ps -a --format '{{.Names}}' | grep -q "^$CONTAINER$" 2>/dev/null; then
    echo "ERROR: Container '$CONTAINER' does not exist." >&2
    exit 1
fi

# ── Check if network exists ──────────────────────────────────────────────────

if ! docker network inspect "$NETWORK" > /dev/null 2>&1; then
    echo "ERROR: Network '$NETWORK' does not exist." >&2
    exit 1
fi

# ── Check if already connected ───────────────────────────────────────────────

if docker inspect "$CONTAINER" --format '{{range $net,$v := .NetworkSettings.Networks}}{{$net}}{{"\n"}}{{end}}' 2>/dev/null | grep -q "^$NETWORK$"; then
    echo "INFO: Container '$CONTAINER' is already connected to '$NETWORK'. Nothing to do."
    exit 0
fi

# ── Connect to network ───────────────────────────────────────────────────────

if ! docker network connect "$NETWORK" "$CONTAINER" > /dev/null 2>&1; then
    echo "ERROR: Failed to connect '$CONTAINER' to network '$NETWORK'." >&2
    exit 1
fi

echo "✓ '$CONTAINER' connected to '$NETWORK'"
echo "✅ All done"