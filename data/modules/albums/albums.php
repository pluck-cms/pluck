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

require_once ('data/modules/albums/functions.php');

function albums_info() {
	global $lang;
	return array(
		'name'          => $lang['albums']['title'],
		'intro'         => $lang['albums']['descr'],
		'version'       => '0.2',
		'author'        => $lang['general']['pluck_dev_team'],
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/albums.png',
		'compatibility' => '4.7',
		'categories'    => albums_get_albums(TRUE)
	);
}

function albums_settings_default() {
	return array(
		'resize_image_width'  => '800',
		'resize_thumb_width'  => '200'
	);
}

function albums_admin_module_settings_beforepost() {
	global $lang;
	echo '<span class="kop2">'.$lang['albums']['title'].'</span>
		<table>
			<tr>
				<td><input name="image_width" id="image_width" type="text" size="2" value="'.module_get_setting('albums','resize_image_width').'" /></td>
				<td><label for="image_width">&emsp;'.$lang['albums']['image_width'].'</label></td>
			</tr>
			<tr>
				<td><input name="thumb_width" id="thumb_width" type="text" size="2" value="'.module_get_setting('albums','resize_thumb_width').'" /></td>
				<td><label for="thumb_width">&emsp;'.$lang['albums']['thumb_width'].'</label></td>
			</tr>
	</table><br />';
}

function albums_admin_module_settings_afterpost() {
	global $lang;

	//Check if posted settings are numeric.
	if (!is_numeric($_POST['image_width']) || !is_numeric($_POST['thumb_width'])) {
		return show_error($lang['albums']['settings_error'], 1, true);
	}

	else {
		//Compose settings array
		$settings = array(
			'resize_image_width'  => $_POST['image_width'],
			'resize_thumb_width'  => $_POST['thumb_width']
		);
		//Save settings
		module_save_settings('albums', $settings);
	}
}
?>