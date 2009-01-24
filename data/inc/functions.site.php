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
	global $lang_front1, $module;
	//Get the title if we are looking at a normal page
	if (isset($_GET['file']) && !empty($_GET['file'])) {
		if (isset($_GET ['file']))
			$filetoread = $_GET ['file'];

		//Check if page exists
		if (file_exists('data/settings/pages/'.$filetoread)) {
			include ('data/settings/pages/'.$filetoread);
			return $title;
		}

		//If page doesn't exist; display error
		else
			return $lang_front1;
	}

	//Get the title if we are looking at a module page
	elseif (isset($module) && module_is_compatible($module)) {
		$module_page_site = call_user_func($module.'_page_site_list');
		foreach ($module_page_site as $module_page) {
			if ($module_page['func'] == CURRENT_MODULE_PAGE)
				return $module_page['title'];
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
		if (file_exists('data/settings/pages/'.CURRENT_PAGE_FILENAME))
			include ('data/settings/pages/'.CURRENT_PAGE_FILENAME);
	}

	//Check which CSS-file we need to use (LTR or RTL)
	if (isset($direction) && $direction == 'rtl')
		$cssfile = THEME_DIR.'/style-rtl.css';
	else
		$cssfile = THEME_DIR.'/style.css';

	echo '<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />'."\n";
	echo '<meta name="generator" content="pluck '.PLUCK_VERSION.'" />'."\n";
	echo '<title>'.PAGE_TITLE.' - '.SITE_TITLE.'</title>'."\n";
	echo '<link href="'.$cssfile.'" rel="stylesheet" type="text/css" media="screen" />'."\n";
	echo '<meta name="language" content="'.LANG.'" />'."\n";

	//If we are not looking at a module: include metatag information
	if (defined('CURRENT_PAGE_FILENAME') && file_exists('data/settings/pages/'.CURRENT_PAGE_FILENAME)) {
		echo '<meta name="title" content="'.PAGE_TITLE.'" />'."\n";
		if (isset($keywords) && !empty($keywords))
			echo '<meta name="keywords" content="'.$keywords.'" />'."\n";
		if (isset($description) && !empty($description))
			echo '<meta name="description" content="'.$description.'" />'."\n";
	}

	//If RTL, set direction to RTL in CSS
	if (isset($direction) && $direction == 'rtl')
		echo '<style type="text/css">body {direction:rtl;}</style>'."\n";

	run_hook('theme_meta');
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
		//Sort the array.
		natcasesort($files);

		foreach ($files as $file) {
			if (isset($_GET['file']))
				$currentpage = $_GET['file'];

			include ('data/settings/pages/'.$file);

			//Only display in menu if page isn't hidden by user.
			if (isset($hidden) && $hidden == 'no') {
				//Check if we need to show an active link.
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
			run_hook('theme_content_before');
			echo $content;
			run_hook('theme_content_after');
		}
		//If page doesn't exist, show error message
		else {
			echo $lang_front2;
		}
	}
}

//[THEME] FUNCTION TO INCLUDE MODULES
//---------------------------------
function theme_area($place) {
	//Include needed variables.
	global $lang_modules27;
	//If mainspace: include the page-specific modules.
	if ($place == 'main') {
		if (defined('CURRENT_PAGE_FILENAME') && file_exists('data/settings/pages/'.CURRENT_PAGE_FILENAME)) {
			//Include page-information.
			include ('data/settings/pages/'.CURRENT_PAGE_FILENAME);
			//First, check if we want to include any modules.
			if (isset($module_pageinc)) {
				//Let's make sure that the modules are dislayed in the right order.
				natcasesort($module_pageinc);
				foreach ($module_pageinc as $module_to_include => $order) {
					//Check if module is compatible, and the function exists.
					if (module_is_compatible($module_to_include) && function_exists($module_to_include.'_theme_main'))
							call_user_func($module_to_include.'_theme_main');
				}
			}
		}

		//If we are looking at a module page.
		elseif (defined('CURRENT_MODULE_DIR')) {
			$module_page_site = call_user_func(CURRENT_MODULE_DIR.'_page_site_list');
			foreach ($module_page_site as $module_page) {
				if ($module_page['func'] == CURRENT_MODULE_PAGE)
					call_user_func(CURRENT_MODULE_DIR.'_page_site_'.$module_page['func']);
			}
		}
	}

	//Include info of theme (to see which modules we should include etc), but only if file exists.
	elseif (file_exists('data/settings/themes/'.THEME.'/moduleconf.php')) {
		include ('data/settings/themes/'.THEME.'/moduleconf.php');

		//Get the array and sort it.
		foreach ($space as $area => $number) {
			//Sort the array, so that the modules will be displayed in correct order.
			natcasesort($number);
			foreach ($number as $module => $order) {
				//If the area where the module should be displayed is the same as the area we're currently...
				//...processing: include the module.
				if ($area == $place) {
					//Check if module is compatible, and the function exists.
					if (module_is_compatible($module) && function_exists($module.'_theme_main'))
							call_user_func($module.'_theme_main');
				}
			}
		}
	}
}
?>