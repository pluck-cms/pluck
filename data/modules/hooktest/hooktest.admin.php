<?php
//Admin pages. $module_page_admin[0] is the start page.
function hooktest_page_admin_list() {
	$module_page_admin[] = array(
		'func'  => 'foo',
		'title' => 'Foobar'
	);
	$module_page_admin[] = array(
		'func'  => 'bar',
		'title' => 'Barfoo'
	);
	return $module_page_admin;
}

function hooktest_page_admin_foo() {
	echo 'Admin pages are working<br /><a href="?module=hooktest&amp;page=bar">Barfoo page</a>';
}

function hooktest_page_admin_bar() {
	echo 'Also working';
}
?>
