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

//If we want to restore a page.
//----------------------------
if ($var2 == 'page' && file_exists('data/trash/pages/'.$var1)) {
	//First check if there isn't a page with the same name.
	if (!file_exists('data/settings/pages/'.$var1)) {
		//Move the page to the trashcan.
		copy('data/trash/pages/'.$var1, 'data/settings/pages/'.$var1);
		chmod('data/settings/pages/'.$var1, 0777);
		unlink('data/trash/pages/'.$var1);
	}

	//If there is a page with the same name.
	//DOESN'T NEED CLEANUP, CODE WILL BE REMOVED IN 4.7!
	else {
		//Now we have to check which filenames we can then use.
		if (file_exists("data/settings/pages/kop1.php")) {
			$i = 2;
			$o = 3;
			while (file_exists("data/settings/pages/kop$i.php") || file_exists("data/settings/pages/kop$o.php")) {
				$i = $i+1;
				$o = $o+1;
			}
			$newfile = "data/settings/pages/kop$i.php";
		}
		else {
			$newfile = "data/settings/pages/kop1.php";
		}
		//Move the file with the new filename.
		copy('data/trash/pages/'.$var1, $newfile);
		chmod($newfile, 0777);
		unlink('data/trash/pages/'.$var1);
	}
}

//If we want to restore an image.
//----------------------------
if ($var2 == 'image' && file_exists('data/trash/images/'.$var1)) {
	//First check if there isn't an image with the same name.
	if (!file_exists('images/'.$var1)) {
		copy('data/trash/images/'.$var1, 'images/'.$var1);
		chmod('images/'.$var1, 0777);
		unlink('data/trash/images/'.$var1);
	}

	//If there already is an image with the same name.
	else {
		list($filename, $extension) = explode('.', $var1);
		$filename = $filename.'_copy';
		copy('data/trash/images/'.$var1, 'images/'.$filename.'.'.$extension);
		chmod('images/'.$filename.'.'.$extension, 0777);
		unlink('data/trash/images/'.$var1);
	}
}

//Redirect.
redirect('?action=trashcan', 0);
?>