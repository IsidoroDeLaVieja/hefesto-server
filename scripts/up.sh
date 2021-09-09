#!/bin/bash

set -e
docker start hefesto_redis_1 hefesto_postgres_1 hefesto_php-worker_1 hefesto_php-fpm_1 hefesto_nginx_1

echo 'DONE'