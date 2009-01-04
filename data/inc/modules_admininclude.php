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

//-----------------
//Lets start including the pages of the modules.
//-----------------

//Define path to the module-dir.
$path = 'data/modules';
//Open the folder.
$dir_handle = @opendir($path) or die('Unable to open '.$path.'. Check if it\'s readable.');

//Loop through dirs.
while ($dir = readdir($dir_handle)) {
	if ($dir == '.' || $dir == '..')
		continue;

	if (file_exists('data/modules/'.$dir.'/module_info.php'))
		include ('data/modules/'.$dir.'/module_info.php');

	//Check if module is compatible, otherwise don't include pages.
	if (module_is_compatible($dir)) {

		if (file_exists('data/modules/'.$dir.'/module_pages_admin.php')) {
			include ('data/modules/'.$dir.'/module_pages_admin.php');

			//Include startpage of module.
			if (isset($module) && $module == $module_dir && !isset($page)) {
				$titelkop = $module_name;
				include_once ('data/inc/header.php');
				include ('data/modules/'.$module_dir.'/pages_admin/'.$startpage);
			}

			//Include other module-pages,
			//but only include pages if array has been given.
			if (isset($module_page) && isset($page)) {
				foreach ($module_page as $filename => $pagetitle) {
					//Generate filename with extension
					$filename_ext = $filename.'.php';
					if (isset($module) && $module == $module_dir && $page == $filename) {
						$titelkop = $pagetitle;
						include_once ('data/inc/header.php');
						include ('data/modules/'.$module_dir.'/pages_admin/'.$filename_ext);
					}
				}
			}
		}
	}

	//If module is not compatible
	elseif (!module_is_compatible($dir) && $module == $dir) {
		$titelkop = $module_name;
		include_once ('data/inc/header.php');
		echo $lang_modules27;
	}

	//Also include the module-include file (if it exists).
	if (file_exists('data/modules/'.$dir.'/inc_admin.php') && module_is_compatible($dir))
		include ('data/modules/'.$dir.'/inc_admin.php');
}

//Close module-dir
closedir($dir_handle);
?>