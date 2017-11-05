#!/usr/bin/php
<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2017, Andrew Zawadzki #
#                                                             #
############################################################### 

if ( $argv[1] == "restore" ) {
  $restore = true;
  $restoreMsg = "Restore";
} else {
  $restore = false;
  $restoreMsg = "Backup";
}

require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("/usr/local/emhttp/plugins/ca.backup/include/paths.php");
require_once("/usr/local/emhttp/plugins/ca.backup/include/helpers.php");

exec("rm -rf ".$communityPaths['backupLog']);
exec("mkdir -p /var/lib/docker/unraid/ca.backup.datastore");

function backupLog($msg) {
  global $communityPaths;
  
  file_put_contents($communityPaths['backupLog'],"$msg\n",FILE_APPEND);
}

function getRsyncReturnValue($returnValue) {
  $returnMessage[0] = "Success";
  $returnMessage[1] = "Syntax or usage error";
  $returnMessage[2] = "Protocol incompatibility";
  $returnMessage[3] = "Errors selecting input/output files, dirs";
  $returnMessage[4] = "Requested action not supported: an attempt was made to manipulate 64-bit files on a platform that cannot support them; or an option was specified that is supported by the client and not by the server.";
  $returnMessage[5] = "Error starting client-server protocol";
  $returnMessage[6] = "Daemon unable to append to log-file";
  $returnMessage[10] = "Error in socket I/O";
  $returnMessage[11] = "Error in file I/O";
  $returnMessage[12] = "Error in rsync protocol data stream";
  $returnMessage[13] = "Errors with program diagnostics";
  $returnMessage[14] = "Error in IPC code";
  $returnMessage[20] = "Received SIGUSR1 or SIGINT";
  $returnMessage[21] = "Some error returned by waitpid()";
  $returnMessage[22] = "Error allocating core memory buffers";
  $returnMessage[23] = "Partial transfer due to error";
  $returnMessage[24] = "Partial transfer due to vanished source files";
  $returnMessage[25] = "The --max-delete limit stopped deletions";
  $returnMessage[30] = "Timeout in data send/receive";
  $returnMessage[35] = "Timeout waiting for daemon connection";
  
  $return = $returnMessage[$returnValue] ? $returnMessage[$returnValue] : "Unknown Error";
  return $return;
}

if ( is_file($communityPaths['backupProgress']) ) {
  logger("Backup already in progress.  Aborting");
  exit;
}
if ( is_file($communityPaths['restoreProgress']) ) {
  logger("Restore in progress. Aborting");
  exit;
}
@unlink($communityPaths['backupLog']);
$dockerSettings = @my_parse_ini_file($communityPaths['unRaidDockerSettings']);

if ( $restore ) {
  file_put_contents($communityPaths['restoreProgress'],getmypid());
} else {
  file_put_contents($communityPaths['backupProgress'],getmypid());
}
  
$dockerClient = new DockerClient();
$dockerRunning = $dockerClient->getDockerContainers();

$backupOptions = readJsonFile($communityPaths['backupOptions']);
if ( ! $backupOptions ) {
  @unlink($communityPaths['backupProgress']);
  exit;
}

$backupOptions['source'] = rtrim($backupOptions['source'],"/");
$backupOptions['dockerIMG'] = "exclude";

if ( ! $backupOptions['backupFlash'] ) { $backupOptions['backupFlash'] = "appdata"; }
if ( ! $backupOptions['backupXML'] )   { $backupOptions['backupXML'] = "appdata"; }

$basePathBackup = $backupOptions['destination']."/".$backupOptions['destinationShare'];

if ( ! $backupOptions['dockerIMG'] )     { $backupOptions['dockerIMG'] = "exclude"; }
if ( ! $backupOptions['notification'] )  { $backupOptions['notification'] = "always"; }
if ( ( $backupOptions['deleteOldBackup'] == "" ) || ( $backupOptions['deleteOldBackup'] == "0" ) ) { $backupOptions['fasterRsync'] = "no"; }
if ( ! $backupOptions['dockerStopDelay'] ) { $backupOptions['dockerStopDelay'] = 10; }

