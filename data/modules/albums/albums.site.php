<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) pluck team
 * http://www.pluck-cms.org

 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');

require_once 'data/modules/albums/functions.php';

function albums_pages_site() {
	global $lang;

	if (file_exists(ALBUMS_DIR.'/'.$_GET['album'].'.php')) {
		include(ALBUMS_DIR.'/'.$_GET['album'].'.php');
		$module_page_site[] = array(
			'func'  => 'viewalbum',
			'title' => $album_name
		);
	}
	else {
		$module_page_site[] = array(
			'func'  => 'viewalbum',
			'title' => $lang['general']['404']
		);
	}
	return $module_page_site;
}

function albums_theme_main($category = 'all') {
	//Only show category listing if category = all
	if ($category == 'all') {
		$albums = albums_get_albums();
		if ($albums != FALSE) {
			//Open the module-folder
			$albums = read_dir_contents(ALBUMS_DIR, 'dirs');

			//Loop through dirs.
			foreach ($albums as $album) {
				include (ALBUMS_DIR.'/'.$album.'.php');

				//Find the first image.
				$files = read_dir_contents(ALBUMS_DIR.'/'.$album, 'files');
				//Only display album if it contains images.
				if (!empty($files)) {
					natcasesort($files);
					foreach ($files as $file) {
						$parts = explode('.', $file);
						if (count($parts) == 4) {
							list($number, $fdirname, $ext, $php) = $parts;
							$first_image = $fdirname.'.'.$ext;
							break;
						}
					}
					unset($file);
					?>
					<div class="album">
						<table>
							<tr>
								<td>
									<a href="<?php echo PAGE_URL_PREFIX.CURRENT_PAGE_SEONAME.ALBUM_URL_PREFIX.$album; ?>" title="album <?php echo $album_name; ?>">
										<img alt="<?php echo $album_name; ?>" title="<?php echo $album_name; ?>" src="<?php echo SITE_URL; ?>/data/modules/albums/albums_getimage.php?image=<?php echo $album; ?>/thumb/<?php echo $first_image; ?>" />
									</a>
								</td>
								<td>
									<span class="albuminfo">
										<a href="<?php echo PAGE_URL_PREFIX.CURRENT_PAGE_SEONAME.ALBUM_URL_PREFIX.$album; ?>" title="album <?php echo $album_name; ?>"><?php echo $album_name; ?></a>
									</span>
								</td>
							</tr>
						</table>
					</div>
					<?php
				}
			}
			unset($albums);
		}
	}
	else {
		albums_site_show_images(seo_url($category));
	}
}

function albums_theme_meta() {
	//Only insert LyteBox when we're viewing an album
	if ((defined('CURRENT_MODULE_DIR') && CURRENT_MODULE_DIR == 'albums') || (defined('CURRENT_PAGE_SEONAME') && module_is_included_in_page('albums', CURRENT_PAGE_SEONAME))) {
	?>
		<script type="text/javascript" src="<?php echo SITE_URL; ?>/data/modules/albums/lib/lytebox/lytebox.js"></script>
		<link rel="stylesheet" href="<?php echo SITE_URL; ?>/data/modules/albums/lib/lytebox/lytebox.css" type="text/css" media="screen" />
	<?php
	}
}

function albums_page_site_viewalbum() {
	global $lang;

	albums_site_show_images($_GET['album']);
	?>
	<p>
		<a href="<?php echo PAGE_URL_PREFIX.CURRENT_PAGE_SEONAME; ?>" title="<?php echo $lang['general']['back']; ?>">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
	</p>
	<?php
}
?>