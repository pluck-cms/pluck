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

//Check if page exists.
if (file_exists('data/settings/pages/'.get_page_filename($var1))) {
	$pages = read_dir_contents('data/trash/pages', 'files');

	//If there are pages in trash, check if there's one with the same name.
	if ($pages != false) {
		foreach ($pages as $page) {
			if ($page ==  $var1.'.php') {
				show_error($lang['trashcan']['same_name'], 1);
				redirect('?action=page', 2);
				include_once('data/inc/footer.php');
				exit;
			}
		}
	}

	//Move the file.
	rename('data/settings/pages/'.get_page_filename($var1), 'data/trash/pages/'.$var1.'.php');

	//Reorder the pages
	reorder_pages('data/settings/pages');

	//Show message.
	show_error($lang['trashcan']['moving_item'], 3);
}

//Redirect user.
redirect('?action=page', 0);
?>