if ( $restore ) {
  if ( $backupOptions['datedBackup'] == "yes" ) {
    $backupOptions['destinationShare'] = $backupOptions['destinationShare']."/".$argv[2];
  }
} else {
  if ( $backupOptions['datedBackup'] == "yes" ) {
    $newFolderDated = exec("date +%F@%H.%M");
    $backupOptions['destinationShare'] = $backupOptions['destinationShare']."/".$newFolderDated;

    if ( $backupOptions['fasterRsync'] == "yes" ) {
      $currentDate = date_create(now);
      $dirContents = dirContents($basePathBackup);
      foreach ($dirContents as $dir) {
        $folderDate = date_create_from_format("Y-m-d@G.i",$dir);
        if ( ! $folderDate ) { continue; }
        $interval = date_diff($currentDate,$folderDate);
        $age = $interval->format("%R%a");
        if ( $age <= (0 - $backupOptions['deleteOldBackup']) ) {
          logger("Renaming $basePathBackup/$dir to $basePathBackup/$newFolderDated");
          exec("mv ".escapeshellarg($basePathBackup)."/".$dir." ".escapeshellarg($basePathBackup)."/".$newFolderDated);
          break;
        }   
      }
    }
  }
}

logger('#######################################');
logger("Community Applications appData $restoreMsg");
logger("Applications will be unavailable during");
logger("this process.  They will automatically");
logger("be restarted upon completion.");
logger('#######################################');
if ( $backupOptions['notification'] == "always" ) {
  notify("Community Applications","appData $restoreMsg","$restoreMsg of appData starting.  This may take awhile");
}
  
if ( $backupOptions['stopScript'] ) {
  logger("executing custom stop script ".$backupOptions['stopScript']);
  backupLog("Executing custom stop script");
  shell_exec($backupOptions['stopScript']." >> ".$communityPaths['backupLog']);
}
if ( is_array($dockerRunning) ) {
  foreach ($dockerRunning as $docker) {
    if ($docker['Running']) {
      if ( $backupOptions['dontStop'][$docker['Name']] ) {
        logger($docker['Name']." set to not be stopped by ca backup's advanced settings.  Skipping");
        backupLog($docker['Name']." set to not be stopped by ca backup's advanced settings.  Skipping");
        continue;
      }
      logger("Stopping ".$docker['Name']);
      backupLog("Stopping ".$docker['Name']);
      shell_exec("docker stop -t {$backupOptions['dockerStopDelay']} {$docker['Name']}");
      logger("docker stop -t {$backupOptions['dockerStopDelay']} {$docker['Name']}");
    }
  }
}
if ( $restore ) {
  $source = $backupOptions['destination']."/".$backupOptions['destinationShare']."/";
  $destination = $backupOptions['source'];
} else {
  $source = $backupOptions['source']."/";
  $destination = $backupOptions['destination']."/".$backupOptions['destinationShare'];
  if ( $backupOptions['backupFlash'] == "appdata" ) {
    $usbDestination = $source."Community_Applications_USB_Backup";
  } else {
    $usbDestination = $backupOptions['usbDestination'];
  }
  if ( $backupOptions['backupXML'] == "appdata" ) {
    $xmlDestination = $source."Community_Applications_VM_XML_Backup";
  } else {
    $xmlDestination = $backupOptions['xmlDestination'];
  }
  
  if ( $backupOptions['backupFlash'] != "no" ) {
    logger("Deleting Old USB Backup");
    exec("rm -rf '$usbDestination'");
    logger("Backing up USB Flash drive config folder to $usbDestination");
    backupLog("Backing up USB Flash Drive");
    exec("mkdir -p '$usbDestination'");
    $availableDisks = my_parse_ini_file("/var/local/emhttp/disks.ini",true);
    $txt .= "Disk Assignments as of ".date(DATE_RSS)."\r\n";
    foreach ($availableDisks as $Disk) {
      $txt .= "Disk: ".$Disk['name']."  Device: ".$Disk['id']."  Status: ".$Disk['status']."\r\n";
    }
    file_put_contents("/boot/config/DISK_ASSIGNMENTS.txt",$txt);
    $command = '/usr/bin/rsync '.$backupOptions['rsyncOption'].' --log-file="'.$communityPaths['backupLog'].'" /boot/ "'.$usbDestination.'" > /dev/null 2>&1';
    logger("Using command: $command");
    exec($command);
    
    exec("mv '$usbDestination/config/super.dat' '$usbDestination/config/super.dat.CA_BACKUP'");
  }
  if ( $backupOptions['backupXML'] != "no" ) {
    logger("Backing up libvirt.img to $xmlDestination");
    backupLog("Backing up libvirt.img");
    exec("mkdir -p '$xmlDestination'");
    $domainCFG = @parse_ini_file("/boot/config/domain.cfg");
    if ( is_file($domainCFG['IMAGE_FILE']) ) {
      $command = '/usr/bin/rsync '.$backupOptions["rsyncOption"].' --log-file="'.$communityPaths["backupLog"].'" "'.$domainCFG["IMAGE_FILE"].'" "'.$xmlDestination.'" > /dev/null 2>&1';
      exec($command);
    }
    $command = '/usr/bin/rsync '.$backupOptions['rsyncOption'].' --log-file="'.$communityPaths['backupLog'].'" /etc/libvirt/qemu/ "'.$xmlDestination.'" > /dev/null 2>&1';
  }
}
if ( $backupOptions['dockerIMG'] == "exclude" ) {
  $dockerIMGFilter = '--exclude "'.str_replace($backupOptions['source']."/","",$dockerSettings['DOCKER_IMAGE_FILE']).'"';
}

