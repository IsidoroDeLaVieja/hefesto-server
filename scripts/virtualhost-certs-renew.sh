#!/bin/bash

set -e
docker exec hefesto-nginx-1 certbot renew

echo 'DONE'