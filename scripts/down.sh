#!/bin/bash

set -e
docker stop hefesto-redis-1 hefesto-postgres-1 hefesto-php-worker-1 hefesto-php-fpm-1 hefesto-nginx-1

echo 'DONE'