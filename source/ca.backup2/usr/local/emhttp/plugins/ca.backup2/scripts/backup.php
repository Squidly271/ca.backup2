#!/usr/bin/php
<?PHP
###############################################################
#                                                             #
# Community Applications copyright 2015-2020, Andrew Zawadzki #
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
require_once("/usr/local/emhttp/plugins/ca.backup2/include/paths.php");
require_once("/usr/local/emhttp/plugins/ca.backup2/include/helpers.php");

/** @var $communityPaths array */

exec("rm -rf ".$communityPaths['backupLog']);
exec("mkdir -p /var/lib/docker/unraid/ca.backup2.datastore");

/**
 * Logs a message to the Backup Log (the one shown at "Backup / Restore status"
 * @param $msg string The Text to be logged
 * @return void
 */
function backupLog($msg, $newLine = true, $skipDate = false) {
	global $communityPaths;
	
	file_put_contents($communityPaths['backupLog'],($skipDate ? '' :"[".date("d.m.Y H:i:s")."]")." $msg".($newLine ? "\n" : ''),FILE_APPEND);
}

if ( ! is_dir("/mnt/user") ) {
	logger("It doesn't appear that the array is running.  Exiting CA Backup");
	exit();
} 
$backupOptions = readJsonFile($communityPaths['backupOptions']);
if ( ! $backupOptions ) {
	@unlink($communityPaths['backupProgress']);
	exit;
}

if ( is_file($communityPaths['backupProgress']) ) {
	logger("Backup already in progress.  Aborting");
	exit;
}
if ( is_file($communityPaths['restoreProgress']) ) {
	logger("Restore in progress. Aborting");
	exit;
}
$dockerOptions = parse_ini_file($communityPaths['unRaidDockerSettings'],true);
$dockerImageFile = basename($dockerOptions['DOCKER_IMAGE_FILE']);

if ( $restore ) {
	$backupOptions['destinationShare'] = $backupOptions['destinationShare']."/".$argv[2];
	if ( is_file($backupOptions['destinationShare']."/CA_backup.tar") ) {
		$restoreFile = $backupOptions['destinationShare']."/CA_backup.tar";
	}
	if ( is_file($backupOptions['destinationShare']."/CA_backup.tar.gz") ) {
		$restoreFile = $backupOptions['destinationShare']."/CA_backup.tar.gz";
	}
	$restoreDestination = $backupOptions['source'];
	if ( ! $restoreFile ) {
		logger("Restore File Not Found.  Aborting");
		exit;
	}
}

$dockerSettings = @my_parse_ini_file($communityPaths['unRaidDockerSettings']);

if ( $restore ) {
	file_put_contents($communityPaths['restoreProgress'],getmypid());
} else {
	file_put_contents($communityPaths['backupProgress'],getmypid());
}
	
$dockerClient = new DockerClient();
$dockerRunning = $dockerClient->getDockerContainers();

$backupOptions['source'] = rtrim($backupOptions['source'],"/");
$backupOptions['dockerIMG'] = "exclude";

if ( ! $backupOptions['backupFlash'] ) { $backupOptions['backupFlash'] = "appdata"; }
if ( ! $backupOptions['backupXML'] )   { $backupOptions['backupXML'] = "appdata"; }

$basePathBackup = $backupOptions['destinationShare'];

if ( ! $backupOptions['dockerIMG'] )     { $backupOptions['dockerIMG'] = "exclude"; }
if ( ! $backupOptions['notification'] )  { $backupOptions['notification'] = "always"; }
if ( ( $backupOptions['deleteOldBackup'] == "" ) || ( $backupOptions['deleteOldBackup'] == "0" ) ) { $backupOptions['fasterRsync'] = "no"; }
if ( ! $backupOptions['dockerStopDelay'] ) { $backupOptions['dockerStopDelay'] = 10; }
$backupOptions["rsyncOption"] = " -avXHq --delete ";

$newFolderDated = exec("date +%F@%H.%M");
$backupOptions['destinationShare'] = $backupOptions['destinationShare']."/".$newFolderDated;

