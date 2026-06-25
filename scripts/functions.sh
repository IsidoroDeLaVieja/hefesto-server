#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_ROOT="$SCRIPT_DIR/.."

param_or_die()
{
    if [ -z "$2" ]
    then
        echo "ERROR: $1" >&2
        exit 1
    fi
}

# ── Docker Compose helpers ───────────────────────────────────────────────────

COMPOSE_DIR="$PROJECT_ROOT/laradock"

compose_run()
{
    docker compose -f "$COMPOSE_DIR/docker-compose.yml" "$@"
}

compose_exec()
{
    local service=$1
    shift

    if ! compose_run ps --services 2>/dev/null | grep -q "^$service$"; then
        echo "ERROR: Service '$service' is not running." >&2
        exit 1
    fi

    compose_run exec -T "$service" "$@"
}

# ── Artisan helpers ──────────────────────────────────────────────────────────

config_cache()
{
    compose_exec php-fpm php /var/www/artisan config:cache
}

# ── Nginx helpers ────────────────────────────────────────────────────────────

generate_nginx_virtualhost()
{
    SOURCE=$PROJECT_ROOT
    VIRTUAL_HOST=$2
    GENERATE_CERT=$3

    cp "$SOURCE/laradock/nginx/sites/default.conf.example" "$SOURCE/laradock/nginx/sites/$VIRTUAL_HOST.conf"
    sed -i "s/#domain#/$VIRTUAL_HOST/g" "$SOURCE/laradock/nginx/sites/$VIRTUAL_HOST.conf"
    compose_exec nginx nginx -s reload

    if [ "$GENERATE_CERT" == "generatecert" ]; then
        compose_exec nginx certbot --nginx -d "$VIRTUAL_HOST"
    fi
}