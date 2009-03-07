<?php
function blog_info() {
	global $lang_blog, $lang_blog1;
	$module_info = array(
		'name'          => $lang_blog,
		'intro'         => $lang_blog1,
		'version'       => '0.1',
		'author'        => 'pluck development team',
		'website'       => 'http://spirit55555.dk',
		'icon'          => 'images/blog.png',
		'compatibility' => '4.6'
	);
	return $module_info;
}
?>
