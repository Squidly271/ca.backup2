#!/usr/bin/php
<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2017, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/ca.backup/include/paths.php");
require_once("/usr/local/emhttp/plugins/ca.backup/include/helpers.php");

if ( is_file($communityPaths['backupProgress']) || is_file($communityPaths['restoreProgress']) ) {
  $backupLine = exec("tail -n1 ".$communityPaths['backupLog']);
  
  $backupArray = explode(" ",$backupLine);
  $rsyncPID = str_replace("[","",$backupArray[2]);
  $rsyncPID = str_replace("]","",$rsyncPID);
  
  if ( is_dir("/proc/$rsyncPID") ) {
    logger("Community Applications rsync process running.  Killing");
    posix_kill($rsyncPID,SIGINT);
  }  
}
?>