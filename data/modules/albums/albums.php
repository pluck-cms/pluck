<?php
function albums_info() {
	global $lang_albums, $lang_albums7;
	return array(
		'name'          => $lang_albums,
		'intro'         => $lang_albums7,
		'version'       => '0.2',
		'author'        => 'pluck development team',
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/albums.png',
		'compatibility' => '4.7'
	);
}
?>