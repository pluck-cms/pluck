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
defined('IN_PLUCK') or exit('Access denied!');

//Check if page exists.
if (file_exists('data/settings/pages/'.get_page_filename($var1))) {
	$pages = read_dir_contents('data/trash/pages', 'files');

	//Is it a sub-page we want to delete?
	if (strpos($var1, '/') !== false)
		$newfile = str_replace(get_sub_page_dir($var1).'/', '', $var1);
	else
		$newfile = $var1;

	//If there are pages in trash, check if there's one with the same name.
	if ($pages) {
		foreach ($pages as $page) {
			if ($page ==  $newfile.'.php') {
				show_error($lang['trashcan']['same_name'], 1);
				redirect('?action=page', 2);
				include_once('data/inc/footer.php');
				exit;
			}
		}
		unset($page);
	}

	//Are there any sub-pages?
	if (is_dir('data/settings/pages/'.$var1) && read_dir_contents('data/settings/pages/'.$var1, 'files') == true) {
		//Find the sub-pages.
		foreach (get_pages() as $page) {
			if (strpos($page, $var1.'/') !== false)
				$sub_pages[] = $page;
		}
		unset($page);

		//If there are pages in trash, check if there's just one with the same name as one of the sub-pages.
		if ($pages) {
			foreach ($sub_pages as $sub_page) {
				foreach ($pages as $page) {
					if ($page ==  str_replace(get_sub_page_dir(get_page_seoname($sub_page)).'/', '', get_page_seoname($sub_page)).'.php') {
						show_error($lang['trashcan']['same_name'], 1);
						redirect('?action=page', 2);
						include_once('data/inc/footer.php');
						exit;
					}
				}
				unset($page);
			}
		}
	}

	//Move the file.
	rename('data/settings/pages/'.get_page_filename($var1), 'data/trash/pages/'.$newfile.'.php');

	//Then move the sub-pages, if any.
	if (isset($sub_pages)) {
		foreach ($sub_pages as $sub_page)
			rename('data/settings/pages/'.$sub_page, 'data/trash/pages/'.str_replace(get_sub_page_dir(get_page_seoname($sub_page)).'/', '', get_page_seoname($sub_page)).'.php');

		//Delete the dir where the sub-pages were in.
		rmdir('data/settings/pages/'.$var1);
	}

	//If it's a sub-page, we have to do a few things.
	if (strpos($var1, '/') !== false) {
		//Delete the dir, if there are no other pages.
		if (read_dir_contents('data/settings/pages/'.get_sub_page_dir($var1), 'files') == false)
			rmdir('data/settings/pages/'.get_sub_page_dir($var1));

		//Else, just reorder the pages in the sub-dir.
		else
			reorder_pages('data/settings/pages/'.get_sub_page_dir($var1));
	}

	//Reorder the pages
	else
		reorder_pages('data/settings/pages');

	//Show message.
	show_error($lang['trashcan']['moving_item'], 3);
}

//Redirect user.
redirect('?action=page', 0);
?>