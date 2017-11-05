#!/bin/bash

echo "/usr/local/emhttp/plugins/ca.backup2/scripts/backup.php & > /dev/null " | at -M NOW >/dev/null 2>&1

