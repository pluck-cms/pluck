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

//Make sure the file isn't accessed directly.
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit;
}

//First get the day of the year
$dayofyear = date('z');

//If the updatecheckfile existst, include it.
if (file_exists('data/settings/update_lastcheck.php'))
	include_once ('data/settings/update_lastcheck.php');

//We want to check for updates if:
//1 Updatecheckfile doesn't exist
//2 Updatecheckfile exists, but last check was not today
if (!file_exists('data/settings/update_lastcheck.php') || ((file_exists('data/settings/update_lastcheck.php') && $lastcheck != $dayofyear))) {
	//Iniate CURL to fetch update-info,
	//but only if CURL-extension is loaded
	if (extension_loaded('curl')) {
		$geturl = curl_init();
		$timeout = 10;
		curl_setopt ($geturl, CURLOPT_URL, 'http://www.pluck-cms.org/update.php?version='.PLUCK_VERSION);
		curl_setopt ($geturl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($geturl, CURLOPT_CONNECTTIMEOUT, $timeout);
		$update_available = curl_exec($geturl);
		curl_close($geturl);
	}

	//If CURL isn't loaded, save an error-status
	else
		$update_available = 'error';

	$data = 'data/settings/update_lastcheck.php';
	$file = fopen($data, 'w');
	fputs($file, '<?php'."\n"
	.'$lastcheck = "'.$dayofyear.'";'."\n"
	.'$lastupdatestatus = "'.$update_available.'";'."\n"
	.'?>');
	fclose($file);
	chmod($data, 0777);
}

//If update-file exists and we already checked for updates today, use old updatecheck result
elseif (file_exists('data/settings/update_lastcheck.php') && $lastcheck == $dayofyear)
	$update_available = $lastupdatestatus;

//Then determine which icon we need to show... and show it
if ($update_available == 'yes') {
	$update_note = '<a href="http://www.pluck-cms.org/" target="_blank"><img src="data/image/update-available.png" alt="" /> '.$lang['update']['available'].'</a>';
}

elseif ($update_available == 'urgent') {
	$update_note = '<a href="http://www.pluck-cms.org/" target="_blank"><img src="data/image/update-available-urgent.png" alt="" /> '.$lang['update']['urgent'].'</a>';
}

elseif ($update_available == 'error') {
	$update_note = '<img src="data/image/error.png" alt="" /> '.$lang['update']['failed'];
}

else {
	$update_note = '<img src="data/image/update-no.png" alt="" /> '.$lang['update']['up_to_date'];
}
?>
<div>
	<?php echo $update_note; ?>
</div>
