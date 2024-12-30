#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
source $SCRIPT_DIR/functions.sh

VIRTUAL_HOST=$1
SOURCE=$SCRIPT_DIR/..

param_or_die "The format is virtualhost-delete.sh virtualhost" $VIRTUAL_HOST

docker exec --user www-data hefesto-php-fpm-1 php /var/www/artisan delete:virtualhost $VIRTUAL_HOST
docker exec hefesto-nginx-1 certbot delete --non-interactive --cert-name $VIRTUAL_HOST

echo $VIRTUAL_HOST' DELETED'