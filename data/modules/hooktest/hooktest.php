<?php
//TODO: This is only how it could be done.
function testhook_info() {
	$module = array (
		'name'    => 'Hook test',
		'intro'   => 'Created to show the new hooks.',
		'version' => '0.1',
		'author'  => 'Spirit55555',
		'website' => 'http://spirit55555.dk',
		'icon'    => null
	);
}

function testhook_css() {
	echo '<style type="text/css">h1:before, h1:after {content: \'"\';}</style>';
}

add_hook('admin_header_main', 'testhook_css');
?>
