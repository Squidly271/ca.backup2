#!/bin/bash

echo "/usr/local/emhttp/plugins/ca.backup/scripts/deleteDatedBackupSets.php & > /dev/null " | at -M NOW >/dev/null 2>&1

