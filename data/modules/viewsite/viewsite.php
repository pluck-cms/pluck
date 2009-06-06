<?php
function viewsite_info() {
	global $lang;
	$module_info = array(
		'name'          => $lang['viewsite']['module_name'],
		'intro'         => $lang['viewsite']['module_intro'],
		'version'       => '0.1',
		'author'        => 'pluck development team',
		'website'       => 'http://pluck-cms.org',
		'icon'          => '../../image/website.png',
		'compatibility' => '4.7'
	);
	return $module_info;
}

function viewsite_admin_menu($links) {
	global $lang;
	
	$data[] = array(
		'href' => 'index.php'.HOME_PAGE,
		'img'  => 'data/modules/viewsite/images/viewsite.png',
		'text' => $lang['viewsite']['message'],
		'target' => '_blank'
	);

	$links = module_insert_at_position($links, $data, 1);
}
?>