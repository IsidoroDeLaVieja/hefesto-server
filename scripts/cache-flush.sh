#!/bin/bash

set -e
docker exec hefesto_nginx_1 rm -R /etc/nginx/cache

echo 'DONE'