#!/bin/bash

echo "/usr/local/emhttp/plugins/ca.backup/scripts/deleteIncompleteBackupSets1.sh '$1' & > /dev/null " | at -M NOW >/dev/null 2>&1

