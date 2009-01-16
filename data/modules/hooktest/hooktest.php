<?php
/*
 * Name: Hooktest
 * Intro: Created to show the new hooks.
 * Version: 0.1
 * Author: Anders Jørgensen
 * Website: http://spirit55555.dk
 * Icon: null
 * Compatibility: 4.7
 */

function hooktest_css() {
	echo '<style type="text/css">h1:before, h1:after {content: \'"\';}</style>';
}

add_hook('admin_header_main', 'hooktest_css');
?>
