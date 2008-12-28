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
if (isset($var1) && isset($var2) && file_exists('data/settings/modules/albums/'.$var2.'/'.$var1.'.php') && file_exists('data/settings/modules/albums/'.$var2.'/thumb/'.$var1.'.jpg')) {
	unlink('data/settings/modules/albums/'.$var2.'/'.$var1.'.php');
	unlink('data/settings/modules/albums/'.$var2.'/'.$var1.'.jpg');
	unlink('data/settings/modules/albums/'.$var2.'/thumb/'.$var1.'.jpg');
}

redirect('?module=albums&page=editalbum&var1='.$var2, 0);
?>