if ( $backupOptions['excluded'] ) {
  $exclusions = explode(",",$backupOptions['excluded']);
  
  foreach ($exclusions as $excluded) {
    $rsyncExcluded .= '--exclude "'.$excluded.'" ';
  }
	$rsyncExcluded = str_replace($source,"",$rsyncExcluded);
}

if ( $backupOptions['runRsync'] == "true" ) {
  $logLine = $restore ? "Restoring " : "Backing Up";
  logger("$logLine appData from $source to $destination");
  backupLog("$logLine appData from $source to $destination");
  $command = '/usr/bin/rsync '.$backupOptions['rsyncOption'].' '.$dockerIMGFilter.' '.$rsyncExcluded.' --log-file="'.$communityPaths['backupLog'].'" "'.$source.'" "'.$destination.'" > /dev/null 2>&1';
  logger('Using command: '.$command);
  backupLog("Executing rsync: $command");
  exec("mkdir -p ".escapeshellarg($destination));
  exec($command,$output,$returnValue);
  logger("$restoreMsg Complete");
}
if ( $backupOptions['updateApps'] == "yes" && is_file("/var/log/plugins/ca.update.applications.plg") ) {
  backupLog("Searching for updates to docker applications");
  exec("/usr/local/emhttp/plugins/ca.update.applications/scripts/updateDocker.php");
}
if ( is_array($dockerRunning) ) {
  $autostart = readJsonFile("/boot/config/plugins/ca.docker.autostart/settings.json");
  foreach ($dockerRunning as $docker) {
    if ($docker['Running']) {
      if ( $backupOptions['dontStop'][$docker['Name']] ) {
        continue;
      }
      $autostartIndex = searchArray($autostart,"name",$docker['Name']);
      if ( $autostartIndex !== false ) {
        continue;
      }
      logger("Restarting ".$docker['Name']);
      backupLog("Restarting ".$docker['Name']);
      shell_exec("docker start ".$docker['Name']);
    }
  }
  if ( $autostart ) {
    $networkINI = parse_ini_file("/usr/local/emhttp/state/network.ini",true);
    $defaultIP = $networkINI['eth0']['IPADDR:0'];
    foreach ($autostart as $docker) {
      $index = searchArray($dockerRunning,"Name",$docker['name']);
      if ( $index === false ) {
        continue;
      }
      if ( $backupOptions['dontStop'][$docker['name']] ) {
        continue;
      }
      if ( $dockerRunning[$index]['Running'] ) {
        $delay = $docker['delay'];
        if ( ! $delay ) {
          $delay = 0;
        }
        $containerName = $docker['name'];
        $containerDelay = $docker['delay'];
        $containerPort = $docker['port'];
        $containerIP = $docker['IP'];
        if ( ! $containerIP ) {
          $containerIP = $defaultIP;
        }
        if ( ! $containerIP ) {
          unset($containerPort);
        }
        if ( $docker['port'] ) {
          logger("Restarting $containerName");
          backupLog("Restarting $containerName");
          exec("docker start $containerName");
          logger("Waiting for port $containerPort to be available before continuing... Timeout of $containerDelay seconds");
          backupLog("Waiting for port $containerIP:$containerPort to be available before continuing... Timeout of $containerDelay seconds");
          for ($time = 0; $time < $containerDelay; $time++) {
            exec("echo test 2>/dev/null > /dev/tcp/$containerIP/$containerPort",$output,$error);
            if ( ! $error) {
              break;
            }
            sleep(1);
          }
          if ( $error ) {
            logger("$containerPort still not available.  Carrying on.");
            backupLog("$containerPort still not available.  Carrying on.");
          }
        } else {
          logger("Sleeping $delay seconds before starting ".$docker['name']);
          backupLog("Sleeping $delay seconds before starting ".$docker['name']);
          sleep($delay);
          logger("Restarting ".$docker['name']);
          backupLog("Restarting ".$docker['name']);
          shell_exec("docker start ".$docker['name']);         
        }
      }
    }
  }
}
if ( $backupOptions['startScript'] ) {
  logger("Executing custom start script ".$backupOptions['startScript']);
  backupLog("Executing custom start script");
  shell_exec($backupOptions['startScript']." >> ".$communityPaths['backupLog']);
}
logger('#######################');
logger("appData $restoreMsg complete");
logger('#######################');

