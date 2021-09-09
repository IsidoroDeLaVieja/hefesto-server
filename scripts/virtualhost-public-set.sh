#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
source $SCRIPT_DIR/functions.sh

VIRTUAL_HOST=$1
ENV=$2
KEY=$3
GENERATE_CERT=$4
VIRTUAL_HOST_PATH=$5
SOURCE=$SCRIPT_DIR/..

param_or_die "The format is virtualhost-prod-set.sh virtualhost env key generatecert path" $VIRTUAL_HOST 
param_or_die "The format is virtualhost-prod-set.sh virtualhost env key generatecert path" $ENV 
param_or_die "The format is virtualhost-prod-set.sh virtualhost env key generatecert path" $KEY 

docker exec --user www-data hefesto_php-fpm_1 php /var/www/artisan set:virtualhost:public $VIRTUAL_HOST $ENV $KEY $VIRTUAL_HOST_PATH

generate_nginx_virtualhost $SOURCE $VIRTUAL_HOST $GENERATE_CERT

echo 'PUBLIC '$VIRTUAL_HOST' SAVED'