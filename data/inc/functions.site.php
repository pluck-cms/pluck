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

//Function: get page title
//---------------------------------
function get_pagetitle() {
	//Get the title if we are looking at a normal page
	if (isset($_GET['file'])) {
		$filetoread = $_GET['file'];
		//Check if page exists
		if (file_exists("data/settings/pages/$filetoread")) {
			include("data/settings/pages/$filetoread");
			$pagetitle = $title;
			return $pagetitle;
		}
		//If page doesn't exist
		else {
			//Include Translation data
			include("data/settings/langpref.php");
			include("data/inc/lang/en.php");
			include("data/inc/lang/$langpref");
			$pagetitle = $lang_front1;
			return $pagetitle;
		}
	}

	//Get the title if we are looking at a module page
	if (isset($_GET['module'])) {
		$module = $_GET['module'];
	}
	if (isset($_GET['page'])) {
		$page = $_GET['page'];
	}
	if (isset($module)) {
		//Include module files (but only if they exist)
 		if (file_exists("data/modules/$module/module_info.php")) {
 			include("data/modules/$module/module_info.php");
 			if(module_is_compatible($module)) {
				if (file_exists("data/modules/$module/module_pages_site.php")) {
 					include("data/modules/$module/module_pages_site.php");

	 				//Include all module-pages, but
					//only include pages if array has been given
					if (isset($module_page)) {
	 					foreach ($module_page as $filename => $pagetitle) {
 							//Generate filename with extension
							$filename_ext = "$filename.php";
							if (($module == $module_dir) && ($page == $filename)) {
								return $pagetitle;
							}
						}
					}
				}
			}
		}	
	}
}

?>