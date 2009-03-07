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
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit;
}

//Include functions
include('data/modules/blog/functions.php');

//Check if config file exists
if(file_exists('data/settings/modules/blog/categories.dat')) {
	$categories = file_get_contents('data/settings/modules/blog/categories.dat');

	//Check if category exists in file, and if it has been saved comma seperated or not
	//If category is not last in list:
	if(ereg($var.',',$categories)) {
		$categories = str_replace($var.',','',$categories);
		//Open config file
		$file = fopen('data/settings/modules/blog/categories.dat', 'w');
		//Save categories
		fputs($file,$categories);
		//Close file, and chmod it
		fclose($file);
		chmod('data/settings/modules/blog/categories.dat', 0777);
	}
	//If category is last in list...
	elseif(ereg($var,$categories)) {
		//...but category is not the only one
		if(ereg(','.$var,$categories)) {
			$categories = str_replace(','.$var,'',$categories);
			//Open config file
			$file = fopen('data/settings/modules/blog/categories.dat', 'w');
			//Save categories
			fputs($file,$categories);
			//Close file, and chmod it
			fclose($file);
			chmod('data/settings/modules/blog/categories.dat', 0777);
		}
		//...and category is the only one
		elseif(ereg($var,$categories)) {
			unlink('data/settings/modules/blog/categories.dat');
		}
	}
}

//Redirect
redirect('?module=blog','0');
?>