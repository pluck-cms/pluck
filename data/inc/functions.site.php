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

//Function: get page title
//---------------------------------
function get_pagetitle() {
	global $lang_front1;
	//Get the title if we are looking at a normal page
	if ((isset($_GET['file'])) && (!empty($_GET['file']))) {
		if (isset($_GET ['file']))
			$filetoread = $_GET ['file'];

		//Check if page exists
		if (file_exists('data/settings/pages/'.$filetoread)) {
			include ('data/settings/pages/'.$filetoread);
			return $title;
		}

		//If page doesn't exist; display error
		else {
			return $lang_front1;
		}
	}

	//Get the title if we are looking at a module page
	if (isset($_GET ['module']))
		$module = $_GET ['module'];

	if (isset($_GET ['page']))
		$page = $_GET ['page'];

	if (isset($module)) {
		//Include module files (but only if they exist)
 		if (file_exists('data/modules/'.$module.'/module_info.php')) {
 			include ('data/modules/'.$module.'/module_info.php');
 			if (module_is_compatible($module)) {
				if (file_exists('data/modules/'.$module.'/module_pages_site.php')) {
 					include ('data/modules/'.$module.'/module_pages_site.php');

	 				//Include all module-pages, but
					//only include pages if array has been given
					if (isset($module_page)) {
	 					foreach ($module_page as $filename => $pagetitle) {
 							//Generate filename with extension
							$filename_ext = $filename.'.php';
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

//FUNCTIONS FOR FILLING IN THE PAGE
//---------------------------------
//---------------------------------

//[THEME] FUNCTION TO INCLUDE META-DATA IN THE PAGE
//---------------------------------
function theme_meta() {
	//Get page-info (for meta-information)
	if (defined('CURRENT_PAGE_FILENAME')) {
		if (file_exists('data/settings/pages/'.CURRENT_PAGE_FILENAME)) {
			include ('data/settings/pages/'.CURRENT_PAGE_FILENAME);
		}
	}

	//Check which CSS-file we need to use (LTR or RTL)
	if ((isset($direction)) && ($direction == 'rtl')) {
		$cssfile = THEME_DIR.'/style-rtl.css';
	}
	else {
		$cssfile = THEME_DIR.'/style.css';
	}

	echo '<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />'."\n";
	echo '<meta name="generator" content="pluck '.PLUCK_VERSION.'" />'."\n";
	echo '<title>'.PAGE_TITLE.' - '.SITE_TITLE.'</title>'."\n";
	echo '<link href="'.$cssfile.'" rel="stylesheet" type="text/css" media="screen" />'."\n";
	echo '<meta name="language" content="'.LANG.'" />'."\n";

	//If we are not looking at a module: include metatag information
	if (defined('CURRENT_PAGE_FILENAME') && file_exists('data/settings/pages/'.CURRENT_PAGE_FILENAME)) {
		echo '<meta name="title" content="'.PAGE_TITLE.'" />'."\n";
		if (isset($keywords) && !empty($keywords)) {
			echo '<meta name="keywords" content="'.$keywords.'" />'."\n";
		}
		if (isset($description) && !empty($description)) {
			echo '<meta name="description" content="'.$description.'" />'."\n";
		}
	}

	//If RTL, set direction to RTL in CSS
	if ((isset($direction)) && ($direction == 'rtl')) {
		echo '<style type="text/css">body {direction:rtl;}</style>'."\n";
	}

	//Also include module head-inclusion files (inc_site_head.php)
	//--------------
	//Open the folder
	$dir_handle = @opendir('data/modules') or die('Unable to open module directory. Check if it\'s readable.');

	//Loop through dirs
	while ($dir = readdir($dir_handle)) {
			if ($dir == '.' || $dir == '..') {
			continue;
			//Include the inc_site.php if it exists, and if module is compatible
			include ('data/modules/'.$dir.'/module_info.php');
			if (module_is_compatible($dir)) {
				if (file_exists('data/modules/'.$dir.'/inc_site_head.php')) {
					include ('data/modules/'.$dir.'/inc_site_head.php');
				}
			}
		}
	}
	//Close module-dir
	closedir($dir_handle);
}

//[THEME] FUNCTION TO SHOW SITE TITLE
//---------------------------------
function theme_sitetitle() {
	echo SITE_TITLE;
}

//[THEME] FUNCTION TO SHOW THE MENU
//---------------------------------
function theme_menu($html,$htmlactive = NULL) {
	$files = read_dir_contents('data/settings/pages', 'files');
	if ($files) {
		//Sort the array
		natcasesort($files);

		foreach ($files as $file) {
			if (isset($_GET['file'])) {
				$currentpage = $_GET['file'];
			}
			include ('data/settings/pages/'.$file);

			//Only display in menu if page isn't hidden by user
			if (isset($hidden) && $hidden == 'no') {
				//Check if we need to show an active link
				if (isset($currentpage) && $currentpage == $file && $htmlactive) {
					$html_new = str_replace('#title', $title, $htmlactive);
					$html_new = str_replace('#file', '?file='.$file, $html_new);
					echo $html_new;
				}
				else {
					$html_new = str_replace('#title', $title, $html);
					$html_new = str_replace('#file', '?file='.$file, $html_new);
	    			echo $html_new;
	    		}
	    	}
		}
	}
}

//[THEME] FUNCTION TO SHOW PAGE TITLE
//---------------------------------
function theme_pagetitle() {
	echo PAGE_TITLE;
}

//[THEME] FUNCTION TO SHOW PAGE CONTENTS
//---------------------------------
function theme_content() {
	//Get needed variables
	global $lang_front2;
	//Get the contents only if we are looking at a normal page
	if (defined('CURRENT_PAGE_FILENAME')) {
		//Check if page exists
		if (file_exists('data/settings/pages/'.CURRENT_PAGE_FILENAME)) {
			include ('data/settings/pages/'.CURRENT_PAGE_FILENAME);
			echo $content;
		}
		//If page doesn't exist, show error message
		else {
			echo $lang_front2;
		}
	}
}

//[THEME] FUNCTION TO INCLUDE MODULES
//---------------------------------
function theme_module($place) {
	//Include needed variables
	global $lang_modules27;

	//If mainspace: include the page-specific modules
	if ($place == 'main') {
		//If we are looking at a normal page: include the inclusion file of the module (but only if specified page exists)
		if (!defined('CURRENT_MODULE_DIR') && defined('CURRENT_PAGE_FILENAME') && file_exists('data/settings/pages/'.CURRENT_PAGE_FILENAME)) {
			//Include page-information
			include ('data/settings/pages/'.CURRENT_PAGE_FILENAME);
			//First, check if we want to include any modules
			if (isset($module_pageinc)) {
				//Let's make sure that the modules are dislayed in the right order
				natcasesort($module_pageinc);

				foreach ($module_pageinc as $module_to_include => $order) {
					//Check if module is set to be displayed, and make sure module exists
					if ($order != 0 && file_exists('data/modules/'.$module_to_include.'/module_info.php')) {
						//Include module information
						include ('data/modules/'.$module_to_include.'/module_info.php');
						//Check if module is compatible
						if (module_is_compatible($module_to_include)) {
							//Check if module wants to insert pages
							if (file_exists('data/modules/'.$module_to_include.'/module_pages_site.php')) {
								include ('data/modules/'.$module_to_include.'/module_pages_site.php');
								//Include the file for the "main" module area
								include ('data/modules/'.$module_to_include.'/pages_site/'.$includepage);
							}
						}
					}
				}
			}
		}
		//If we are looking at a module-page: include that page
		elseif (defined('CURRENT_MODULE_DIR')) {
			//Include module files (but only if they exist)
			if (file_exists('data/modules/'.CURRENT_MODULE_DIR.'/module_info.php')) {
				include ('data/modules/'.CURRENT_MODULE_DIR.'/module_info.php');
				if (module_is_compatible(CURRENT_MODULE_DIR)) {
					if (file_exists('data/modules/'.CURRENT_MODULE_DIR.'/module_pages_site.php')) {
						include ('data/modules/'.CURRENT_MODULE_DIR.'/module_pages_site.php');

						//Only include pages if array has been given
						if (isset($module_page)) {
							//Loop through module-pages
							foreach ($module_page as $filename => $pagetitle) {
								//And include them
								if (CURRENT_MODULE_DIR == $module_dir && CURRENT_MODULE_PAGE == $filename) {
									include ('data/modules/'.$module_dir.'/pages_site/'.$filename.'.php');
								}
							}
						}
					}
				}
				//If module is not compatible
				else {
					echo $lang_modules27;
				}
			}
		}
	}

	//Include the other modules
	//Include info of theme (to see which modules we should include etc), but only if file exists
	if (file_exists('data/settings/themes/'.THEME.'/moduleconf.php')) {
		include ('data/settings/themes/'.THEME.'/moduleconf.php');

		//Get the array and sort it
		foreach ($space as $area => $number) {

			//Sort the array, so that the modules will be displayed in correct order
			natcasesort($number);

			//Get final variables
			foreach ($number as $module => $order) {
				//If the area where the module should be displayed is the same as the area we're currently...
				//...processing: include the module
				if (($area == $place) && ($order != 0)) {
					//Check if module wants to insert pages
					if (file_exists('data/modules/'.$module.'/module_pages_site.php')) {
						if (module_is_compatible($module)) {
							include ('data/modules/'.$module.'/module_pages_site.php');

							//...and include the module
							include ('data/modules/'.$module.'/pages_site/'.$includepage);
						}
					}
				}
			}
		}
	}
}
?>