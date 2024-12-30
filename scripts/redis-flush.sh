#!/bin/bash

set -e
docker exec hefesto-redis-1 redis-cli FLUSHALL

echo 'DONE'