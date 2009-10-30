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

//Check if file exists.
if (file_exists('data/settings/pages/'.get_page_filename($var1))) {
	$current_page_filename = get_page_filename($var1);

	if (strpos($current_page_filename, '/') !== false) {
		$patch = explode('/', $current_page_filename);
		$count = count($patch);
		$current_page_filename = $patch[$count - 1];
		unset($patch[$count - 1]);
		$patch = implode('/', $patch);
		$patch = '/'.$patch;
	}

	else
		$patch = '';

	$pages = read_dir_contents('data/settings/pages'.$patch, 'files');
	sort($pages, SORT_NUMERIC);

	//Find current page number, and the next page number and filename.
	foreach ($pages as $number => $page) {
		if ($current_page_filename == $page)
			$current_page_number = $number + 1;
		elseif (isset($current_page_number)) {
			$next_page_number = $number + 1;
			$next_page_filename = $page;
		}
	}

	//Check if the page isn't already the last one.
	if (!isset($next_page_number)) {
		show_error($lang['page']['last'], 2);
		redirect('?action=page', 2);
		include_once('data/inc/footer.php');
		exit;
	}

	//Split the filenames, so we can switch numbers.
	$current_page_filename_split = explode('.', $current_page_filename);
	$next_page_filename_split = explode('.', $next_page_filename);

	//Switch the numbers.
	$current_page_filename_new = $next_page_filename_split[0].'.'.$current_page_filename_split[1].'.'.$current_page_filename_split[2];
	$next_page_filename_new = $current_page_filename_split[0].'.'.$next_page_filename_split[1].'.'.$next_page_filename_split[2];

	if (strpos($patch, '/') !== false)
		$patch = ltrim($patch, '/').'/';
	
	//And rename the files.
	rename('data/settings/pages/'.$patch.$current_page_filename, 'data/settings/pages/'.$patch.$current_page_filename_new);
	rename('data/settings/pages/'.$patch.$next_page_filename, 'data/settings/pages/'.$patch.$next_page_filename_new);

	//Display message.
	show_error($lang['general']['changing_rank'], 3);
}
redirect('?action=page', 0);
?>