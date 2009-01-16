<?php
/*
 * Name: hooktest
 * Intro: Created to show the new hooks.
 * Version: 0.1
 * Author: Anders Jørgensen
 * Website: http://spirit55555.dk
 * Icon: ../../image/stats.png
 * Compatibility: 4.7
 */

function hooktest_css() {
	echo '<style type="text/css">h1:before, h1:after {content: \'"\';}</style>';
}

add_hook('admin_header_main', 'hooktest_css');

function hooktest_content() {
	echo '<p>Works in themes too!</p>';
}

add_hook('theme_content_after', 'hooktest_content');
?>
