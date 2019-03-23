<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2019, Andrew Zawadzki #
#                                                             #
###############################################################

require_once("/usr/local/emhttp/plugins/ca.backup2/include/paths.php");
require_once("/usr/local/emhttp/plugins/ca.backup2/include/helpers.php");

function getDates() {
  global $communityPaths;
  
  $backupOptions = readJsonFile($communityPaths['backupOptions']);

  $availableDates = @array_diff(@scandir($backupOptions['destinationShare']),array(".",".."));
  if ( ! is_array($availableDates) ) {
    return "No Backup Sets Found";
  }
  foreach ($availableDates as $date) {
		if ( is_file("{$backupOptions['destinationShare']}/$date/CA_backup.tar") || is_file("{$backupOptions['destinationShare']}/$date/CA_backup.tar.gz") ) {
			$output .= '<option value="'.$date.'">'.$date.'</option>';
		}
  }
	if ( ! $output) {
		return "No Backup Sets Found";
	}
  return '<select id="date">'.$output.'</select>';
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

  $destinationShare = rtrim($settings['destinationShare'],'/');

  if ( $settings['source'] == "" ) {
    $errors .= "Source Must Be Specified<br>";
  }
	$testSource = $settings['source'];
	$availableDisks = my_parse_ini_file("/var/local/emhttp/disks.ini",true);
	foreach ($availableDisks as $disk) {
		$testSource = str_replace("/mnt/".$disk['name']."/","",$testSource);
	}
  $testSource = str_replace("/mnt/user0/","",$testSource);
	$testSource = str_replace("/mnt/user/","",$testSource);
	$testSource = str_replace("/mnt/disks/","",$testSource);
	if ( ! $testSource ) {
		$errors .= "Source cannot be /mnt/user or /mnt/disk<br>";
	}
	$testDest = $settings['destinationShare'];
	foreach ($availableDisks as $disk) {
		$testDest = str_replace("/mnt/".$disk['name']."/","",$testDest);
	}
  $testDest = str_replace("/mnt/user0/","",$testDest);
	$testDest = str_replace("/mnt/user/","",$testDest);
	$testDest = str_replace("/mnt/disks/","",$testDest);
	if ( ! $testDest ) {
		$errors .= "Destination cannot be /mnt/user or /mnt/disk<br>";
	}
  if ( $settings['source'] != "" && $settings['source'] == $settings['destinationShare'] ) {
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
  if ( ($settings['usbDestination'] == $settings['destinationShare']) || ($settings['xmlDestination'] == $settings['destinationShare']) ) {
		$errors .= "USB / XML destinations cannot be the same as appdata destination<br>";
	}
  if ( $settings['usbDestination'] ) {
		$origUSBDestination = $settings['usbDestination'];
		$usbDestination = $settings['usbDestination'];
		foreach ($availableDisks as $disk) {
			$usbDestination = str_replace("/mnt/".$disk['name']."/","",$usbDestination);
		}
 		$usbDestination = str_replace("/mnt/user0/","",$usbDestination);
		$usbDestination = str_replace("/mnt/user/","",$usbDestination);
		$usbDestination = str_replace("/mnt/disks/","",$usbDestination);
		
		if ( $usbDestination == "" ) {
			$errors .= "USB Destination cannot be the root directory of /mnt/user or of a disk<br>";
		}
		if ( ! is_dir($origUSBDestination) ) {
			$errors .= "USB Destination Not A Valid Directory<br>";
		}
	}
  if ( startsWith($usbDestination,$destinationShare) ) {
    $errors .= "USB Destination ($usbDestination) $destinationShare cannot be a sub-folder of Appdata destination<br>";
  }
	if ( $settings['xmlDestination'] ) {
		$origXMLDestination = $settings['xmlDestination'];
		$xmlDestination = $settings['xmlDestination'];
		foreach ($availableDisks as $disk) {
			$xmlDestination = str_replace("/mnt/".$disk['name']."/","",$xmlDestination);
		}
		$xmlDestination = str_replace("/mnt/user0/","",$xmlDestination);
		$xmlDestination = str_replace("/mnt/user/","",$xmlDestination);
		$xmlDestination = str_replace("/mnt/disks/","",$xmlDestination);
		
		if ( $xmlDestination == "" ) {
			$errors .= "XML Destination cannot be the root directory of /mnt/user or of a disk<br>";
		}
		if ( ! is_dir($origXMLDestination) ) {
			$errors .= "XML Destination Not A Valid Directory<br>";
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
	if ( is_file($communityPaths['verifyProgress']) ) {
		$backupLines .= "
      <script>$('#backupStatus').html('<font color=red>Verifying</font> Your docker containers will be automatically restarted at the conclusion of the backup/restore');
      $('.statusLines').html('<font color=red>Verifying');
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
		}
	}
  if ( is_file($communityPaths['backupOptions']) ) {
    $backupLines .= "
			<script>if ( appliedChanges == false ) {
        $('#restore').prop('disabled',false);
      } else { 
        $('#restore').prop('disabled',true);
      }
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
  shell_exec("/usr/local/emhttp/plugins/ca.backup2/scripts/backup.sh");
  break;
  
case 'restoreNow':
  $backupOptions['availableDates'] = isset($_POST['availableDates']) ? urldecode(($_POST['availableDates'])) : "";
  shell_exec("/usr/local/emhttp/plugins/ca.backup2/scripts/restore.sh ".$backupOptions['availableDates']);
  break;
  
case 'deleteOldBackupSets':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);

  if ( ! $backupOptions['destinationShare'] ) {
    break;
  }
  $deleteFolder = escapeshellarg("/mnt/user/".$backupOptions['destinationShare']);
  shell_exec("/usr/local/emhttp/plugins/ca.backup2/scripts/deleteOldBackupSets.sh $deleteFolder");
  break;

case 'deleteDatedBackupSets':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);
  if ( ! $backupOptions['destinationShare'] ) {
    break;
  }
  shell_exec("/usr/local/emhttp/plugins/ca.backup2/scripts/deleteDatedBackupSets.sh");
  break;
  
case 'deleteIncompleteBackupSets':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);

  if ( ! $backupOptions['destinationShare'] ) {
    break;
  }
  $deleteFolder = escapeshellarg("/mnt/user/".$backupOptions['destinationShare']);
  shell_exec("/usr/local/emhttp/plugins/ca.backup2/scripts/deleteIncompleteBackupSets.sh $deleteFolder");
  break;
  
case 'abortBackup':
  shell_exec("/usr/local/emhttp/plugins/ca.backup2/scripts/killRsync.php");
  break;

case 'getBackupShare':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);
  echo "/mnt/user/".$backupOptions['destinationShare'];
  break;
  
case 'restoreSettings':
  $backupOptions = readJsonFile($communityPaths['backupOptions']);
  $o = "<script>";
  if ( ! $backupOptions ) {
		$o .= "
			$('#restoreErrors').html('No backup settings have already been defined.  You must set those settings before you are able to restore any backups');
		";
	} else {
		$o .= "
			$('#restoreSource').html('".$backupOptions['destinationShare']."');
			$('#restoreDestination').html('".$backupOptions['source']."');
		";
	}
	$o .= "
		$('#availableDates').html('".getDates()."');
	";

  $o .= "</script>";
  echo $o;
  break;
}