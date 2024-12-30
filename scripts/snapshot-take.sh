#!/bin/bash

set -e
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

SOURCE=$SCRIPT_DIR/..
TARGET_SNAPSHOTS=$SCRIPT_DIR/../snapshots/

NOW=$(date +%G%m%d%H%M%S)
NAME_SNAPSHOT=hefesto_$NOW
TARGET_CURRENT=$TARGET_SNAPSHOTS$NAME_SNAPSHOT

mkdir $TARGET_CURRENT

cp -R $SOURCE/code-engine/app/Apis $TARGET_CURRENT
cp -R $SOURCE/code-engine/storage/app $TARGET_CURRENT
docker exec hefesto-postgres-1 pg_dumpall -c -U postgres | gzip > "$TARGET_CURRENT"/postgres_bck.gz

cd $TARGET_SNAPSHOTS
tar -zcvf $NAME_SNAPSHOT.tar.gz $NAME_SNAPSHOT/
rm -R $NAME_SNAPSHOT

echo 'DONE'