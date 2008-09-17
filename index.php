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

//First set the charset: utf-8
header('Content-Type:text/html;charset=utf-8');
//Set our homepage (where we can redirect if a page doesn't exist or something)
$homepage = '?file=kop1.php';

//Check if pluck has been installed. If not, redirect.
if (!file_exists('data/settings/install.dat')) {
	header('Location: install.php');
	exit;
}

//Include security-enhancements
require('data/inc/security.php');
//Include functions
require('data/inc/functions.all.php');
require('data/inc/functions.site.php');
//Include variables
require('data/inc/variables.all.php');
require('data/inc/variables.site.php');
//Include Theme data
require('data/settings/themepref.php');
//Then, if we have a RTL-language and theme hasn't been converted
if ((isset($direction)) && ($direction == 'rtl') && (!file_exists('data/themes/'.$themepref.'/style-rtl.css'))) {
	//Convert theme and save CSS
	include('data/inc/themes_convert-rtl.php');
}

//Include module-inclusion files (inc_site.php)
//---------------
//Open the folder
$dir_handle = @opendir('data/modules') or die('Unable to open module directory. Check if it\'s readable.');

//Loop through dirs
while ($dir = readdir($dir_handle)) {
if($dir == '.' || $dir == '..')
   continue;
	//Include the inc_site.php if it exists, and if module is compatible
	include('data/modules/'.$dir.'/module_info.php');
	if(module_is_compatible($dir)) {
		if(file_exists('data/modules/'.$dir.'/inc_site.php')) {
			include('data/modules/'.$dir.'/inc_site.php');
		}
	}
}
//Close module-dir
closedir($dir_handle);

//Check if a page or module has been specified, if not: redirect to kop1.php
if ((!isset($current_page_filename)) && (!isset($current_module_dir))) {
	header('Location: '.$homepage);
	exit;
}
//Or if a page has been specified but it's empty
elseif ((isset($current_page_filename)) && (empty($current_page_filename))) {
	header('Location: '.$homepage);
	exit;
}

//If a module has been specified...
if (isset($current_module_dir)) {
	//check if the module exists
	if (file_exists('data/modules/'.$current_module_dir)) {
		//and check if we also specified a page (if not, redirect)
		if ((isset($current_module_dir)) && (!isset($current_module_page))) {
			header("Location: $homepage");
			exit;
		}
		//if a page has been set, check if it exists (if not, redirect) 
		elseif((isset($current_module_dir)) && (isset($current_module_page))) {
			if (!file_exists('data/modules/'.$current_module_dir.'/pages_site/'.$current_module_page.'.php')) {
				header("Location: $homepage");
				exit;
			}
		}
	}
	//If module doesn't exist, also redirect
	else {
		header("Location: $homepage");
		exit;
	}
}

//Include Theme data
include('data/settings/themepref.php');
//Set themedir
$themedirectory = 'data/themes/'.$themepref;


//FUNCTIONS FOR FILLING IN THE PAGE
//---------------------------------
//---------------------------------

