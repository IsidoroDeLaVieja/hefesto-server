#!/bin/bash

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# shellcheck source=./functions.sh
source "$SCRIPT_DIR/functions.sh"

DO_BACKUP=false

# ── Parse flags ──────────────────────────────────────────────────────────────

for arg in "$@"; do
    case "$arg" in
        --backup) DO_BACKUP=true ;;
        *)
            echo "ERROR: Unknown argument '$arg'" >&2
            echo "Usage: update.sh [--backup]" >&2
            exit 1
            ;;
    esac
done

echo "INFO: Starting update process..."

# ── Optional snapshot ────────────────────────────────────────────────────────

if [ "$DO_BACKUP" = true ]; then
    echo "INFO: Taking snapshot before updating..."
    "$SCRIPT_DIR/snapshot-take.sh" --db --storage
    echo ""
fi

# ── Stop services ────────────────────────────────────────────────────────────

echo "INFO: Stopping services..."
"$SCRIPT_DIR/down.sh"
echo ""

# ── Clean container logs ─────────────────────────────────────────────────────

echo "Cleaning Docker container logs (removing stopped containers)..."
compose_run down --remove-orphans 2>/dev/null || true
echo "✓ Container logs cleaned"
echo ""

# ── Pull latest images ───────────────────────────────────────────────────────

echo "Pulling latest Docker images..."
compose_run pull
echo "✓ Images pulled"
echo ""

# ── Rebuild images from scratch ──────────────────────────────────────────────

echo "Rebuilding images (no cache)..."
compose_run build --no-cache
echo "✓ Images rebuilt"
echo ""

# ── Start services ───────────────────────────────────────────────────────────

echo "Starting services..."
compose_run up -d php-fpm nginx redis php-worker postgres
echo "✓ Services started"
echo ""

# ── Ensure Composer is installed (survives container rebuild) ────────────────

LARADOCK_DIR="$PROJECT_ROOT/laradock"

if ! compose_exec php-fpm test -f /var/www/composer.phar 2>/dev/null; then
    echo "Installing Composer..."
    compose_run cp "$LARADOCK_DIR/php-fpm/composer-install.sh" php-fpm:/var/www/composer-install.sh
    compose_exec php-fpm bash -c "chmod +x /var/www/composer-install.sh && /var/www/composer-install.sh && rm /var/www/composer-install.sh && chmod +x /var/www/composer.phar"
    echo "✓ Composer installed"
else
    echo "✓ Composer already installed"
fi
echo ""

# ── Wait for postgres ────────────────────────────────────────────────────────

echo "Waiting for postgres to be ready..."
if ! compose_run exec -T postgres pg_isready -U postgres --timeout=60 2>/dev/null; then
    echo "ERROR: postgres did not become ready in time." >&2
    exit 1
fi
echo "✓ postgres is ready"
echo ""

# ── Update Composer dependencies ─────────────────────────────────────────────

echo "Checking for outdated Composer packages..."
compose_exec php-fpm php /var/www/composer.phar outdated --direct 2>/dev/null || \
    echo "(no outdated packages detected or already up to date)"

echo ""
echo "Updating Composer dependencies..."
compose_exec php-fpm php /var/www/composer.phar update --no-interaction --prefer-dist
echo "✓ Composer dependencies updated"
echo ""

# ── Post-update tasks ────────────────────────────────────────────────────────

echo "Regenerating config cache..."
config_cache
echo "✓ Config cache regenerated"
echo ""

echo "Refreshing nginx virtual hosts..."
"$SCRIPT_DIR/virtualhost-refresh.sh"
echo ""

echo "Flushing Redis cache..."
"$SCRIPT_DIR/redis-flush.sh"
echo ""

# ── Report ───────────────────────────────────────────────────────────────────

echo "✓ Docker images updated"
echo "✓ Composer dependencies updated"
echo "✅ All done"
echo ""
echo "INFO: To verify the update, run: $SCRIPT_DIR/tests.sh"