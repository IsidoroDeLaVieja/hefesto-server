#!/bin/bash

set -e
docker exec -it hefesto-php-worker-1 php /var/www/artisan config:cache

echo 'DONE'