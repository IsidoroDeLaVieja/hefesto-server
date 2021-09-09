#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
source $SCRIPT_DIR/functions.sh

VIRTUAL_HOST=$1
GENERATE_CERT=$2
SOURCE=$SCRIPT_DIR/..

param_or_die "The format is virtualhost-admin-set.sh virtualhost generatecert" $VIRTUAL_HOST 

docker exec --user www-data hefesto_php-fpm_1 php /var/www/artisan set:virtualhost:admin $VIRTUAL_HOST
generate_nginx_virtualhost $SOURCE $VIRTUAL_HOST $GENERATE_CERT

echo 'ADMIN '$VIRTUAL_HOST' SAVED'