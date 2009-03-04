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
<p>
	<strong><?php echo $lang_cpass1; ?></strong>
</p>
<form method="post" action="">
	<label class="kop2" for="cont1"><?php echo $lang_cpass2; ?></label>
	<br />
	<input name="cont1" id="cont1" type="password"/>
	<br /><br />
	<label class="kop2" for="cont2"><?php echo $lang_cpass3; ?></label>
	<br />
	<input name="cont2" id="cont2" type="password" />
	<br /><br />
	<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
	<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=options';" />
</form>
<?php
if (isset($_POST['Submit'])) {
	//Include old password
	require_once ('data/settings/pass.php');

	//MD5-encrypt posted passwords
	if (!empty($cont1))
		$cont1 = md5($cont1);

	//Check if the old password entered is correct. If it isn't, do:
	if ($ww != $cont1) {
	?>
		<span class="red"><?php echo $lang_cpass4; ?></span>
	<?php
	}

	//If the old password entered is correct, save it
	else {
		if (!empty($cont2)) {
			save_password($cont2);
			//Redirect user
			echo $lang_cpass5;
			redirect('?action=options', 2);
		}
	}
}
?>