#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

BACKUP_DB=false
BACKUP_STORAGE=false

# ── Parse flags ──────────────────────────────────────────────────────────────

for arg in "$@"; do
    case "$arg" in
        --db) BACKUP_DB=true ;;
        --storage) BACKUP_STORAGE=true ;;
        *)
            echo "ERROR: Unknown argument '$arg'" >&2
            echo "Usage: snapshot-take.sh [--db] [--storage]" >&2
            exit 1
            ;;
    esac
done

if [ "$BACKUP_DB" = false ] && [ "$BACKUP_STORAGE" = false ]; then
    echo "ERROR: At least one of --db or --storage is required." >&2
    echo "Usage: snapshot-take.sh [--db] [--storage]" >&2
    exit 1
fi

# ── Setup ────────────────────────────────────────────────────────────────────

TARGET_SNAPSHOTS="$PROJECT_ROOT/snapshots"
NOW=$(date +%Y%m%d%H%M%S)
NAME_SNAPSHOT="hefesto_$NOW"
TARGET_CURRENT="$TARGET_SNAPSHOTS/$NAME_SNAPSHOT"

mkdir -p "$TARGET_CURRENT"

# ── Backup postgres ──────────────────────────────────────────────────────────

if [ "$BACKUP_DB" = true ]; then
    echo "Backing up database..."
    if ! compose_run exec -T postgres pg_dumpall -c -U postgres | gzip > "$TARGET_CURRENT"/postgres_bck.gz 2>/dev/null; then
        echo "ERROR: Database backup failed." >&2
        rm -rf "$TARGET_CURRENT"
        exit 1
    fi
    echo "✓ Database backed up"
fi

# ── Backup storage ───────────────────────────────────────────────────────────

if [ "$BACKUP_STORAGE" = true ]; then
    if [ -d "$PROJECT_ROOT/code-engine/storage/app" ]; then
        echo "Backing up storage..."
        cp -R "$PROJECT_ROOT/code-engine/storage/app" "$TARGET_CURRENT"/storage_bck
        echo "✓ Storage backed up"
    else
        echo "WARNING: storage/app directory not found, skipping storage backup." >&2
    fi
fi

# ── Compress snapshot ────────────────────────────────────────────────────────

cd "$TARGET_SNAPSHOTS"
tar -zcf "$NAME_SNAPSHOT.tar.gz" "$NAME_SNAPSHOT/"
rm -rf "$NAME_SNAPSHOT"

# ── Report ───────────────────────────────────────────────────────────────────

parts=""
[ "$BACKUP_DB" = true ] && parts="${parts}db"
[ "$BACKUP_STORAGE" = true ] && parts="${parts:+$parts+}storage"

echo ""
echo "✓ Snapshot created: $NAME_SNAPSHOT.tar.gz ($parts)"
echo "✅ All done"