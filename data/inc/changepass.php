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
?>
<?php
if (isset($_POST['submit'])) {
	//Include old password.
	require_once ('data/settings/pass.php');

	//MD5-encrypt posted passwords.
	if (!empty($cont1))
		$cont1 = md5($cont1);

	//Check if the old password entered is correct. If it isn't, do:
	if ($ww != $cont1)
		$error = show_error($lang['changepass']['cant_change'], 1, true);

	elseif (empty($cont2))
		$error = show_error($lang['changepass']['empty'], 1, true);

	elseif ($cont2 != $cont3)
		$error = show_error($lang['changepass']['different'], 1, true);

	//If the old password entered is correct, save it.
	else {
			save_password($cont2);
			show_error($lang['changepass']['changed'], 3);
			redirect('?action=options', 2);
			include_once ('data/inc/footer.php');
			exit;
	}
}
?>
<p>
	<strong><?php echo $lang['changepass']['message']; ?></strong>
</p>
<?php
if (isset($error))
	echo $error;
?>
<form method="post" action="">
	<p>
		<label class="kop2" for="cont1"><?php echo $lang['changepass']['old']; ?></label>
		<br />
		<input name="cont1" id="cont1" type="password"/>
	</p>
	<p>
		<label class="kop2" for="cont2"><?php echo $lang['changepass']['new']; ?></label>
		<br />
		<input name="cont2" id="cont2" type="password" />
	</p>
	<p>
		<label class="kop2" for="cont3"><?php echo $lang['changepass']['repeat']; ?></label>
		<br />
		<input name="cont3" id="cont3" type="password" />
	</p>
	<input type="submit" name="submit" value="<?php echo $lang['general']['save']; ?>" title="<?php echo $lang['general']['save']; ?>" />
	<button type="button" class="cancel" onclick="javascript: window.location='?action=options';" title="<?php echo $lang['general']['cancel']; ?>"><?php echo $lang['general']['cancel']; ?></button>
</form>