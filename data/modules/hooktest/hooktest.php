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

function hooktest_admin_header_main() {
	echo '<style type="text/css">h1:before, h1:after {content: \'"\';}</style>';
}

function hooktest_theme_content_after() {
	echo '<p>Works in themes too!</p>';
}

function hooktest_theme_main() {
	echo '<a href="?module=hooktest&amp;page=hook">Module page link</a>';
}

//Site pages.
function hooktest_page_site_list() {
	$module_page_site[] = array(
		'func' => 'hook',
		'title' => 'Hooks are great!'
	);
	$module_page_site[] = array(
		'func' => 'filter',
		'title' => 'I want filters!'
	);
	return $module_page_site;
}

function hooktest_page_site_hook() {
	echo 'Hooks are great when they can have their own pages.';
}

function hooktest_page_site_filter() {
	echo 'FILTER!';
}

//Admin pages. $module_page_admin[0] is the start page.
function hooktest_page_admin_list() {
	$module_page_admin[] = array(
		'func' => 'foo',
		'title' => 'Foobar'
	);
	$module_page_admin[] = array(
		'func' => 'bar',
		'title' => 'Barfoo'
	);
	return $module_page_admin;
}
?>
