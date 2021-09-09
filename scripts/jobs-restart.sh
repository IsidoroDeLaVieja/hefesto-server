#!/bin/bash

set -e
docker exec -it hefesto_php-worker_1 php /var/www/artisan queue:restart

echo "DONE"