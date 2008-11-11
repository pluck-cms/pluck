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

//First set the charset: utf-8.
header('Content-Type:text/html;charset=utf-8');

//Check if pluck has been installed. If not, redirect.
if (!file_exists('data/settings/install.dat')) {
	header('Location: install.php');
	exit;
}

//Include security-enhancements.
require_once ('data/inc/security.php');
//Include functions.
require_once ('data/inc/functions.all.php');
require_once ('data/inc/functions.site.php');
//Include variables.
require_once ('data/inc/variables.all.php');
require_once ('data/inc/variables.site.php');

//Then, if we have a RTL-language and theme hasn't been converted
if ((isset($direction)) && ($direction == 'rtl') && (!file_exists(THEME_DIR.'/style-rtl.css'))) {
	//Convert theme and save CSS
	include_once ('data/inc/themes_convert-rtl.php');
}

//Include module-inclusion files (inc_site.php)
//---------------
//Open the folder
$dir_handle = @opendir('data/modules') or die('Unable to open module directory. Check if it\'s readable.');

//Loop through dirs
while ($dir = readdir($dir_handle)) {
if ($dir == '.' || $dir == '..')
   continue;
	//Include the inc_site.php if it exists, and if module is compatible
	include_once ('data/modules/'.$dir.'/module_info.php');
	if (module_is_compatible($dir)) {
		if (file_exists('data/modules/'.$dir.'/inc_site.php')) {
			include_once ('data/modules/'.$dir.'/inc_site.php');
		}
	}
}

//Close module-dir
closedir($dir_handle);

//Check if a page or module has been specified, if not: redirect to kop1.php
if ((!isset($current_page_filename)) && (!isset($current_module_dir))) {
	header('Location: '.HOME_PAGE);
	exit;
}

//Or if a page has been specified but it's empty
elseif ((isset($current_page_filename)) && (empty($current_page_filename))) {
	header('Location: '.HOME_PAGE);
	exit;
}

//If a module has been specified...
if (isset($current_module_dir)) {
	//check if the module exists
	if (file_exists('data/modules/'.$current_module_dir)) {
		//and check if we also specified a page (if not, redirect)
		if ((isset($current_module_dir)) && (!isset($current_module_page))) {
			header('Location: '.HOME_PAGE);
			exit;
		}

		//if a page has been set, check if it exists (if not, redirect)
		elseif ((isset($current_module_dir)) && (isset($current_module_page))) {
			if (!file_exists('data/modules/'.$current_module_dir.'/pages_site/'.$current_module_page.'.php')) {
				header('Location: '.HOME_PAGE);
				exit;
			}
		}
	}

	//If module doesn't exist, also redirect
	else {
		header('Location: '.HOME_PAGE);
		exit;
	}
}

//NOW, INCLUDE THE PAGE
//---------------------------------
//---------------------------------
include_once (THEME_DIR.'/theme.php');
?>