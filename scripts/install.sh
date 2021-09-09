#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

CODE_DIR=$SCRIPT_DIR/../code-engine
LARADOCK_DIR=$SCRIPT_DIR/../laradock

cp $CODE_DIR/.env.example $CODE_DIR/.env
cp $LARADOCK_DIR/env-example $LARADOCK_DIR/.env

cd $LARADOCK_DIR
docker-compose up -d nginx redis php-worker postgres

docker cp php-fpm/composer-install.sh hefesto_php-fpm_1:/var/www
docker exec -i hefesto_php-fpm_1 bash -c "chmod +x /var/www/composer-install.sh && /var/www/composer-install.sh && rm /var/www/composer-install.sh && chmod +x /var/www/composer.phar"
docker exec --user www-data -i hefesto_php-fpm_1 bash -c "/var/www/composer.phar install"

cd $CODE_DIR
mkdir storage/app/host

docker exec --user www-data hefesto_php-fpm_1 php /var/www/artisan set:virtualhost:admin localhost
$SCRIPT_DIR/config-cache.sh

echo "DONE"