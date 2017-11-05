<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2017, Andrew Zawadzki #
#                                                             #
###############################################################

##################################################################################################################################################################################################
#                                                                                                                                                                                                #
# Static Variables.  Note that most paths are stored within /var/lib/docker/unraid, which means that any files are actually stored within the docker.img file and are persistent between reboots #
#                                                                                                                                                                                                #
##################################################################################################################################################################################################

$plugin = "ca.backup";

$communityPaths['tempFiles']                     = "/tmp/ca.backup/tempFiles";                            /* path to temporary files */
$communityPaths['persistentDataStore']           = "/var/lib/docker/unraid/ca.backup.datastore";          /* anything in this folder is NOT deleted upon an update of templates */

$communityPaths['unRaidVersion']                 = "/etc/unraid-version";
$communityPaths['backupOptions']                 = "/boot/config/plugins/ca.backup/BackupOptions.json";
$communityPaths['backupProgress']                = $communityPaths['tempFiles']."/backupInProgress";
$communityPaths['restoreProgress']               = $communityPaths['tempFiles']."/restoreInProgress";
$communityPaths['deleteProgress']                = $communityPaths['tempFiles']."/deleteInProgress";
$communityPaths['backupLog']                     = $communityPaths['persistentDataStore']."/appdata_backup.log";
$communityPaths['defaultShareConfig']            = "/usr/local/emhttp/plugins/ca.backup/scripts/defaultShare.cfg";
$communityPaths['backupScript']                  = "/usr/local/emhttp/plugins/ca.backup/scripts/backup.php";
$communityPaths['addCronScript']                 = "/usr/local/emhttp/plugins/ca.backup/scripts/addCron.php";
$communityPaths['unRaidDockerSettings']          = "/boot/config/docker.cfg";
$communityPaths['unRaidDisks']                   = "/var/local/emhttp/disks.ini";
$communityPaths['deleteScriptPath']              = $communityPaths['tempFiles']."/deleteScript";

?>
