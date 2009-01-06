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

//Check if page exists.
//FIXME: Would it not be better to use $var1?
if (file_exists('data/settings/pages/'.$var1)) {

	//First check if there isn't an item with the same name in the trashcan.
	if (!file_exists('data/trash/pages/'.$var1)) {

		//Move the page to the trashcan.
		copy('data/settings/pages/'.$var1, 'data/trash/pages/'.$var1);
		chmod('data/trash/pages/'.$var1, 0777);
		unlink('data/settings/pages/'.$var1);
	}

	//If there is an item with the same name in the trashcan.
	else {
		//Now we have to check which filenames we can then use.
		if (file_exists('data/trash/pages/kop1.php')) {
			$i = 2;
			$o = 3;
			while ((file_exists('data/trash/pages/kop'.$i.'.php')) || (file_exists('data/trash/pages/kop'.$o.'.php'))) {
				$i++;
				$o++;
			}
			$newfile = 'data/trash/pages/kop'.$i.'.php';
		}
		else
			$newfile = 'data/trash/pages/kop1.php';

		//Move the file with the new filename.
		copy('data/settings/pages/'.$var1, $newfile);
		chmod($newfile, 0777);
		unlink('data/settings/pages/'.$var1);
	}
}

//Redirect user.
redirect('?action=page',0);
?>