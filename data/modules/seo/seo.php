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

function seo_info() {
	global $lang;
	return array(
		'name'          => 'seo',
		'intro'         => 'seo intro',
		'version'       => '0.1',
		'author'        => $lang['general']['pluck_dev_team'],
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => '../../image/themes.png',
		'compatibility' => '4.7'
	);
}

function is_apache_module() {
$result = false;
    if (function_exists('apache_get_modules'))
         $result = in_array('mod_rewrite', apache_get_modules());
    else {
        ob_start();
        phpinfo(INFO_MODULES);
        $contents = ob_get_contents();
        ob_end_clean();
        $result = (strpos($contents, 'mod_rewrite') !== false);
    }
	return ($result && file_exists('.htaccess')) ? true : false;
}

function seo_page_url_prefix($prefix) {

    if (is_apache_module() && basename($_SERVER['PHP_SELF']) != 'admin.php')
		return $prefix = SITE_URL.'/';
	else
		return;
}

function seo_blog_url_prefix($prefix) {
	if (is_apache_module())
		return $prefix = '_blog_';
	else
		return;
}

function seo_album_url_prefix($prefix) {
	if (is_apache_module())
		return $prefix = '_album_';
	else
		return;
}
?>