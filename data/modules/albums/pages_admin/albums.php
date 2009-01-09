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
	exit();
}

//First, load the functions.
require_once ('data/modules/albums/functions.php');
?>
	<p>
		<strong><?php echo $lang_albums1; ?></strong>
	</p>
	<span class="kop2"><?php echo $lang_albums2; ?></span>
	<br />
<?php
	read_albums('data/settings/modules/albums');
	//Check if the PHP-gd module is installed on server.
	if (extension_loaded('gd')) {
	?>
		<br /><br />
		<label class="kop2" for="cont1"><?php echo $lang_albums3; ?></label>
		<br />
		<form method="post" action="">
			<span class="kop4"><?php echo $lang_albums4; ?></span>
			<br />
			<input name="cont1" type="text" />
			<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
		</form>
	<?php
	//When form is submitted.
	if (isset($_POST['Submit'])) {
		if (isset($cont1) && file_exists('data/settings/modules/albums/'.$cont1))
			echo '<span class="red">'.$lang_album19.'</span>';

		elseif (isset($cont1)) {
			//Delete unwanted characters.
			$cont1 = str_replace ('"','', $cont1);
			$cont1 = str_replace (' ','', $cont1);
			$cont1 = str_replace ('\'','', $cont1);
			$cont1 = str_replace ('.','', $cont1);
			$cont1 = str_replace ('/','', $cont1);

			//Create and chmod directories.
			mkdir ('data/settings/modules/albums/'.$cont1);
			mkdir ('data/settings/modules/albums/'.$cont1.'/thumb');
			chmod ('data/settings/modules/albums/'.$cont1, 0777);
			chmod ('data/settings/modules/albums/'.$cont1.'/thumb', 0777);
			redirect('?module=albums', 0);
		}
	}
}

//If PHP-gd module is not installed.
elseif (!extension_loaded('gd')) {
?>
	<p class="red"><?php echo $lang_albums16; ?></p>
<?php
}
?>
<p>
	<a href="?action=modules">&lt;&lt;&lt; <?php echo $lang_theme12; ?></a>
</p>