//[THEME] FUNCTION TO INCLUDE META-DATA IN THE PAGE
//---------------------------------
function theme_meta() {
	//Get themedir
	include('data/settings/themepref.php');
	//Include variables
	require('data/inc/variables.all.php');
	require('data/inc/variables.site.php');

	//Get page-info (for meta-information)
	if(isset($current_page_filename)) {
		if(file_exists('data/settings/pages/'.$current_page_filename)) {
			include('data/settings/pages/'.$current_page_filename);
		}
	}

	//Check which CSS-file we need to use (LTR or RTL)
	if ((isset($direction)) && ($direction == 'rtl')) {
		$cssfile = 'data/themes/'.$themepref.'/style-rtl.css';
	}
	else {
		$cssfile = 'data/themes/'.$themepref.'/style.css';
	}

	echo '<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />'."\n";
	echo '<meta name="generator" content="pluck '.$pluck_version.'" />'."\n";
	echo '<title>'.$page_title.' - '.$site_title.'</title>'."\n";
	echo '<link href="'.$cssfile.'" rel="stylesheet" type="text/css" media="screen" />'."\n";

	//If we are not looking at a module: include metatag information
	if ((isset($current_page_filename)) && (file_exists('data/settings/pages/'.$current_page_filename))) {
		echo '<meta name="title" content="'.$page_title.'" />'."\n";
		if ((isset($keywords)) && (!empty($keywords))) {
			echo '<meta name="keywords" content="'.$keywords.'" />'."\n";
		}
		if ((isset($description)) && (!empty($description))) {
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
		if($dir == '.' || $dir == '..')
   		continue;
		//Include the inc_site.php if it exists, and if module is compatible
		include('data/modules/'.$dir.'/module_info.php');
		if(module_is_compatible($dir)) {
			if(file_exists('data/modules/'.$dir.'/inc_site_head.php')) {
				include('data/modules/'.$dir.'/inc_site_head.php');
			}
		}	
	}
	//Close module-dir
	closedir($dir_handle);
}

//[THEME] FUNCTION TO SHOW SITE TITLE
//---------------------------------
function theme_sitetitle() {
	$site_title = get_sitetitle();
	echo $site_title;
}

//[THEME] FUNCTION TO SHOW THE MENU
//---------------------------------
function theme_menu($html,$htmlactive = NULL) {
	$dir = 'data/settings/pages';
	$path = opendir($dir);
	while (false !== ($file = readdir($path))) {
   	if(($file !== ".") and ($file !== "..")) {
			if(is_file($dir."/".$file))
				$files[]=$file;
			else
				$dirs[]=$dir."/".$file;           
		}
	}
	if($files) {
		//Sort the array
		natcasesort($files);

		foreach ($files as $file) {
			if (isset($_GET['file'])) {
				$currentpage = $_GET['file'];
			}
			include('data/settings/pages/'.$file);

			//Only display in menu if page isn't hidden by user
			if ((isset($hidden)) && ($hidden == 'no')) {
				//Check if we need to show an active link
				if ((isset($currentpage)) && ($currentpage == $file) && ($htmlactive)) {
					$html_new = str_replace("#title", $title, $htmlactive);
					$html_new = str_replace("#file", "?file=$file", $html_new);
	   	 		echo $html_new;
				}
				else {
					$html_new = str_replace("#title", $title, $html);
					$html_new = str_replace("#file", "?file=$file", $html_new);
	    			echo $html_new;
	    		}
	    	}
		}
	}
	closedir($path);
}

//[THEME] FUNCTION TO SHOW PAGE TITLE
//---------------------------------
function theme_pagetitle() {
	$page_title = get_pagetitle();
	echo $page_title;
}

//[THEME] FUNCTION TO SHOW PAGE CONTENTS
//---------------------------------
function theme_content() {
	//Get needed variables
	include('data/inc/variables.all.php');
	include('data/inc/variables.site.php');
	//Get the contents only if we are looking at a normal page
	if (isset($current_page_filename)) {
		//Check if page exists
		if(file_exists('data/settings/pages/'.$current_page_filename)) {
			include('data/settings/pages/'.$current_page_filename);
			echo $content;
		}
		//If page doesn't exist, show error message
		else {
			$content = $lang_front2;
			echo $content;
		}
	}
}

//[THEME] FUNCTION TO INCLUDE MODULES
//---------------------------------
function theme_module($place) {

//Include needed variables
include('data/inc/variables.all.php');
include('data/inc/variables.site.php');

//If mainspace: include the page-specific modules
if($place == 'main') {

	//If we are looking at a normal page: include the inclusion file of the module (but only if specified page exists)
	if((!isset($current_module_dir)) && (isset($current_page_filename)) && (file_exists('data/settings/pages/'.$current_page_filename))) {
		//Include page-information
		include('data/settings/pages/'.$current_page_filename);		
		//First, check if we want to include any modules 
		if(isset($module_pageinc)) {
			//Let's make sure that the modules are dislayed in the right order
			natcasesort($module_pageinc);

			foreach($module_pageinc as $module_to_include => $order) {
				//Check if module is set to be displayed
				if($order != '0') {
					//Include module information
					include('data/modules/'.$module_to_include.'/module_info.php');
					//Check if module is compatible
					if(module_is_compatible($module_to_include)) {
						//Check if module wants to insert pages
						if(file_exists('data/modules/'.$module_to_include.'/module_pages_site.php')) {
							include('data/modules/'.$module_to_include.'/module_pages_site.php');
							//Include the file for the "main" module area
							include('data/modules/'.$module_to_include.'/pages_site/'.$includepage);
						}
					}
				}
 			}
 		}
 	}
 	//If we are looking at a module-page: include that page
 	elseif(isset($current_module_dir)) {
 		//Include module files (but only if they exist)
 		if(file_exists('data/modules/'.$current_module_dir.'/module_info.php')) {
 			include('data/modules/'.$current_module_dir.'/module_info.php');
 			if(module_is_compatible($current_module_dir)) {
				if(file_exists('data/modules/'.$current_module_dir.'/module_pages_site.php')) {
 					include('data/modules/'.$current_module_dir.'/module_pages_site.php');

					//Only include pages if array has been given
					if(isset($module_page)) {
			 			//Loop through module-pages
 						foreach($module_page as $filename => $pagetitle) {
 							//And include them
							if(($current_module_dir == $module_dir) && ($current_module_page == $filename)) {
								include('data/modules/'.$module_dir.'/pages_site/'.$filename.'.php');
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
//Include theme data
include('data/settings/themepref.php');

	//Include info of theme (to see which modules we should include etc), but only if file exists
	if(file_exists('data/settings/themes/'.$themepref.'/moduleconf.php')) {
		include('data/settings/themes/'.$themepref.'/moduleconf.php');

		//Get the array and sort it
		foreach($space as $area => $number) {

		//Sort the array, so that the modules will be displayed in correct order
		natcasesort($number);

			//Get final variables
			foreach($number as $module => $order) {

				//If the area where the module should be displayed is the same as the area we're currently...
				//...processing: include the module
				if(($area == $place) && ($order != '0')) {
					//Check if module wants to insert pages
					if(file_exists('data/modules/'.$module.'/module_pages_site.php')) {
						if(module_is_compatible($module)) {
							include('data/modules/'.$module.'/module_pages_site.php'); 
							//...and include the module
							include('data/modules/'.$module.'/pages_site/'.$includepage);
						}
					}
				}
			}
		}
	}
}


//NOW, INCLUDE THE PAGE
//---------------------------------
//---------------------------------
include($themedirectory.'/theme.php');
?>