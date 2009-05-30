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

//Load all the modules, so we can use hooks.
//This has to be done before anything else.
$path = opendir('data/modules');
while (false !== ($dir = readdir($path))) {
	if ($dir != '.' && $dir != '..') {
		if (is_dir('data/modules/'.$dir))
			$modules[] = $dir;
	}
}
closedir($path);

foreach ($modules as $module) {
	if (file_exists('data/modules/'.$module.'/'.$module.'.php')) {
		require_once ('data/modules/'.$module.'/'.$module.'.php');
		$module_list[] = $module;
	}
}
unset($module);

//Include security-enhancements.
require_once ('data/inc/security.php');
//Include functions.
require_once ('data/inc/functions.all.php');
//Include variables.
require_once ('data/inc/variables.all.php');

//Check if we've installed pluck.
if (!file_exists('data/settings/install.dat')) {
	$titelkop = $lang['install']['not'];
	include_once ('data/inc/header2.php');
	redirect('install.php', 3);
	show_error($lang['install']['not_message'], 1);
	include_once('data/inc/footer.php');
}

//If pluck is installed:
else {
	require_once ('data/settings/pass.php');

	//Check if we're already logged in.
	session_start();
	if (isset($_SESSION['cmssystem_loggedin']) && ($_SESSION['cmssystem_loggedin'] == 'ok')) {
		header('Location: admin.php');
		exit;
	}

	//Include header-file.
	$titelkop = $lang['login']['title'];
	include_once ('data/inc/header2.php');

	//If password has been sent, and the bogus input is empty...
	if (isset($_POST['submit']) && empty($_POST['bogus'])) {
		//...first MD5-encrypt password that has been posted.
		$pass = md5($cont1);

		//...and is correct:
		if ($pass == $ww) {
			//Save session.
			$_SESSION['cmssystem_loggedin'] = 'ok';
			//Display success message.
			show_error($lang['login']['correct'], 3);
			redirect('admin.php?action=start', 1);
			include_once ('data/inc/footer.php');
			exit;
		}

		//...or is NOT correct:
		else
			$login_error = show_error($lang['login']['incorrect'], 1, true);
	}
	?>
		<span class="kop2"><?php echo $lang['login']['password']; ?></span><br />
		<form action="login.php" method="post" name="passform">
			<input name="cont1" size="25" type="password" />
			<input type="text" name="bogus" style="display: none;" />
			<input type="submit" name="submit" value="<?php echo $lang['login']['title']; ?>" />
		</form>
	<?php
	if (isset($login_error))
		echo $login_error;

	include_once ('data/inc/footer.php');
}
?>