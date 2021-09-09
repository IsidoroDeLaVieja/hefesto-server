#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
source $SCRIPT_DIR/functions.sh

CONTAINER=$1

param_or_die "The format is network-container-add.sh container" $CONTAINER 

docker network connect hefesto_backend $CONTAINER

echo $CONTAINER' IS CONNECTED'