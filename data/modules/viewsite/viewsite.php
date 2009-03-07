<?php
function viewsite_info() {
	$module_info = array(
		'name'          => 'view site link',
		'intro'         => 'Created to show the new hooks. Adds a direct link to the site in the admin menu.',
		'version'       => '0.1',
		'author'        => 'pluck development team',
		'website'       => 'http://pluck-cms.org',
		'icon'          => '../../image/website.png',
		'compatibility' => '4.7'
	);
	return $module_info;
}

function viewsite_admin_menu_inside_before() {
global $lang;
?>
	<div class="menuitem">
		<span>
			<img src="data/image/website.png" alt="" height="22" />
			<a href="index.php?file=kop1.php" title="<?php echo $lang['viewsite']; ?>" target="_blank">view site</a>
		</span>
	</div>
<?php
}
?>