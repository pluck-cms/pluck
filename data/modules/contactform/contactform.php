<?php
function contactform_info() {
	global $lang_contact12, $lang_contact13;
	$module_info = array(
		'name'          => $lang_contact12,
		'intro'         => $lang_contact13,
		'version'       => '0.2',
		'author'        => 'pluck development team',
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/contactform.png',
		'compatibility' => '4.7'
	);
	return $module_info;
}
?>