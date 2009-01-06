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

//Check if image exists.
if (file_exists('images/'.$var1)) {

	//First check if there isn't an item with the same name in the trashcan.
	if (!file_exists('data/trash/images/'.$var1)) {
		//Move the page to the trashcan.
		copy('images/'.$var1, 'data/trash/images/'.$var1);
		chmod('data/trash/images/'.$var1, 0777);
		unlink('images/'.$var1);

		//Redirect user.
		redirect('?action=images', 0);
	}

	//If there is an item with the same name in the trashcan: display error.
	else {
		echo $lang_trash4;
		redirect('?action=images', 3);
	}
}
?>