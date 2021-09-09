#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

SOURCE=$SCRIPT_DIR/..

sed -i "s/ADMIN_CLOSED=false/ADMIN_CLOSED=true/g" $SOURCE/code-engine/.env
$SCRIPT_DIR/config-cache.sh

echo "DONE"