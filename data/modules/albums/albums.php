<?php
function albums_info() {
	global $lang_albums, $lang_albums7;
	$module_info = array(
		'name'          => $lang_albums,
		'intro'         => $lang_albums7,
		'version'       => '0.2',
		'author'        => 'pluck development team',
		'website'       => 'http://www.pluck-cms.org',
		'icon'          => 'images/albums.png',
		'compatibility' => '4.7'
	);
	return $module_info;
}

function albums_theme_main() {
	//Open the module-folder
	$dir_handle = @opendir('data/settings/modules/albums') or die('Unable to open data/modules. Check if it\'s readable.');

	//Loop through dirs
	while ($dir = readdir($dir_handle)) {
		if (file_exists('data/settings/modules/albums/'.$dir.'/thumb/image1.jpg')) {
		?>
			<div class="album">
				<table>
					<tr>
						<td>
							<a href="?module=albums&amp;page=viewalbum&amp;album=<?php echo $dir; ?>&amp;pageback=<?php echo CURRENT_PAGE_FILENAME; ?>" title="album <?php echo $dir; ?>"><img alt="<?php echo $dir; ?>" title="<?php echo $dir; ?>" src="data/modules/albums/albums_getimage.php?image=<?php echo $dir; ?>/thumb/image1.jpg" /></a>
						</td>
						<td>
							<span class="albuminfo">
								<a href="?module=albums&amp;page=viewalbum&amp;album=<?php echo $dir; ?>&amp;pageback=<?php echo CURRENT_PAGE_FILENAME; ?>" title="album <?php echo $dir; ?>"><?php echo $dir; ?></a>
							</span>
						</td>
					</tr>
				</table>
			</div>
		<?php
		}
	}
}
?>
