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

//If we want to restore a page.
if ($var2 == 'page' && file_exists('data/trash/pages/'.$var1.'.php')) {
	$pages = read_dir_contents('data/settings/pages', 'files');

	if (get_page_filename($var1) != false) {
		//TODO: Add text for error
		show_error($lang_trash12, 2);
		redirect('?action=trashcan', 2);
	}

	else {
		if ($pages == false)
			$next_number = 1;
		else
			$next_number = count($pages) + 1;

		rename('data/trash/pages/'.$var1.'.php', 'data/settings/pages/'.$next_number.'.'.$var1.'.php');
		show_error($lang['trashcan']['restoring'], 3);
		redirect('?action=trashcan', 0);
	}
}

//If we want to restore an image.
elseif ($var2 == 'image' && file_exists('data/trash/images/'.$var1)) {
	//First check if there isn't an image with the same name.
	if (!file_exists('images/'.$var1)) {
		copy('data/trash/images/'.$var1, 'images/'.$var1);
		chmod('images/'.$var1, 0777);
		unlink('data/trash/images/'.$var1);
	}

	//If there already is an image with the same name.
	else {
		list($filename, $extension) = explode('.', $var1);
		$filename = $filename.'_copy';
		copy('data/trash/images/'.$var1, 'images/'.$filename.'.'.$extension);
		chmod('images/'.$filename.'.'.$extension, 0777);
		unlink('data/trash/images/'.$var1);
	}
	//Redirect.
	show_error($lang['trashcan']['restoring'], 3);
	redirect('?action=trashcan', 0);
}
?>