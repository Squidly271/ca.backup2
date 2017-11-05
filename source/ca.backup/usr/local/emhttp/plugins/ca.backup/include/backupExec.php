<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2017, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/ca.backup/include/paths.php");
require_once("/usr/local/emhttp/plugins/ca.backup/include/helpers.php");

function getDates() {
  global $communityPaths;
  
  $backupOptions = readJsonFile($communityPaths['backupOptions']);

  $availableDates = @array_diff(@scandir($backupOptions['destination']."/".$backupOptions['destinationShare']),array(".",".."));
  if ( ! is_array($availableDates) ) {
    return "No Backup Sets Found";
  }
  $output = '<select id="date">';
  foreach ($availableDates as $date) {
    $output .= '<option value="'.$date.'">'.$date.'</option>';
  }
  $output .= "</select>";
  return $output;
}

switch ($_POST['action']) {

##############################################################
#                                                            #
# Returns errors on settings for backup / restore of appData #
#                                                            #
##############################################################

case 'validateBackupOptions':
  $rawSettings = getPostArray('settings');
  foreach ($rawSettings as $setting) {
    $settings[$setting[0]] = $setting[1];
  }

  $destinationShare = str_replace("/mnt/user/","",$settings['destinationShare']);
  $destinationShare = rtrim($destinationShare,'/');

  if ( $settings['source'] == "" ) {
    $errors .= "Source Must Be Specified<br>";
  }
  if ( $settings['destination'] == "" || $destinationShare == "" ) {
    $errors .= "Destination Must Be Specified<br>";
  }
  
  if ( $settings['source'] != "" && $settings['source'] == $settings['destination'] ) {
    $errors .= "Source and Destination Cannot Be The Same<br>";
  } else {
    $destDir = ltrim($destinationShare,'/');
    $destDirPaths = explode('/',$destDir);
    if ( basename($settings['source']) == $destDirPaths[0] ) {
      $errors .= "Destination cannot be a subfolder from source<br>";
    }
  }
  
  if ( basename($settings['source']) == $destinationShare ) {
    $errors .= "Source and Destination Cannot Be The Same Share<br>";
  }
  
  if ( $settings['stopScript'] ) {
    if ( ! is_file($settings['stopScript']) ) {
      $errors .= "No Script at ".$settings['stopScript']."<br>";
    } else {
      if ( ! is_executable($settings['stopScript']) ) {
        $errors .= "Stop Script ".$settings['stopScript']." is not executable<br>";
      }
    }
  }
  if ( $settings['startScript'] ) {
    if ( ! is_file($settings['startScript']) ) {
      $errors .= "No Script at ".$settings['startScript']."<br>";
    } else {
        if ( ! is_executable($settings['startScript']) ) {
        $errors .= "Start Script ".$settings['startScript']." is not executable<br>";
      }
    }
  }
  
  if ( $settings['backupFlash'] == "separate" ) {
    if ( ! $settings['usbDestination'] ) {
      $errors .= "Destination for the USB Backup Must Be Specified<br>";
    } else {
      $origUSBDestination = $settings['usbDestination'];
      $availableDisks = my_parse_ini_file("/var/local/emhttp/disks.ini",true);
      $usbDestination = $settings['usbDestination'];
      foreach ($availableDisks as $disk) {
        $usbDestination = str_replace("/mnt/".$disk['name']."/","",$usbDestination);
      }
      $usbDestination = str_replace("/mnt/user0/","",$usbDestination);
      $usbDestination = str_replace("/mnt/user/","",$usbDestination);
      
      if ( $usbDestination == "" ) {
        $errors .= "USB Destination cannot be the root directory of /mnt/user or of a disk<br>";
      }
      if ( ! is_dir($origUSBDestination) ) {
        $errors .= "USB Destination Not A Valid Directory<br>";
      }
    }
  }
  if ( startsWith($usbDestination,$destinationShare) ) {
    $errors .= "USB Destination cannot be a sub-folder of Appdata destination<br>";
  }
   if ( $settings['backupXML'] == "separate" ) {
    if ( ! $settings['xmlDestination'] ) {
      $errors .= "Destination for the XML Backup Must Be Specified<br>";
    } else {
      $origXMLDestination = $settings['xmlDestination'];
      $availableDisks = my_parse_ini_file("/var/local/emhttp/disks.ini",true);
      $xmlDestination = $settings['xmlDestination'];
      foreach ($availableDisks as $disk) {
        $xmlDestination = str_replace("/mnt/".$disk['name']."/","",$xmlDestination);
      }
      $xmlDestination = str_replace("/mnt/user0/","",$xmlDestination);
      $xmlDestination = str_replace("/mnt/user/","",$xmlDestination);
      
      if ( $xmlDestination == "" ) {
        $errors .= "XML Destination cannot be the root directory of /mnt/user or of a disk<br>";
      }
      if ( ! is_dir($origXMLDestination) ) {
        $errors .= "XML Destination Not A Valid Directory<br>";
      }
    }
  }
  if ( startsWith($xmlDestination,$destinationShare) ) {
    $errors .= "XML Destination cannot be a sub-folder of Appdata destination<br>";
  } 
  if ( startsWith($xmlDestination,$usbDestination) || startsWith($usbDestination,$xmlDestination) ) {
    $errors .= "USB/XML Destinations are the same or cannot be sub-folders of each other";
  }
  if ( ! $errors ) {
    $errors = "NONE";
  }
  echo $errors;
  
  break;
  
######################################
#                                    #
# Applies the backup/restore options #
#                                    #
######################################

case 'applyBackupOptions':
  $rawSettings = getPostArray('settings');
  $dontStop = getPostArray('dontStop');
  $dontKeys = array_keys($dontStop);
  foreach ($dontStop as $key) {
    $donotStop[$key] = "true";
  }
  foreach ($rawSettings as $setting) {
    $backupOptions[$setting[0]] = $setting[1];
  }
  $backupOptions['excluded'] = trim($backupOptions['excluded']);
  $backupOptions['destinationShare'] = str_replace("/mnt/user/","",$backupOptions['destinationShare']);  # make new options conform to old layout of json
  $backupOptions['destinationShare'] = rtrim($backupOptions['destinationShare'],'/');
  $backupOptions['dontStop'] = $donotStop;
  writeJsonFile($communityPaths['backupOptions'],$backupOptions);
  exec($communityPaths['addCronScript']);
  break; 
  
###########################################
#                                         #
# Checks the status of a backup / restore #
#                                         #
###########################################

case 'checkBackup':
  if ( is_file($communityPaths['backupLog']) ) {
    $backupLines = "<font size='0'><tt>".shell_exec("tail -n10 ".$communityPaths['backupLog'])."</tt></font>";
    $backupLines = str_replace("\n","<br>",$backupLines);
  } else {
    $backupLines = "<br><br><br>";
  }
  if ( is_file($communityPaths['backupProgress']) || is_file($communityPaths['restoreProgress']) ) {
    $backupLines .= "
      <script>$('#backupStatus').html('<font color=red>Running</font> Your docker containers will be automatically restarted at the conclusion of the backup/restore');
      $('.statusLines').html('<font color=red>Backup / Restore Running');
      $('#restore').prop('disabled',true);
      $('#abort').prop('disabled',false);
      $('#Backup').attr('data-running','true');
      $('#Backup').prop('disabled',true);
      $('.miscScripts').prop('disabled',true);
      $('#deleteOldBackupSet').prop('disabled',true);
      $('#deleteIncompleteBackup').prop('disabled',true);
      $('#deleteOldBackupScript').prop('disabled',true);
      </script>";
  } else {
    $backupLines .= "
    <script>
    $('#backupStatus').html('<font color=green>Not Running</font>');
    $('.statusLines').html('');
    $('#abort').prop('disabled',true);
    $('.miscScripts').prop('disabled',false);
    $('#Backup').attr('data-running','false');
    if ( appliedChanges == false ) {
      $('#Backup').prop('disabled',false);
    }
    </script>";
    if ( is_file($communityPaths['backupOptions']) ) {
      $backupLines .= "
      <script>if ( appliedChanges == false ) {
        $('#restore').prop('disabled',false);
      } else { 
        $('#restore').prop('disabled',true);
      }
      </script>";
    }
  }
  if ( is_file($communityPaths['deleteProgress']) ) {
    $description = trim(file_get_contents($communityPaths['deleteProgress']));
    $backupLines .= "<script>$('.miscScripts').prop('disabled',true);
    $('#backupStatus').html('<font color=red>$description</font>');
    $('.statusLines').html('<font color=red>$description</font>');
    $('#restore').prop('disabled',true);
    $('#Backup').prop('disabled',true);
    </script>";
  }
  echo $backupLines;
  break;

############################################################################################
#                                                                                          #
# backupNow, restoreNow, abortBackup - executes scripts to start / stop backups / restores #
#                                                                                          #
############################################################################################

case 'backupNow':
  shell_exec("/usr/local/emhttp/plugins/ca.backup/scripts/backup.sh");
  break;
  
case 'restoreNow':
  $backupOptions['availableDates'] = isset($_POST['availableDates']) ? urldecode(($_POST['availableDates'])) : "";
  shell_exec("/usr/local/emhttp/plugins/ca.backup/scripts/restore.sh ".$backupOptions['availableDates']);
  break;
  
case 'deleteOldBackupSets':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);

  if ( ! $backupOptions['destinationShare'] ) {
    break;
  }
  $deleteFolder = escapeshellarg("/mnt/user/".$backupOptions['destinationShare']);
  shell_exec("/usr/local/emhttp/plugins/ca.backup/scripts/deleteOldBackupSets.sh $deleteFolder");
  break;

