<?
###############################################################
#                                                             #
# Community Applications copyright 2015-2020, Andrew Zawadzki #
#                                                             #
###############################################################

function getLineCount($directory) {
  global $lineCount;

  $allFiles = array_diff(scandir($directory),array(".",".."));
  foreach ($allFiles as $file) {
    if (is_dir("$directory/$file")) {
      getLineCount("$directory/$file");
      continue;
    }
    $extension = pathinfo("$directory/$file",PATHINFO_EXTENSION);
    if ( $extension == "sh" || $extension == "php" || $extension == "page" ) {
      $lineCount = $lineCount + count(file("$directory/$file"));
    }
  }
}

$caCredits = "
    <center><table align:'center' style='background:initial;'>
      <tr>
        <td><img src='https://github.com/Squidly271/community.applications/raw/master/webImages/Tripping-the-Rift-Chode_400x400.jpg' width='50px';height='48px'></td>
        <td><strong>Andrew Zawadzki</strong></td>
        <td>Main Development</td>
      </tr>
      <tr>
        <td><img src='http://i.imgur.com/hpBxTJX.jpg' width='48px' height='48px'></td>
        <td><strong>CHBMB</strong></td>
        <td>Additional Assistance</td>
      </tr>
    </table></center>
    <br>
    <center><em><font size='1'>Copyright 2015-2020 Andrew Zawadzki</font></em></center>
    <center><a href='https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7M7CBCVU732XG' target='_blank'><img src='https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif'></a></center>
    <br><center><a href='https://forums.lime-technology.com/topic/61211-plugin-ca-appdata-backup-restore-v2/' target='_blank'>Plugin Support Thread</a></center>
  ";
  getLineCount("/usr/local/emhttp/plugins/ca.backup2");
  $caCredits .= "<center>$lineCount Lines of code and counting!</center>";
  $caCredits = str_replace("\n","",$caCredits);
?>