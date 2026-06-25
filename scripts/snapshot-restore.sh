#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

SNAPSHOT_FILE=""
RESTORE_DB=false
RESTORE_STORAGE=false

# ── Parse arguments ──────────────────────────────────────────────────────────

while [ $# -gt 0 ]; do
    case "$1" in
        --db) RESTORE_DB=true; shift ;;
        --storage) RESTORE_STORAGE=true; shift ;;
        -*)
            echo "ERROR: Unknown argument '$1'" >&2
            echo "Usage: snapshot-restore.sh <snapshot-file> [--db] [--storage]" >&2
            exit 1
            ;;
        *)
            if [ -z "$SNAPSHOT_FILE" ]; then
                SNAPSHOT_FILE="$1"
            else
                echo "ERROR: Unexpected argument '$1'" >&2
                echo "Usage: snapshot-restore.sh <snapshot-file> [--db] [--storage]" >&2
                exit 1
            fi
            shift
            ;;
    esac
done

# ── Validate ─────────────────────────────────────────────────────────────────

if [ -z "$SNAPSHOT_FILE" ]; then
    echo "ERROR: Snapshot file is required." >&2
    echo "Usage: snapshot-restore.sh <snapshot-file> [--db] [--storage]" >&2
    exit 1
fi

if [ "$RESTORE_DB" = false ] && [ "$RESTORE_STORAGE" = false ]; then
    echo "ERROR: At least one of --db or --storage is required." >&2
    echo "Usage: snapshot-restore.sh <snapshot-file> [--db] [--storage]" >&2
    exit 1
fi

TARGET_SNAPSHOTS="$PROJECT_ROOT/snapshots"
SNAPSHOT_PATH="$TARGET_SNAPSHOTS/$SNAPSHOT_FILE"

if [ ! -f "$SNAPSHOT_PATH" ]; then
    echo "ERROR: Snapshot file not found: $SNAPSHOT_PATH" >&2
    exit 1
fi

# ── Extract snapshot ─────────────────────────────────────────────────────────

cd "$TARGET_SNAPSHOTS"
RESTORE_DIR="${SNAPSHOT_FILE%.tar.gz}"
tar -xzf "$SNAPSHOT_FILE"

# ── Validate contents ───────────────────────────────────────────────────────

if [ "$RESTORE_DB" = true ] && [ ! -f "$RESTORE_DIR/postgres_bck.gz" ]; then
    echo "ERROR: Snapshot does not contain a database backup (postgres_bck.gz missing)." >&2
    rm -rf "$RESTORE_DIR"
    exit 1
fi

if [ "$RESTORE_STORAGE" = true ] && [ ! -d "$RESTORE_DIR/storage_bck" ]; then
    echo "ERROR: Snapshot does not contain a storage backup (storage_bck directory missing)." >&2
    rm -rf "$RESTORE_DIR"
    exit 1
fi

# ── Restore database ─────────────────────────────────────────────────────────

if [ "$RESTORE_DB" = true ]; then
    echo "Restoring database..."
    if ! gunzip < "$RESTORE_DIR/postgres_bck.gz" | compose_run exec -T postgres psql -U postgres > /dev/null 2>&1; then
        echo "ERROR: Database restore failed." >&2
        rm -rf "$RESTORE_DIR"
        exit 1
    fi
    echo "✓ Database restored"
fi

# ── Restore storage ──────────────────────────────────────────────────────────

if [ "$RESTORE_STORAGE" = true ]; then
    echo "Restoring storage..."
    rm -rf "$PROJECT_ROOT/code-engine/storage/app"
    cp -R "$RESTORE_DIR/storage_bck" "$PROJECT_ROOT/code-engine/storage/app"
    echo "✓ Storage restored"
fi

# ── Cleanup ──────────────────────────────────────────────────────────────────

cd "$TARGET_SNAPSHOTS"
rm -rf "$RESTORE_DIR"

# ── Post-restore steps ───────────────────────────────────────────────────────

echo ""
echo "Running post-restore tasks..."

"$SCRIPT_DIR/redis-flush.sh"
"$SCRIPT_DIR/jobs-restart.sh"
"$SCRIPT_DIR/cache-flush.sh"
"$SCRIPT_DIR/virtualhost-refresh.sh"

echo ""
echo "✓ Snapshot restored: $SNAPSHOT_FILE"
echo "✅ All done"