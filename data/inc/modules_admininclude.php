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

//Loop through dirs.
foreach ($module_list as $dir) {
	//Check if module is compatible, otherwise don't include pages.
	if (module_is_compatible($dir) && function_exists($dir.'_page_admin_list')) {
		$module_info = call_user_func($module.'_info');
		$module_pages = call_user_func($dir.'_page_admin_list');

		//Include startpage of module.
		if ($module == $dir && !isset($page) && isset($module_pages[0])) {
			$titelkop = $module_pages[0]['title'];
			include_once ('data/inc/header.php');
			call_user_func($dir.'_page_admin_'.$module_pages[0]['func']);
		}

		//Include other module-pages,
		//but only include pages if array has been given.
		elseif (isset($module_pages) && isset($page)) {
			foreach ($module_pages as $module_page) {
				if ($module == $dir && $page == $module_page['func'] && function_exists($dir.'_page_admin_'.$module_page['func'])) {
					$titelkop = $module_page['title'];
					include_once ('data/inc/header.php');
					call_user_func($dir.'_page_admin_'.$module_page['func']);
				}
			}
		}
	}

	//If module is not compatible.
	elseif (!module_is_compatible($dir) && $module == $dir) {
		$titelkop = $module_name;
		include_once ('data/inc/header.php');
		echo $lang_modules27;
	}
}
?>