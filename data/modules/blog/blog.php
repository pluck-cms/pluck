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

require_once ('data/modules/blog/functions.php');

function blog_info() {
	global $lang;
	return array(
		'name'          => $lang['blog']['title'],
		'intro'         => $lang['blog']['descr'],
		'version'       => '0.2',
		'author'        => $lang['general']['pluck_dev_team'],
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/blog.png',
		'compatibility' => '4.7',
		'categories'    => blog_get_categories(TRUE)
	);
}

function blog_settings_default() {
	return array(
		'allow_reactions'	=> true,
		'truncate_posts'	=> '500',
		'posts_per_page'	=> '15',
		'post_date'	=> 'd/m/Y',
		'post_time'	=> 'G:i'
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
			<tr>
				<td><input name="posts_per_page" id="posts_per_page" type="text" size="2" value="'.module_get_setting('blog','posts_per_page').'" /></td>
				<td><label for="posts_per_page">&emsp;'.$lang['blog']['posts_per_page'].'</label></td>
			</tr>
			<tr>
				<td>
					<select name="post_date" id="post_date" />
						<option value="d/m/Y">'.date('d/m/Y').'</option>
						<option value="m/d/Y">'.date('m/d/Y').'</option>
						<option value="Y/m/d">'.date('Y/m/d').'</option>
						<option value="d.m.Y">'.date('d.m.Y').'</option>
						<option value="d.m.y">'.date('d.m.y').'</option>
						<option value="Y-m-d">'.date('Y-m-d').'</option>
						<option value="D M Y">'.date('D M Y').'</option>
						<option value="d M Y">'.date('d M Y').'</option>
						<option value="F j, Y">'.date('F j, Y').'</option>
					</select>
				</td>
				<td><label for="post_date">&emsp;'.$lang['blog']['post_date'].'</label></td>
			</tr>
			<tr>
				<td>
					<select name="post_time" id="post_time" />
						<option value="G:i">'.date('G:i').'</option>
						<option value="H:i:s">'.date('H:i:s').'</option>
						<option value="g:i a">'.date('g:i a').'</option>
						<option value="g:i A">'.date('g:i A').'</option>
						<option value="g:i:s a">'.date('g:i:s a').'</option>
						<option value="g:i:s A">'.date('g:i:s A').'</option>
					</select>
				</td>
				<td><label for="post_time">&emsp;'.$lang['blog']['post_time'].'</label></td>
			</tr>
	</table><br />';
}

function blog_admin_module_settings_afterpost() {
	global $lang;

	//truncate_posts should be numeric.
	if (!is_numeric($_POST['truncate_posts']) || !is_numeric($_POST['posts_per_page']))
		return show_error($lang['blog']['numeric_error'], 1, true);

	if (empty($_POST['posts_per_page']))
		return show_error($lang['blog']['posts_per_page_error'], 1, true);

	else {
		//Compose settings array
		$settings = array(
			'allow_reactions' => $_POST['allow_reactions'],
			'truncate_posts' => $_POST['truncate_posts'],
			'posts_per_page' => $_POST['posts_per_page'],
			'post_date' => $_POST['post_date'],
			'post_time' => $_POST['post_time']
		);
		//Save settings
		module_save_settings('blog', $settings);
	}
}
?>