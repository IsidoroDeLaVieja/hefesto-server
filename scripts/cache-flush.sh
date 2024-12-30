#!/bin/bash

set -e
docker exec hefesto-nginx-1 rm -R /etc/nginx/cache

echo 'DONE'