logger('#######################################');
logger("Community Applications appData $restoreMsg");
logger("Applications will be unavailable during");
logger("this process.  They will automatically");
logger("be restarted upon completion.");
logger('#######################################');
if ( $backupOptions['notification'] == "always" ) {
	notify("Community Applications","appData $restoreMsg","$restoreMsg of appData starting.  This may take awhile");
}

backupLog("$restoreMsg of appData starting. This may take awhile");

if ( $backupOptions['stopScript'] ) {
	logger("executing custom stop script ".$backupOptions['stopScript']);  backupLog("Executing custom stop script");
	copy($backupOptions['stopScript'],$communityPaths['tempScript']);
	chmod($communityPaths['tempScript'],0777);
	shell_exec($communityPaths['tempScript']." >> ".$communityPaths['backupLog']);
}
if ( is_array($dockerRunning) ) {
    $stopTimer = 0;
	foreach ($dockerRunning as $docker) {
		if ($docker['Running']) {
			if ( $backupOptions['dontStop'][$docker['Name']] ) {
				$dontRestart[$docker['Name']] = true;
				logger($docker['Name']." set to not be stopped by ca backup's advanced settings.  Skipping");  backupLog($docker['Name']." set to not be stopped by ca backup's advanced settings.  Skipping");
				continue;
			}
			logger("Stopping ".$docker['Name']); backupLog("Stopping ".$docker['Name']."... ", false);
            $stopTimer = time();
			shell_exec("docker stop -t {$backupOptions['dockerStopDelay']} {$docker['Name']}");
			logger("docker stop -t {$backupOptions['dockerStopDelay']} {$docker['Name']}");

            backupLog("done (took ".(time()-$stopTimer)." seconds)", true, true);

		}
	}
}
if ( ! $restore ) {
	$source = $backupOptions['source']."/";
	$destination = $backupOptions['destinationShare'];
	
	if ( $backupOptions['usbDestination'] ) {
		logger("Backing up USB Flash drive config folder to $usbDestination");  backupLog("Backing up USB Flash Drive");
		exec("mkdir -p '{$backupOptions['usbDestination']}'");
		$availableDisks = my_parse_ini_file("/var/local/emhttp/disks.ini",true);
		$txt .= "Disk Assignments\r\n";
		foreach ($availableDisks as $Disk) {
			$txt .= "Disk: ".$Disk['name']."  Device: ".$Disk['id']."  Status: ".$Disk['status']."\r\n";
		}
		$oldAssignments = @file_get_contents("/boot/config/DISK_ASSIGNMENTS.txt");
		if ( $oldAssignments != $txt ) {
			file_put_contents("/boot/config/DISK_ASSIGNMENTS.txt",$txt);
		}
		if ( is_dir("/boot") ) {
			$command = '/usr/bin/rsync '.$backupOptions['rsyncOption'].' --log-file="'.$communityPaths['backupLog'].'" /boot/ "'.$backupOptions['usbDestination'].'" > /dev/null 2>&1';
		  logger("Using command: $command");
			exec($command);
			exec("mv '{$backupOptions['usbDestination']}/config/super.dat' '{$backupOptions['usbDestination']}/config/super.dat.CA_BACKUP'");
			logger("Changing permissions on backup");
			exec("chmod 0777 -R ".escapeshellarg($backupOptions['usbDestination']));
		} else {
			$missingSource = true;
			logger("USB not backed up.  Missing source");
		}
	}
	if ( $backupOptions['xmlDestination'] ) {
		logger("Backing up libvirt.img to {$backupOptions['xmlDestination']}");  backupLog("Backing up libvirt.img");
		exec("mkdir -p '{$backupOptions['xmlDestination']}'");
		$domainCFG = @parse_ini_file("/boot/config/domain.cfg");
		if ( is_file($domainCFG['IMAGE_FILE']) ) {
			$command = '/usr/bin/rsync '.$backupOptions["rsyncOption"].' --log-file="'.$communityPaths["backupLog"].'" "'.$domainCFG["IMAGE_FILE"].'" "'.$backupOptions['xmlDestination'].'" > /dev/null 2>&1';
			logger("Using Command: $command"); backupLog("Using Command: $command");
			exec($command);
			logger("Changing permissions on backup");
			exec("chmod 0777 -R ".escapeshellarg($backupOptions['xmlDestination']));
		}
	}
}

