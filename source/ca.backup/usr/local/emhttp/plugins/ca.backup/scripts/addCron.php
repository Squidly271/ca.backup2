#!/usr/bin/php
<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2017, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/ca.backup/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ca.backup/include/paths.php");

$backupOptions = readJsonFile($communityPaths['backupOptions']);
if ( ! $backupOptions ) { exit; } 
if ($backupOptions['cronSetting'] != "disabled") {
  switch ($backupOptions['cronSetting']) {
    case 'custom':
      $cronSettings = $backupOptions['cronCustom'];
      break;
    case 'daily':
      $cronSettings = $backupOptions['cronMinute']." ".$backupOptions['cronHour']." * * *";
      break;
    case 'weekly':
      $cronSettings = $backupOptions['cronMinute']." ".$backupOptions['cronHour']." * * ".$backupOptions['cronDay'];
      break;
    case 'monthly':
      $cronSettings = $backupOptions['cronMinute']." ".$backupOptions['cronHour']." ".$backupOptions['cronMonth']." * *";
      break;
  }
  $cronSettings .= " ".$communityPaths['backupScript']." &>/dev/null 2>&1";
} else {
  $cronSettings = "";
}
  
exec("crontab -l",$oldCronSettings);
  
foreach ($oldCronSettings as $oldCron) {
  if ( ! strpos($oldCron,$communityPaths['backupScript']) ) {
    $newCronSettings[] = $oldCron;
  }
}
$newCronSettings[] = $cronSettings."\n";
$cronFile = randomFile();
file_put_contents($cronFile,implode("\n",$newCronSettings));
exec("crontab $cronFile");
unlink($cronFile);
  

?>
