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

function contactform_info() {
	global $lang;
	return array(
		'name'          => $lang['contactform']['module_name'],
		'intro'         => $lang['contactform']['module_intro'],
		'version'       => '0.2',
		'author'        => $lang['general']['pluck_dev_team'],
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/contactform.png',
		'compatibility' => '4.7'
	);
}
?>