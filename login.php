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

//Include security-enhancements
require('data/inc/security.php');
//Include functions
require('data/inc/functions.all.php');
//Include variables
require('data/inc/variables.all.php');

//Include POST/GET data
require('data/inc/post_get.php');

//Check if we've installed pluck
if (!file_exists('data/settings/install.dat')) {
	$titelkop = $lang_login1;
	include('data/inc/header2.php');
	redirect('install.php', '3');
	echo $lang_login2;
	include('data/inc/footer.php');
}

//If pluck is installed:
else {
	require('data/settings/pass.php');

	//Check if we're already logged in
	session_start();
	if (isset($_SESSION['cmssystem_loggedin']) && ($_SESSION['cmssystem_loggedin'] == 'ok')) {
		header('Location: admin.php');
		exit;
	}

	//If password has not yet been sent
	if(!isset($_POST['Submit'])) {
		//Include header-file
		$titelkop = $lang_login1;
		include('data/inc/header2.php');
?>
		<span class="kop2"><?php echo $lang_login3; ?></span><br />
		<form action="login.php" method="post" name="passform">
			<input name="cont1" size="25" type="password" />
			<input type="text" name="bogusField" style="display: none;" />
			<input type="submit" name="Submit" value="<?php echo $lang_login4; ?>" />
		</form>
<?php
		include ('data/inc/footer.php');
	}

	//If password has been sent...
	elseif(isset($_POST['Submit'])) {
		//...first MD5-encrypt password that has been posted
		$pass = md5($cont1);

		//...and is correct:
		if ($pass == $ww) {
			//Save session
			$_SESSION['cmssystem_loggedin'] = 'ok';
			//Display successmessage   
			$titelkop = $lang_login1;
			include('data/inc/header2.php');
			echo $lang_login5;
			redirect('admin.php?action=start', '1');
			include('data/inc/footer.php');
		}

		//---------------
		//...or is NOT correct:
		else {
			$titelkop = $lang_login1;
			include('data/inc/header2.php');
			echo $lang_login6;
			redirect('login.php', '3');
			include('data/inc/footer.php');
		}
	}
}
?>