case 'deleteDatedBackupSets':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);
  if ( ! $backupOptions['destinationShare'] ) {
    break;
  }
  shell_exec("/usr/local/emhttp/plugins/ca.backup/scripts/deleteDatedBackupSets.sh");
  break;
  
case 'deleteIncompleteBackupSets':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);

  if ( ! $backupOptions['destinationShare'] ) {
    break;
  }
  $deleteFolder = escapeshellarg("/mnt/user/".$backupOptions['destinationShare']);
  shell_exec("/usr/local/emhttp/plugins/ca.backup/scripts/deleteIncompleteBackupSets.sh $deleteFolder");
  break;
  
case 'abortBackup':
  shell_exec("/usr/local/emhttp/plugins/ca.backup/scripts/killRsync.php");
  break;

case 'getBackupShare':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);
  echo "/mnt/user/".$backupOptions['destinationShare'];
  break;
  
case 'restoreSettings':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);
  $o = "<script>";
  if ( ! is_dir("/mnt/cache") ) {
    $o .= "
      $('#restoreErrors').html('No cache drive is installed / formatted.  You must install and format the cache drive prior to restoring a backup set');
    ";
  } else {
    if ( ! $backupOptions ) {
      $o .= "
        $('#restoreErrors').html('No backup settings have already been defined.  You must set those settings before you are able to restore any backups');
      ";
    } else {
      $o .= "
        $('#restoreSource').html('".$backupOptions['destination']."/".$backupOptions['destinationShare']."');
        $('#restoreDestination').html('".$backupOptions['source']."');
      ";
    }
    if ( $backupOptions['datedBackup'] == "yes" ) {
      $o .= "
        $('#availableDates').html('".getDates()."');
      ";
    } else {
      $o .= "
        $('#availableDates').html('Dated Backups Not Enabled');
      ";
    }
  }
  $o .= "</script>";
  echo $o;
  break;
}