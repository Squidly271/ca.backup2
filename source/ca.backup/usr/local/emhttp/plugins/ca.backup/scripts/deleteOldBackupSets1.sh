#!/bin/bash
echo "Deleting Old Backup Sets" > /tmp/ca.backup/tempFiles/deleteInProgress
mkdir -p /var/lib/docker/unraid/ca.backup.datastore/
rm -rfv "$1" >> /var/lib/docker/unraid/ca.backup.datastore/appdata_backup.log
rm /tmp/ca.backup/tempFiles/deleteInProgress
