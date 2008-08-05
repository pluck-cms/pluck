<?php
/* 
 * This file is part of pluck, the easy content management system
 * Copyright (c) somp (www.somp.nl)
 * http://www.pluck-cms.org
 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 
 * See docs/COPYING for the complete license.
*/

//Make sure the file isn't accessed directly
if((!ereg("index.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("admin.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("install.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("login.php", $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo "access denied";
    //Block all other code
    exit();
}

//First get the day of the year
$dayofyear = date("z");

//If the updatecheckfile existst, include it
if (file_exists("data/settings/update_lastcheck.php")) {
	include("data/settings/update_lastcheck.php");
}

//We want to check for updates if:
//1 Updatecheckfile doesn't exist
//2 Updatecheckfile exists, but last check was more not today
if (((!file_exists("data/settings/update_lastcheck.php"))) || ((file_exists("data/settings/update_lastcheck.php")) && ($lastcheck != $dayofyear))) {

//Iniate CURL to fetch update-info,
//but only if CURL-extension is loaded
if (extension_loaded("curl")) {
	$geturl = curl_init();
	$timeout = 10;
	curl_setopt ($geturl, CURLOPT_URL, "http://www.pluck-cms.org/update.php?version=$pluck_version");
	curl_setopt ($geturl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($geturl, CURLOPT_CONNECTTIMEOUT, $timeout);
	$update_available = curl_exec($geturl);
	curl_close($geturl);
}

//If CURL isn't loaded, save an error-status
else {
	$update_available = "error";
}

$data1 = "data/settings/update_lastcheck.php";
$file = fopen($data1, "w");
fputs($file, "<?php
\$lastcheck = \"$dayofyear\";
\$lastupdatestatus = \"$update_available\";
?>");
fclose($file);
}

//If update-file exists and we already checked for updates today, use old updatecheck result
elseif ((file_exists("data/settings/update_lastcheck.php")) && ($lastcheck == $dayofyear)) {
	$update_available = $lastupdatestatus;
}

//Then determine which icon we need to show... and show it
if ($update_available == "yes") {
	$update_image = "update-available.png";
	$update_note = "<a href=\"http://www.pluck-cms.org/cmsupdate.php?versie=$pluck_version\" target=\"_blank\">$lang_update2</a>";
}

elseif ($update_available == "urgent") {
	$update_image = "update-available-urgent.png";
	$update_note = "<a href=\"http://www.pluck-cms.org/cmsupdate.php?versie=$pluck_version\" target=\"_blank\">$lang_update3</a>";
}

elseif ($update_available == "error") {
	$update_image = "error.png";
	$update_note = "<a href=\"http://www.pluck-cms.org/docs/doku.php/docs:updatecheckfailed\" target=\"_blank\">$lang_update4</a>";
}

else {
	$update_image = "update-no.png";
	$update_note = "$lang_update1";
}


echo "<table>
<tr>
<td><img src=\"data/image/$update_image\" border=\"0\" align=\"right\"></td>
<td>$update_note</td>
</tr>
</table>";
?>