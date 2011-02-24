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

require_once ('data/modules/blog/functions.php');

function blog_info() {
	global $lang;
	return array(
		'name'          => $lang['blog']['title'],
		'intro'         => $lang['blog']['descr'],
		'version'       => '0.1',
		'author'        => 'pluck development team',
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/blog.png',
		'compatibility' => '4.7',
		'categories'    => blog_get_categories(TRUE)
	);
}

function blog_settings_default() {
	return array(
		'allow_reactions'  => true,
		'truncate_posts'  => '500'
	);
}

function blog_admin_module_settings_beforepost() {
	global $lang;
	echo '<span class="kop2">'.$lang['blog']['title'].'</span>
		<table>
			<tr>
				<td><input type="checkbox" name="allow_reactions" id="allow_reactions" value="true" '; if (module_get_setting('blog','allow_reactions') == 'true') { echo 'checked="checked" '; } echo '/></td>
				<td><label for="allow_reactions">&emsp;'.$lang['blog']['allow_reactions'].'</label></td>
			</tr>
			<tr>
				<td><input name="truncate_posts" id="truncate_posts" type="text" size="2" value="'.module_get_setting('blog','truncate_posts').'" /></td>
				<td><label for="truncate_posts">&emsp;'.$lang['blog']['truncate_posts'].'</label></td>
			</tr>
	</table><br />';
}

function blog_admin_module_settings_afterpost() {
	global $lang;

	//truncate_posts should be numeric.
	if (!is_numeric($_POST['truncate_posts'])) {
		return show_error($lang['blog']['truncate_error'], 1, true);
	}

	else {
		//Compose settings array
		$settings = array(
			'allow_reactions'  => $_POST['allow_reactions'],
			'truncate_posts'  => $_POST['truncate_posts']
		);
		//Save settings
		module_save_settings('blog', $settings);
	}
}
?>