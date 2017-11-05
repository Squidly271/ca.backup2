#!/usr/bin/php
<?PHP
require_once("/usr/local/emhttp/plugins/ca.backup/include/paths.php");
require_once("/usr/local/emhttp/plugins/ca.backup/include/helpers.php");

exec("mkdir -p /var/lib/docker/unraid/ca.backup.datastore/");

$backupOptions = readJsonFile($communityPaths['backupOptions']);
if ( ! $backupOptions['destinationShare']) {
  exit;
}
if ( ! $backupOptions['deleteOldBackup'] || $backupOptions['deleteOldBackup'] == 0 ) {
  file_put_contents($communityPaths['backupLog'],"Days to delete after is set to 0.  Exiting\n",FILE_APPEND);
}
file_put_contents($communityPaths['deleteProgress'],"Deleting Dated Backup Sets");
file_put_contents($communityPaths['backupLog'],"\nChecking for backup sets older than ".$backupOptions['deleteOldBackup']." days\n",FILE_APPEND);
$basePathBackup = "/mnt/user/".$backupOptions['destinationShare'];
$currentDate = date_create(now);
$dirContents = dirContents($basePathBackup);
foreach ($dirContents as $dir) {
  $folderDate = date_create_from_format("Y-m-d@G.i",$dir);
  if ( ! $folderDate ) { continue; }
  $interval = date_diff($currentDate,$folderDate);
  $age = $interval->format("%R%a");
  if ( $age <= (0 - $backupOptions['deleteOldBackup']) ) {
    ++$flag;
    file_put_contents($communityPaths['backupLog'],"Deleting Dated Backup set: $basePathBackup/$dir\n",FILE_APPEND);
    $path = $basePathBackup."/".$dir;
    exec('rm -rfv '.escapeshellarg($path).' >> '.$communityPaths['backupLog']);
  }   
}
if ( ! $flag ) {
  file_put_contents($communityPaths['backupLog'],"Nothing to delete\n",FILE_APPEND);
} else {
  file_put_contents($communityPaths['backupLog'],"Deleted $flag backup sets\n",FILE_APPEND);
}
unlink($communityPaths['deleteProgress']);
?>