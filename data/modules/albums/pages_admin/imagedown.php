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

//Check if images exist.
if (file_exists('data/settings/modules/albums/'.$var2.'/'.$var1.'.jpg') && file_exists('data/settings/modules/albums/'.$var2.'/'.$var1.'.php') && file_exists('data/settings/modules/albums/'.$var2.'/thumb/'.$var1.'.jpg')) {
	//Determine the imagenumber
	list($filename, $pagenumber) = explode('e', $var1);
	$lowerpagenumber = $pagenumber + 1;

	//We can't lower the last image, so we have to check:
	if (!file_exists('data/settings/modules/albums/'.$var2.'/'.NAME.$lowerpagenumber.'.jpg')) {
		echo $lang_updown7;
		redirect('?module=albums&page=editalbum&var1='.$var2, 2);
		include_once ('data/inc/footer.php');
		exit;
	}

	//First make temporary files.
	rename('data/settings/modules/albums/'.$var2.'/'.$var1.'.jpg', 'data/settings/modules/albums/'.$var2.'/'.$var1.TEMP.'.jpg');
	rename('data/settings/modules/albums/'.$var2.'/'.$var1.'.php', 'data/settings/modules/albums/'.$var2.'/'.$var1.TEMP.'.php');
	rename('data/settings/modules/albums/'.$var2.'/thumb/'.$var1.'.jpg', 'data/settings/modules/albums/'.$var2.'/thumb/'.$var1.TEMP.'.jpg');

	//Then make the higher images one higher.
	rename('data/settings/modules/albums/'.$var2.'/'.NAME.$lowerpagenumber.'.jpg', 'data/settings/modules/albums/'.$var2.'/'.$var1.'.jpg');
	rename('data/settings/modules/albums/'.$var2.'/'.NAME.$lowerpagenumber.'.php', 'data/settings/modules/albums/'.$var2.'/'.$var1.'.php');
	rename('data/settings/modules/albums/'.$var2.'/thumb/'.NAME.$lowerpagenumber.'.jpg', 'data/settings/modules/albums/'.$var2.'/thumb/'.$var1.'.jpg');

	//Finally, give the temp-files its final name.
	rename ('data/settings/modules/albums/'.$var2.'/'.$var1.TEMP.'.jpg', 'data/settings/modules/albums/'.$var2.'/'.NAME.$lowerpagenumber.'.jpg');
	rename ('data/settings/modules/albums/'.$var2.'/'.$var1.TEMP.'.php', 'data/settings/modules/albums/'.$var2.'/'.NAME.$lowerpagenumber.'.php');
	rename ('data/settings/modules/albums/'.$var2.'/thumb/'.$var1.TEMP.'.jpg', 'data/settings/modules/albums/'.$var2.'/thumb/'.NAME.$lowerpagenumber.'.jpg');

	//Show message.
	echo $lang_updown3;
}

//Redirect.
redirect('?module=albums&page=editalbum&var1='.$var2, 0);
?>