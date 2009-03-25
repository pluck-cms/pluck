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
	exit;
}

//Constants for module programmers
//Variables are included for compatibility with pluck 4.6
//----------------
//Filename of current page
if (isset($_GET['file']) && !empty($_GET['file'])) {
	if (get_page_filename($_GET['file']) != false)
		define('CURRENT_PAGE_FILENAME', get_page_filename($_GET['file']));
	define('CURRENT_PAGE_SEONAME', $_GET['file']);
}

//Name of directory of current module
if (isset($_GET['module']))
	define('CURRENT_MODULE_DIR', $_GET['module']);

//Name of current module page
if (isset($_GET['page']))
	define('CURRENT_MODULE_PAGE', $_GET['page']);

//Page title
define('PAGE_TITLE', get_pagetitle()); //Also works for modules
?>