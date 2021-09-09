#!/bin/bash

set -e
docker exec hefesto_redis_1 redis-cli FLUSHALL

echo 'DONE'