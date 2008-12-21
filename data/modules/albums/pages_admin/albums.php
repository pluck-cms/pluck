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

//Block any access to files that only need to be included (no direct access)
if((!ereg("index.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("admin.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("install.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("login.php", $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo "access denied";
    //Block all other code
    exit();
}

//First, load the functions
include('data/modules/albums/functions.php');
?>

<p><b><?php echo $lang_albums1; ?></b></p>
<span class="kop2"><?php echo $lang_albums2; ?></span><br />

<?php
	read_albums('data/settings/modules/albums');
?>

<?php
//Check if the PHP-gd module is installed on server
if (extension_loaded('gd')) { ?>

<br /><br /><span class="kop2"><?php echo $lang_albums3; ?></span><br />
<form method="post"><span class="kop4"><?php echo $lang_albums4; ?></span><br />
	<input name="album_name" type="text" value=""> <input type="submit" name="Submit" value="<?php echo $lang_install13; ?>">
</form>

<?php
//When form is submitted
if(isset($_POST['Submit'])) {
	if($album_name) {
		//Delete unwanted characters.
		$album_name = stripslashes($album_name);
		$album_name = str_replace ('"','', $album_name);
		$album_name = str_replace (' ','', $album_name);
		$album_name = str_replace ('\'','', $album_name);
		$album_name = str_replace ('.','', $album_name);
		$album_name = str_replace ('/','', $album_name);
		//Create and chmod directories.
		mkdir ('data/settings/modules/albums/'.$album_name);
		mkdir ('data/settings/modules/albums/'.$album_name.'/thumb');
		chmod ('data/settings/modules/albums/'.$album_name, 0777);
		chmod ('data/settings/modules/albums/'.$album_name.'/thumb', 0777);
		redirect('?module=albums',0);
	}
}
}

//If PHP-gd module is not installed
elseif (!extension_loaded('gd')) { ?>
	<p><span style="color: red;"><?php echo $lang_albums16; ?></span></p>
<?php } ?>

<p><a href="?action=modules"><<< <?php echo $lang_theme12; ?></a></p>