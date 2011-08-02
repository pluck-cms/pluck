<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) pluck team
 * http://www.pluck-cms.org

 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');

if (isset($_GET['file'])) {
	if (!empty($_GET['file']) && get_page_filename($_GET['file']) != false) {
		/**
		* Defines the filename of the current page. NOTE: is only defined if the requested page exists.
		*/
		define('CURRENT_PAGE_FILENAME', get_page_filename($_GET['file']));
	}
	/**
	* Defines the seoname of the requested page. NOTE: is also defined if the requested page does not exist.
	*/
	define('CURRENT_PAGE_SEONAME', $_GET['file']);
}

//Name of directory of current module.
if (isset($_GET['module']))
	define('CURRENT_MODULE_DIR', $_GET['module']);

//Name of current module page.
if (isset($_GET['page']))
	define('CURRENT_MODULE_PAGE', $_GET['page']);

//Page title.
define('PAGE_TITLE', get_pagetitle());
?>