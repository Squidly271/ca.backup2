#!/usr/bin/php
<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2019, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/ca.backup2/include/paths.php");
require_once("/usr/local/emhttp/plugins/ca.backup2/include/helpers.php");

if ( is_file($communityPaths['backupProgress']) ) {
	$parentPID = file_get_contents($communityPaths['backupProgress']);
}
if ( is_file($communityPaths['restoreProgress']) ) {
	$parentPID = file_get_contents($communityPaths['restoreProgress']);
}
if ( is_file($communityPaths['verifyProgress']) ) {
	$parentPID = file_get_contents($communityPaths['verifyProgress']);
}
if ( ! $parentPID ) {
	exit;
}
$childPID = exec("pgrep -P $parentPID");
if ( is_dir("/proc/$childPID") ) {
	logger("CA Backup / Restore tar process running.  Killing $childPID");
	posix_kill($childPID,SIGINT);
}  

?>