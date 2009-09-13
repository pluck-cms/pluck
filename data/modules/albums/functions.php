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

//Make sure the file isn't accessed directly
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit;
}

//Two constants we use in imageup.php and imagedown.php.
define('TEMP', '_temp');
define('NAME', 'image');

function read_albums($dir) {
	global $lang, $lang_albums5, $lang_albums6;
	$dirs = read_dir_contents($dir, 'dirs');

	if (!$dirs)
		echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span>';

	elseif (isset($dirs)) {
		natcasesort($dirs);
		foreach ($dirs as $dir) {
			include_once (MODULE_SETTINGS.'/'.$dir.'.php');
			?>
				<div class="menudiv">
					<span>
						<img src="<?php echo MODULE_DIR; ?>/images/albums.png" alt="" />
					</span>
					<span class="title-page"><?php echo $album_name; ?></span>
					<span>
						<a href="?module=albums&amp;page=editalbum&amp;var1=<?php echo $dir; ?>">
							<img src="data/image/edit.png" title="<?php echo $lang_albums6; ?>" alt="<?php echo $lang_albums6; ?>" />
						</a>
					</span>
					<span>
						<a href="?module=albums&amp;page=deletealbum&amp;var1=<?php echo $dir; ?>">
							<img src="data/image/delete_from_trash.png"  title="<?php echo $lang_albums5; ?>" alt="<?php echo $lang_albums5; ?>" />
						</a>
					</span>
				</div>
			<?php
		}
		unset($dir);
	}
}

function read_albumimages($dir) {
	global $lang, $lang_albums15, $lang_kop13, $lang_updown5, $var1;
	$files = read_dir_contents($dir, 'files');
	if (!$files)
		echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span><br />';

	elseif ($files) {
		natcasesort($files);
		foreach ($files as $file) {
			$parts = explode('.', $file);
			if (count($parts) == 4) {
				list($number, $fdirname, $ext, $php) = $parts;
				include_once (MODULE_SETTINGS.'/'.$var1.'/'.$file);
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
							<i><?php echo $info; ?></i>
						</span>
						<span>
							<a href="?module=albums&amp;page=editimage&amp;var1=<?php echo $var1; ?>&amp;var2=<?php echo $fdirname; ?>">
								<img src="data/image/edit.png" title="<?php echo $lang_albums15; ?>" alt="<?php echo $lang_albums15; ?>" />
							</a>
						</span>
						<span>
							<a href="?module=albums&amp;page=imageup&amp;var1=<?php echo $var1; ?>&amp;var2=<?php echo $fdirname; ?>">
								<img src="data/image/up.png" title="<?php echo $lang_updown5; ?>" alt="<?php echo $lang_updown5; ?>" />
							</a>
						</span>
						<span>
							<a href="?module=albums&amp;page=imagedown&amp;var1=<?php echo $var1; ?>&amp;var2=<?php echo $fdirname; ?>">
								<img src="data/image/down.png" title="<?php echo $lang_updown5; ?>" alt="<?php echo $lang_updown5; ?>" />
							</a>
						</span>
						<span>
							<a href="?module=albums&amp;page=deleteimage&amp;var1=<?php echo $var1; ?>&amp;var2=<?php echo $fdirname; ?>">
								<img src="data/image/delete_from_trash.png" title="<?php echo $lang_kop13; ?>" alt="<?php echo $lang_kop13; ?>" />
							</a>
						</span>
					</div>
				<?php
			}
		}
		unset($file);
	}
}

function albums_get_php_filename($album, $seoname) {
	$files = read_dir_contents(MODULE_SETTINGS.'/'.$album, 'files');
	foreach ($files as $file) {
		$parts = explode('.', $file);
		if (count($parts) == 4) {
			list($number, $fdirname, $ext, $php) = $parts;
			if ($seoname == $fdirname && file_exists(MODULE_SETTINGS.'/'.$album.'/'.$fdirname.'.'.$ext))
				return $file;
		}
	}
	return false;
}

function albums_reorder_images($album) {
	$files = read_dir_contents(MODULE_SETTINGS.'/'.$album, 'files');

	//Don't reorder somthing that aren't there.
	if ($files) {
	    $number = 1;
	    foreach ($files as $file) {
		    $parts = explode('.', $file);
		    if (isset($parts[3])) {
			    rename(MODULE_SETTINGS.'/'.$album.'/'.$file, MODULE_SETTINGS.'/'.$album.'/'.$number.'.'.$parts[1].'.'.$parts[2].'.'.$parts[3]);
			    $number++;
		    }
	    }
	}
}
?>