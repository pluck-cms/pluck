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
if((!ereg("index.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("admin.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("install.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("login.php", $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo "access denied";
    //Block all other code
    exit();
}

//Check if image exists
if (file_exists("images/$deleteimage")) {

	//First check if there isn't an item with the same name in the trashcan
	if (!file_exists("data/trash/images/$deleteimage")) {
		//Move the page to the trashcan
		copy("images/$deleteimage", "data/trash/images/$deleteimage");
		chmod("data/trash/images/$deleteimage", 0777);
		unlink("images/$deleteimage");

		//Redirect user
		echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"0; URL=?action=images\">";
	}

	//If there is an item with the same name in the trashcan: display error
	else {
		echo "$lang_trash4";
	}
}
?>