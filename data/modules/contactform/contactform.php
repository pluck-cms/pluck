<?php
function contactform_info() {
	global $lang;
	return array(
		'name'          => $lang['contactform']['module_name'],
		'intro'         => $lang['contactform']['module_intro'],
		'version'       => '0.2',
		'author'        => $lang['general']['pluck_dev_team'],
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/contactform.png',
		'compatibility' => '4.7'
	);
}
?>