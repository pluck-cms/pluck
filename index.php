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

//Define that we are in pluck.
define('IN_PLUCK', true);

//Then start session support.
session_start();

//Check if pluck has been installed. If not, redirect.
if (!file_exists('data/settings/install.dat')) {
	header('Location: install.php');
	exit;
}

//Include security-enhancements.
require_once ('data/inc/security.php');
//Include functions.
require_once ('data/inc/functions.modules.php');
require_once ('data/inc/functions.all.php');
require_once ('data/inc/functions.site.php');
//Include variables.
require_once ('data/inc/variables.all.php');
require_once ('data/inc/variables.site.php');

run_hook('site_index');

//Then, if we have a RTL-language and theme hasn't been converted.
if (DIRECTION_RTL && !file_exists(THEME_DIR.'/style-rtl.css')) {
	//Convert theme and save CSS.
	include_once ('data/inc/themes_convert-rtl.php');
}

//Check if a page or module has been specified, if not: redirect to HOME_PAGE.
if (!defined('CURRENT_PAGE_SEONAME') && !defined('CURRENT_MODULE_DIR')) {
	header('Location: '.HOME_PAGE);
	exit;
}

//If a module has been specified...
if (defined('CURRENT_MODULE_DIR')) {
	//Check if the module exists.
	if (file_exists('data/modules/'.CURRENT_MODULE_DIR)) {
		//And check if we also specified a page (if not, redirect).
		if (defined('CURRENT_MODULE_DIR') && !defined('CURRENT_MODULE_PAGE')) {
			header('Location: '.HOME_PAGE);
			exit;
		}

		//If a page has been set, check if it exists (if not, redirect).
		elseif (defined('CURRENT_MODULE_DIR') && defined('CURRENT_MODULE_PAGE')) {
			if (!function_exists(CURRENT_MODULE_DIR.'_page_site_'.CURRENT_MODULE_PAGE)) {
				header('Location: '.HOME_PAGE);
				exit;
			}
		}
	}

	//If module doesn't exist, also redirect.
	else {
		header('Location: '.HOME_PAGE);
		exit;
	}
}

//Allow modules to manipulate theme
$page_theme = THEME;
run_hook('site_theme', array(&$page_theme));

//Allow modules to manipulate theme-filename
$page_theme_file = 'theme';
run_hook('site_theme_file', array(&$page_theme_file));

//Now, include the page.
include_once('data/themes/'.$page_theme.'/'.$page_theme_file.'.php');
?>