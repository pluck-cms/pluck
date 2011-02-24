<?php
function viewsite_info() {
	global $lang;
	return array(
		'name'          => $lang['viewsite']['module_name'],
		'intro'         => $lang['viewsite']['module_intro'],
		'version'       => '0.1',
		'author'        => $lang['general']['pluck_dev_team'],
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => '../../image/website.png',
		'compatibility' => '4.7'
	);
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

function viewsite_admin_page_list_before($file) {
	global $lang; ?>
	<span>
		<a href="index.php?file=<?php echo $file; ?>" target="_blank">
			<img src="data/image/website.png" title="<?php echo $lang['page']['view']; ?>" alt="<?php echo $lang['page']['view']; ?>" />
		</a>
	</span>
<?php
}
?>