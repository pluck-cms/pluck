<?php
function hooktest_info() {
	$module_info = array(
		'name'          => 'hooktest',
		'intro'         => 'Created to show the new hooks.',
		'version'       => '0.1',
		'author'        => 'Anders Jørgensen',
		'website'       => 'http://spirit55555.dk',
		'icon'          => '../../image/stats.png',
		'compatibility' => '4.7'
	);
	return $module_info;
}

function hooktest_theme_main() {
	echo '<a href="?module=hooktest&amp;page=hook">Module page link</a>';
}

function hooktest_admin_header_main() {
	echo '<style type="text/css">h1:before, h1:after {content: \'"\';}</style>';
}

function hooktest_admin_menu_inside_before() {
?>
	<div class="menuitem">
		<span>
			<img src="data/image/website.png" alt="" height="22" />
			<a href="index.php?file=kop1.php" title="view site" target="_blank">view site</a>
		</span>
	</div>
<?php
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

function hooktest_page_admin_foo() {
	echo 'Admin pages are working<br /><a href="?module=hooktest&amp;page=bar">Barfoo page</a>';
}

function hooktest_page_admin_bar() {
	echo 'Also working';
}
?>
