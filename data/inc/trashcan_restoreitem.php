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

//If we want to restore a page.
if ($var2 == 'page' && file_exists('data/trash/pages/'.$var1.'.php')) {

	//We can't restore the page if there is a page with the same name.
	if (get_page_filename($var1) != false) {
		show_error($lang['trashcan']['same_page_name'], 1);
		redirect('?action=trashcan', 3);
	}

	else {
		$pages = read_dir_contents(PAGE_DIR, 'files');

		if ($pages == false)
			$next_number = 1;
		else
			$next_number = count($pages) + 1;

		rename('data/trash/pages/'.$var1.'.php', PAGE_DIR.'/'.$next_number.'.'.$var1.'.php');

		//Redirect.
		show_error($lang['trashcan']['restoring'], 3);
		redirect('?action=trashcan', 1);
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
	redirect('?action=trashcan', 1);
}
?>