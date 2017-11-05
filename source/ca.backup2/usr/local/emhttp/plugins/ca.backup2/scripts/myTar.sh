#!/bin/bash
SOURCE=$1
EXCLUDED=$2
DESTINATION=$3

cd $DESTINATION
COMMAND="/usr/bin/tar -cvaf $DESTINATION $EXCLUDED * >> /var/lib/docker/unraid/ca.backup2.datastore/appdata_backup.log"
logger "$COMMAND"
eval $COMMAND