$message = getRsyncReturnValue($returnValue);

if ( $returnValue > 0 ) {
  $status = "- Errors occurred";
  $type = "warning";
} else {
  $type = "normal";
}

backupLog("Backup/Restore Complete.  Rsync Status: $message");

switch ($backupOptions['logBackup']) {
  case 'yes':
    toDOS($communityPaths['backupLog'],"/boot/config/plugins/ca.backup/backup.log");
    $logMessage = " - Log is available on the flash drive at /config/plugins/ca.backup/backup.log";
    break;
  case 'append':
    toDOS($communityPaths['backupLog'],"/boot/config/plugins/ca.backup/backup.log",true);
    $logMessage = " - Log is available on the flash drive at /config/plugins/ca.backup/backup.log";
    break;
  case 'no':
    $logMessage = "";
    logger("Rsync log to flash drive disabled");
    break;
  default:
    $logMessage = "";
    logger("Rsync log to flash drive disabled");
    break;

}

if ( ($backupOptions['notification'] == "always") || ($backupOptions['notification'] == "completion") || ( ($backupOptions['notification'] == "errors") && ($type == "warning") )  ) {
  notify("Community Applications","appData $restoreMsg","$restoreMsg of appData complete $status$logMessage",$message,$type);
}

if ( ! $restore && ($backupOptions['datedBackup'] == 'yes') ) {
  if ( $backupOptions['deleteOldBackup'] ) {
    if ( $returnValue > 0 ) {
      logger("rsync returned errors.  Not deleting old backup sets of appdata");
      backupLog("rsync returned errors.  Not deleting old backup sets of appdata");
      logger("Renaming $destination to $destination-error\n");
      backupLog("Renaming $destination to $destination-error");
      
      exec("mv ".escapeshellarg("$destination")." ".escapeshellarg("$destination-error"));
    } else {
      $currentDate = date_create(now);
      $dirContents = dirContents($basePathBackup);
			unset($command);
      foreach ($dirContents as $dir) {
        $folderDate = date_create_from_format("Y-m-d@G.i",$dir);
        if ( ! $folderDate ) { continue; }
        $interval = date_diff($currentDate,$folderDate);
        $age = $interval->format("%R%a");
        if ( $age <= (0 - $backupOptions['deleteOldBackup']) ) {
          logger("Deleting $basePathBackup/$dir");
          backupLog("Deleting Dated Backup set: $basePathBackup/$dir");
          $command .= 'nice -n 19 rm -rf '.escapeshellarg($basePathBackup.'/'.$dir)."\n";
        }   
      }
			if ( $command ) {
				$command = "#!/bin/bash\necho 'Deleting Old Backup Sets' > /tmp/ca.backup/tempFiles/deleteInProgress\n$command\nrm /tmp/ca.backup/tempFiles/deleteInProgress\n";
				$descriptorspec = array(
					0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
					1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
					2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
				);
				file_put_contents($communityPaths['deleteScriptPath'],$command);
				exec("chmod 0777 {$communityPaths['deleteScriptPath']}");
				$process = proc_open($communityPaths['deleteScriptPath'], $descriptorspec,$pipes);
			}
    }
  }
}
if ( ! startsWith($destination,"/mnt/user") ) {
  if ( $restore) {
    $temp = explode("/",$destination);
    $shareName = $temp[3];

    $shareCfg = @file_get_contents("/boot/config/shares/$shareName.cfg");
    if ( ! $shareCfg ) {
      $shareCfg = file_get_contents($communityPaths['defaultShareConfig']);
    }
    $shareCfg = str_replace('shareUseCache="no"','shareUseCache="only"',$shareCfg);
    backupLog("Setting $shareName share to be cache-only");
    file_put_contents("/boot/config/shares/$shareName.cfg",$shareCfg);
    backupLog("Deleting any appdata files stored on the array");
    exec('rm -rf '.escapeshellarg("/mnt/user0/$shareName"));
  
    backupLog("Restore finished.  Ideally you should now restart your server");
  }
}
if ( $returnValue > 0 ) {
  logger("Rsync Errors Occurred: $message");
  logger("Possible rsync errors:");
  exec("cat ".$communityPaths['backupLog']." | grep rsync -m 10",$rsyncLog);
  foreach ($rsyncLog as $logLine) {
    ++$line;
    logger($logLine);
    backupLog($logLine);
  }
}
if ( $restore ) {
  unlink($communityPaths['restoreProgress']);
} else {
  unlink($communityPaths['backupProgress']);
}
backupLog("Backup / Restore Completed");
logger("Backup / Restore Completed");

?>