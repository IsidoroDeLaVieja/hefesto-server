#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
source $SCRIPT_DIR/functions.sh

NAME_SNAPSHOT=$1
GENERATE_CERT=$2
SOURCE=$SCRIPT_DIR/..
TARGET_SNAPSHOTS=$SCRIPT_DIR/../snapshots/

param_or_die "The format is snapshot-restore.sh namesnapshot generatecert" $NAME_SNAPSHOT 

cd $TARGET_SNAPSHOTS
tar -xzf $NAME_SNAPSHOT.tar.gz
cd $NAME_SNAPSHOT

rm -R $SOURCE/code-engine/app/Apis
cp -R Apis $SOURCE/code-engine/app 

rm -R $SOURCE/code-engine/storage/app
cp -R app $SOURCE/code-engine/storage

$SCRIPT_DIR/redis-flush.sh
$SCRIPT_DIR/jobs-restart.sh
$SCRIPT_DIR/cache-flush.sh
$SCRIPT_DIR/virtualhost-refresh.sh $GENERATE_CERT

cat postgres_bck.gz | gunzip | docker exec -i hefesto-postgres-1 psql -U postgres

cd ..
rm -R $NAME_SNAPSHOT

echo 'DONE'
