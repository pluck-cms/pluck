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

function hooktest_admin_start_welcome($lang_start1) {
	$lang_start1 = str_replace('pluck', 'pluck-cms', $lang_start1);
}
?>