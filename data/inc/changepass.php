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
if((!ereg('index.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo 'access denied';
    //Block all other code
    exit();
}

//Introduction text
?>
<p><strong><?php echo $lang_cpass1; ?></strong></p>

<form method="post" action="">
	<span class="kop2"><?php echo $lang_cpass2; ?></span><br />
	<input name="passoud" type="password"/><br /><br />
	<span class="kop2"><?php echo $lang_cpass3; ?></span><br />
	<input name="pass" type="password" /><br /><br />
	<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
	<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=options';" />
</form>
<?php
if(isset($_POST['Submit'])) {

	//Include old password
	require('data/settings/pass.php');

	//MD5-encrypt posted passwords
	$passoud = md5($passoud);
	$pass = md5($pass);

	//Check if the old password entered is correct. If it isn't, do:
	if ($ww != $passoud) {
		?>
		<span class="red"><?php echo $lang_cpass4; ?></span>
		<?php
	}

	//If the old password entered is correct, save it
	else {
		$data = 'data/settings/pass.php';
		$file = fopen($data, 'w');
		fputs($file, '<?php $ww = "'.$pass.'"; ?>');
		fclose($file);
		chmod($data,0777);

		//Redirect user
		echo $lang_cpass5;
		redirect('?action=options','0');
	}
}
?>