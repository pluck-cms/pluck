<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) somp (www.somp.nl)
 * http://www.pluck-cms.org

 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');

define('ALBUMS_DIR', 'data/settings/modules/albums');
//Two constants we use in imageup.php and imagedown.php.
define('TEMP', '_temp');
define('NAME', 'image');

function read_albums() {
	global $lang;
	$albums = albums_get_albums();

	if ($albums == FALSE)
		echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span>';

	else {
		foreach ($albums as $album) {
			?>
				<div class="menudiv">
					<span>
						<img src="<?php echo MODULE_DIR; ?>/images/albums.png" alt="" />
					</span>
					<span class="title-page"><?php echo $album['title']; ?></span>
					<span>
						<a href="?module=albums&amp;page=editalbum&amp;var1=<?php echo $album['seoname']; ?>">
							<img src="data/image/edit.png" title="<?php echo $lang['albums']['edit_album']; ?>" alt="<?php echo $lang['albums']['edit_album']; ?>" />
						</a>
					</span>
					<span>
						<a href="?module=albums&amp;page=deletealbum&amp;var1=<?php echo $album['seoname']; ?>">
							<img src="data/image/delete_from_trash.png"  title="<?php echo $lang['albums']['delete_album']; ?>" alt="<?php echo $lang['albums']['delete_album']; ?>" />
						</a>
					</span>
				</div>
			<?php
		}
		unset($albums);
	}
}

/**
 * Load albums in an array. Will return FALSE if no albums exist.
 * If $only_return_title is TRUE, only the title will be returned (seoname will be discarded).
 */
function albums_get_albums($only_return_title = FALSE) {
	$files = read_dir_contents('data/settings/modules/albums', 'files');

	if ($files) {
		natcasesort($files);
		foreach ($files as $album) {
			include('data/settings/modules/albums/'.$album);
			if ($only_return_title == TRUE)
				$albums[] = $album_name;
			else {
				$albums[] = array(
					'title'   => $album_name,
					'seoname' => str_replace('.php', '', $album)
				);
			}
		}
		unset($album);

		return $albums;
	}

	else
		return false;
}

function albums_admin_show_images($dir) {
	global $lang, $var1;
	$files = read_dir_contents($dir, 'files');
	if (!$files)
		echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span><br />';

	elseif ($files) {
		natcasesort($files);
		foreach ($files as $file) {
			$parts = explode('.', $file);
			if (count($parts) == 4) {
				list($number, $fdirname, $ext, $php) = $parts;
				include_once (ALBUMS_DIR.'/'.$var1.'/'.$file);
				?>
					<div class="menudiv">
						<span>
							<a href="<?php echo MODULE_DIR; ?>/albums_getimage.php?image=<?php echo $var1.'/'.$fdirname.'.'.$ext; ?>" target="_blank">
								<img src="<?php echo MODULE_DIR; ?>/albums_getimage.php?image=<?php echo $var1; ?>/thumb/<?php echo $fdirname.'.'.$ext; ?>" title="<?php echo $name; ?>" alt="<?php echo $name; ?>" />
							</a>
						</span>
						<span class="title-page">
							<span class="kop3"><?php echo $name; ?></span>
							<br />
							<span class="small"><?php echo $info; ?></span>
						</span>
						<span>
							<a href="?module=albums&amp;page=editimage&amp;var1=<?php echo $var1; ?>&amp;var2=<?php echo $fdirname; ?>">
								<img src="data/image/edit.png" title="<?php echo $lang['albums']['edit_image']; ?>" alt="<?php echo $lang['albums']['edit_image']; ?>" />
							</a>
						</span>
						<span>
							<a href="?module=albums&amp;page=imageup&amp;var1=<?php echo $var1; ?>&amp;var2=<?php echo $fdirname; ?>">
								<img src="data/image/up.png" title="<?php echo $lang['albums']['change_order']; ?>" alt="<?php echo $lang['albums']['change_order']; ?>" />
							</a>
						</span>
						<span>
							<a href="?module=albums&amp;page=imagedown&amp;var1=<?php echo $var1; ?>&amp;var2=<?php echo $fdirname; ?>">
								<img src="data/image/down.png" title="<?php echo $lang['albums']['change_order']; ?>" alt="<?php echo $lang['albums']['change_order']; ?>" />
							</a>
						</span>
						<span>
							<a href="?module=albums&amp;page=deleteimage&amp;var1=<?php echo $var1; ?>&amp;var2=<?php echo $fdirname; ?>">
								<img src="data/image/delete_from_trash.png" title="<?php echo $lang['albums']['delete_image']; ?>" alt="<?php echo $lang['albums']['delete_image']; ?>" />
							</a>
						</span>
					</div>
				<?php
			}
		}
		unset($file);
	}
}

function albums_site_show_images($album) {
	if (!file_exists(ALBUMS_DIR.'/'.$album))
		echo '<p>'.$lang['albums']['doesnt_exist'].'</p>';

	//If the album exists
	else {
		//Start reading out those images...
		$files = read_dir_contents(ALBUMS_DIR.'/'.$album, 'files');
		if ($files) {
			natcasesort($files);
			foreach ($files as $file) {
				$parts = explode('.', $file);
				if (count($parts) == 4) {
					list($number, $fdirname, $ext, $php) = $parts;
					include_once (ALBUMS_DIR.'/'.$album.'/'.albums_get_php_filename($album, $fdirname));
					?>
						<div class="album">
							<table>
								<tr>
									<td>
										<a href="data/modules/albums/albums_getimage.php?image=<?php echo $album; ?>/<?php echo $fdirname.'.'.$ext; ?>" rel="lytebox[album]" title="<?php echo $name; ?>">
											<img src="data/modules/albums/albums_getimage.php?image=<?php echo $album; ?>/thumb/<?php echo $fdirname.'.'.$ext; ?>" alt="<?php echo $name; ?>" title="<?php echo $name; ?>" />
										</a>
									</td>
									<td>
										<span class="albuminfo"><?php echo $name; ?></span>
										<br />
										<i><?php echo $info; ?></i>
									</td>
								</tr>
							</table>
						</div>
					<?php
				}
			}
			unset($file);
		}
	}
}

function albums_get_php_filename($album, $seoname) {
	$files = read_dir_contents(ALBUMS_DIR.'/'.$album, 'files');
	foreach ($files as $file) {
		$parts = explode('.', $file);
		if (count($parts) == 4) {
			list($number, $fdirname, $ext, $php) = $parts;
			if ($seoname == $fdirname && file_exists(ALBUMS_DIR.'/'.$album.'/'.$fdirname.'.'.$ext))
				return $file;
		}
	}
	return false;
}

function albums_reorder_images($album) {
	$files = read_dir_contents(ALBUMS_DIR.'/'.$album, 'files');

	//Don't reorder somthing that aren't there.
	if ($files) {
	    $number = 1;
	    foreach ($files as $file) {
		    $parts = explode('.', $file);
		    if (isset($parts[3])) {
			    rename(ALBUMS_DIR.'/'.$album.'/'.$file, ALBUMS_DIR.'/'.$album.'/'.$number.'.'.$parts[1].'.'.$parts[2].'.'.$parts[3]);
			    $number++;
		    }
	    }
	}
}
?>