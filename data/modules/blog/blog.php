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

function blog_info() {
	global $lang;
	return array(
		'name'          => $lang['blog']['title'],
		'intro'         => $lang['blog']['descr'],
		'version'       => '0.1',
		'author'        => 'pluck development team',
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/blog.png',
		'compatibility' => '4.7'
	);
}

function blog_settings_default() {
	return array(
		'allow_reactions'  => true
	);
}

/* <?php if ($xhtmlruleset == 'true') echo 'checked="checked"'; ?> */

function blog_admin_module_settings_beforepost() {
	global $lang;
	echo '<p>
		<span class="kop2">'.$lang['blog']['title'].'</span>
		<input type="checkbox" name="blog_reactions" id="blog_reactions" value="true" '; if (module_get_setting('blog','allow_reactions') == 'true') { echo 'checked="checked" '; } echo '/>
		<label for="blog_reactions">&nbsp;'.$lang['blog']['settings_reactions'].'</label>
	</p>';
}

function blog_admin_module_settings_afterpost() {
	//Compose settings array
	$settings = array(
		'allow_reactions'  => $_POST['blog_reactions']
	);
	//Save settings
	module_save_settings('blog', $settings);
}
?>