if ( $backupOptions['excluded'] ) {
	$exclusions = explode(",",$backupOptions['excluded']);
	$rsyncExcluded = " ";
	foreach ($exclusions as $excluded) {
		$rsyncExcluded .= '--exclude "'.rtrim($excluded,"/").'" ';
	}
	$rsyncExcluded = str_replace($source,"",$rsyncExcluded);
}

$logLine = $restore ? "Restoring " : "Backing Up";
$fileExt = ($backupOptions['compression']) == "yes" ? ".tar.gz" : ".tar";
logger("$logLine appData from $source to $destination"); backupLog("$logLine appData from $source to $destination");
if ( ! $restore ) {
	if ( is_dir($source) ) {
		$command = "cd ".escapeshellarg($source)." && /usr/bin/tar -caf ".escapeshellarg("{$destination}/CA_backup$fileExt")." $rsyncExcluded * >> {$communityPaths['backupLog']} 2>&1 & echo $! > {$communityPaths['backupProgress']} && wait $!";
		exec("mkdir -p ".escapeshellarg($destination));
		exec("chmod 0777 ".escapeshellarg($destination));
	} else {
		logger("Appdata not backed up.  Missing source");
		$missingSource = true;
	}
} else {
	$command = "cd ".escapeshellarg($restoreDestination)." && /usr/bin/tar -xvaf ".escapeshellarg($restoreFile)." >> {$communityPaths['backupLog']} 2>&1 & echo $! > {$communityPaths['restoreProgress']} && wait $!";
	exec("mkdir -p ".escapeshellarg($restoreDestination));
}
logger('Using command: '.$command); backupLog("Executing tar: $command");
exec($command,$out,$returnValue);
if ( $returnValue > 0 )	{
        logger("tar creation failed!"); backupLog("tar creation failed!");
        $tarErrors = file_get_contents($communityPaths['backupLog']);
    } else {
    if (!$restore)
        exec("chmod 0777 " . escapeshellarg("{$destination}/CA_backup$fileExt"));

    logger("$restoreMsg Complete");
    if ($backupOptions['verify'] == "yes" && !$restore) {
        $command = "cd " . escapeshellarg("$source") . " && /usr/bin/tar --diff -C '$source' -af " . escapeshellarg("$destination/CA_backup$fileExt") . " >> {$communityPaths['backupLog']} 2>&1 & echo $! > {$communityPaths['verifyProgress']} && wait $!";
        logger("Verifying backup"); backupLog("Verifying Backup");
        logger("Using command: $command"); backupLog("Using command: $command");
        exec($command, $out, $returnValue);
        unlink($communityPaths['verifyProgress']);
        if ($returnValue > 0) {
            $tarErrors = file_get_contents($communityPaths['backupLog']);
        }
    }
}

