#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
source $SCRIPT_DIR/functions.sh

GENERATE_CERT=$1
SOURCE=$SCRIPT_DIR/..

HOSTS=$(docker exec --user www-data hefesto-php-fpm-1 php /var/www/artisan read:virtualhost)

mv $SOURCE/laradock/nginx/sites/capture-trash.conf $SOURCE/laradock/nginx/sites/capture-trash.conf.bck
mv $SOURCE/laradock/nginx/sites/localhost.conf $SOURCE/laradock/nginx/sites/localhost.conf.bck
rm $SOURCE/laradock/nginx/sites/*.conf
mv $SOURCE/laradock/nginx/sites/capture-trash.conf.bck $SOURCE/laradock/nginx/sites/capture-trash.conf
mv $SOURCE/laradock/nginx/sites/localhost.conf.bck $SOURCE/laradock/nginx/sites/localhost.conf

for i in $HOSTS ; do
    if [ "$i" != "localhost" ]; then
        generate_nginx_virtualhost $SOURCE $i $GENERATE_CERT
    fi
done;

echo 'DONE'