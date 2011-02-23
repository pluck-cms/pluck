<?php
//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');

function albums_info() {
	global $lang;
	return array(
		'name'          => $lang['albums']['title'],
		'intro'         => $lang['albums']['descr'],
		'version'       => '0.2',
		'author'        => 'pluck development team',
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/albums.png',
		'compatibility' => '4.7',
		'categories'    => albums_get_albums(TRUE)
	);
}
?>