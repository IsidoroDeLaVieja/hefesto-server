#!/bin/bash

set -e
docker exec hefesto_nginx_1 certbot renew

echo 'DONE'