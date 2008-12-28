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

//Check if an image was defined, and if the image exists
if (isset($var1) && file_exists('data/settings/modules/albums/'.$var2.'/'.$var1.'.php')) {
	//Include the image-information
	include_once ('data/settings/modules/albums/'.$var2.'/'.$var1.'.php');

	//Replace html-breaks by real ones
	$info = str_replace('<br />', "\n", $info);
	?>
	<br />
	<form name="form1" method="post" action="">
		<label class="kop2" for="cont1"><?php echo $lang_install17; ?></label>
		<br />
		<input name="cont1" id="cont1" type="text" value="<?php echo $name; ?>" />
		<br /><br />
		<label class="kop2" for="cont2"><?php echo $lang_albums11; ?></label>
		<br />
		<textarea cols="50" rows="5" name="cont2" id="cont2"><?php echo $info; ?></textarea>
		<br /><br />
		<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
		<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?module=albums&amp;page=editalbum&amp;var1=<?php echo $var2; ?>';" />
	</form>
	<?php
	//When the information is posted:
	if (isset($_POST['Submit'])) {
		//Sanitize data.
		$cont1 = sanitize($cont1);
		$cont2 = sanitize($cont2);
		$cont2 = str_replace ("\n",'<br />', $cont2);

		//Then save the imageinformation
		$data = 'data/settings/modules/albums/'.$var2.'/'.$var1.'.php';
		$file = fopen($data, 'w');
		fputs($file, '<?php'."\n"
		.'$name = \''.$cont1.'\';'."\n"
		.'$info = \''.$cont2.'\';'."\n"
		.'?>');
		fclose($file);
		chmod($data, 0777);

		redirect('?module=albums&page=editalbum&var1='.$var2, 0);
	}
}
?>