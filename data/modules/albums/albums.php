<?php
function albums_info() {
	global $lang, $lang_albums7;
	return array(
		'name'          => $lang['albums']['title'],
		'intro'         => $lang_albums7,
		'version'       => '0.2',
		'author'        => 'pluck development team',
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/albums.png',
		'compatibility' => '4.7'
	);
}
?>