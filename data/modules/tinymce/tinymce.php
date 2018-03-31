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

//Define dir constant
define('TINYMCE_DIR', 'data/modules/tinymce');
//Define constant to add tinymce-class to textareas
define('WYSIWYG_TEXTAREA_CLASS', 'tinymce');

//Require functions
require_once ('data/modules/tinymce/functions.php');

function tinymce_info() {
	global $lang;
	return array(
		'name'          => $lang['tinymce']['module_name'],
		'intro'         => $lang['tinymce']['module_intro'],
		'version'       => '4.7.9',
		'author'        => $lang['general']['pluck_dev_team'],
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/tinymce.png',
		'compatibility' => '4.7'
	);
}

function tinymce_admin_head_main() {
	//Display main code.
	tinymce_display_code(); 
}
?>
