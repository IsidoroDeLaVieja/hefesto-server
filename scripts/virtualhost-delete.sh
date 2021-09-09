#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
source $SCRIPT_DIR/functions.sh

VIRTUAL_HOST=$1
SOURCE=$SCRIPT_DIR/..

param_or_die "The format is virtualhost-delete.sh virtualhost" $VIRTUAL_HOST

docker exec --user www-data hefesto_php-fpm_1 php /var/www/artisan delete:virtualhost $VIRTUAL_HOST

echo $VIRTUAL_HOST' DELETED'