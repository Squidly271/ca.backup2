#!/bin/bash

echo "/usr/local/emhttp/plugins/ca.backup2/scripts/backup.php  restore  $1 & > /dev/null " | at -M NOW >/dev/null 2>&1

