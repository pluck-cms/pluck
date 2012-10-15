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

function viewsite_info() {
	global $lang;
	return array(
		'name'          => $lang['viewsite']['module_name'],
		'intro'         => $lang['viewsite']['module_intro'],
		'version'       => '0.1',
		'author'        => $lang['general']['pluck_dev_team'],
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => '../../image/website.png',
		'compatibility' => '4.7'
	);
}

function viewsite_admin_menu($links) {
	global $lang;

	$data[] = array(
		'href' => SITE_URL.'/',
		'img'  => 'data/modules/viewsite/images/viewsite.png',
		'text' => $lang['viewsite']['message'],
		'target' => '_blank'
	);

	$links = module_insert_at_position($links, $data, 1);
}

function viewsite_admin_page_list_before($file) {
	global $lang; ?>
	<span>
		<a href="<?php echo SITE_URL.'/'.PAGE_URL_PREFIX.$file; ?>" target="_blank">
			<img src="data/image/website.png" title="<?php echo $lang['page']['view']; ?>" alt="<?php echo $lang['page']['view']; ?>" />
		</a>
	</span>
<?php
}
?>