if ( $backupOptions['updateApps'] == "yes" && is_file("/var/log/plugins/ca.update.applications.plg") ) {
	backupLog("Searching for updates to docker applications");
	exec("/usr/local/emhttp/plugins/ca.update.applications/scripts/updateDocker.php");
}
if ( $backupOptions['preStartScript'] ) {
	logger("Executing custom pre-start script ".$backupOptions['preStartScript']);  backupLog("Executing custom pre-start script");
	copy($backupOptions['preStartScript'],$communityPaths['tempScript']);
	chmod($communityPaths['tempScript'],0777);
	shell_exec($communityPaths['tempScript']." >> ".$communityPaths['backupLog']);
}
$unraidVersion = parse_ini_file("/etc/unraid-version");
if ( version_compare($unraidVersion["version"],"6.5.3",">") ){
	echo "6.6 version";
	if ( is_array($dockerRunning) ) {
		$autostart = file("/var/lib/docker/unraid-autostart");
		foreach ($autostart as $auto) {
			$line = explode(" ",$auto);
			$autostartList[trim($line[0])] = trim($line[1]);
		}
		foreach ($dockerRunning as $container) {
			if ($container['Running'] && ! isset($autostartList[$container['Name']])) {
				$autostartList[$container['Name']] = 0;
			}
		}

		foreach ($autostartList as $docker=>$delay) {
			$autostartIndex = searchArray($dockerRunning,"Name",$docker);
			if ( $autostartIndex === false ) {
				continue;
			}
			if ( ! $dockerRunning[$autostartIndex]['Running'] ) {
				continue;
			}
			if ( $dontRestart[$docker] ) {
				continue;
			}

			backupLog("Starting $docker");
			shell_exec("docker start ".$docker);
			if ($delay) {
				backupLog("Waiting $delay seconds before carrying on");
				sleep($delay);
			}
		}
	}
} else {
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
				logger("Restarting ".$docker['Name']); backupLog("Restarting ".$docker['Name']);
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
						logger("Restarting $containerName"); backupLog("Restarting $containerName");
						exec("docker start $containerName");
						logger("Waiting for port $containerPort to be available before continuing... Timeout of $containerDelay seconds"); backupLog("Waiting for port $containerIP:$containerPort to be available before continuing... Timeout of $containerDelay seconds");
						for ($time = 0; $time < $containerDelay; $time++) {
							exec("echo test 2>/dev/null > /dev/tcp/$containerIP/$containerPort",$output,$error);
							if ( ! $error) {
								break;
							}
							sleep(1);
						}
						if ( $error ) {
							logger("$containerPort still not available.  Carrying on."); backupLog("$containerPort still not available.  Carrying on.");
						}
					} else {
						logger("Sleeping $delay seconds before starting ".$docker['name']); backupLog("Sleeping $delay seconds before starting ".$docker['name']);
						sleep($delay);
						logger("Restarting ".$docker['name']); backupLog("Restarting ".$docker['name']);
						shell_exec("docker start ".$docker['name']);         
					}
				}
			}
		}
	}
}

if ( $backupOptions['startScript'] ) {
	logger("Executing custom start script ".$backupOptions['startScript']);  backupLog("Executing custom start script");
	copy($backupOptions['startScript'],$communityPaths['tempScript']);
	chmod($communityPaths['tempScript'],0777);
	shell_exec($communityPaths['tempScript']." >> ".$communityPaths['backupLog']);
}
logger('#######################');
logger("appData $restoreMsg complete");
logger('#######################');

backupLog("Backup/Restore Complete.  tar Return Value: $returnValue");
if ( $returnValue > 0 ) {
	$status = "- Errors occurred";
	$type = "warning";
	$notifyMsg = "Full details in the syslog";
	foreach (explode("\n",$tarErrors) as $errorLine) {
		logger($errorLine);
	}
} else {
	$type = "normal";
}

if ( ($backupOptions['notification'] == "always") || ($backupOptions['notification'] == "completion") || ( ($backupOptions['notification'] == "errors") && ($type == "warning") )  ) {
	notify("CA Backup","appData $restoreMsg","$restoreMsg of appData complete $status$logMessage",$notifyMsg,$type);
}

if ( ! $restore ) {
	if ( $backupOptions['deleteOldBackup'] && ! $missingSource ) {
		if ( $returnValue > 0 ) {
			logger("tar verify returned errors.  Not deleting old backup sets of appdata"); backupLog("tar verify returned errors.  Not deleting old backup sets of appdata");
			exec("mv ".escapeshellarg($destination)." ".escapeshellarg("$destination-error"));
		} else {
			$currentDate = date_create("now");
			$dirContents = dirContents($basePathBackup);
			unset($command);
			foreach ($dirContents as $dir) {
				$testdir = str_replace("-error","",$dir);
				$folderDate = date_create_from_format("Y-m-d@G.i",$testdir);
				if ( ! $folderDate ) { continue; }
				$interval = date_diff($currentDate,$folderDate);
				$age = $interval->format("%R%a");
				if ( $age <= (0 - $backupOptions['deleteOldBackup']) ) {
					logger("Deleting $basePathBackup/$dir"); backupLog("Deleting Dated Backup set: $basePathBackup/$dir");
					exec("rm -rf ".escapeshellarg("$basePathBackup/$dir"));
				}   
			}
		}
	}
}
if ( $restore) {
	backupLog("Restore finished.  Ideally you should now restart your server");
}

if ( $returnValue > 0 ) {
	logger("tar verify errors occurred");
}
@unlink($communityPaths['restoreProgress']);
@unlink($communityPaths['backupProgress']);

backupLog("Backup / Restore Completed"); logger("Backup / Restore Completed");

?>