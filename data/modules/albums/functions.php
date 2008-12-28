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
	exit();
}

//Some functions needed for the album-module are defined here.

//Function: readout albums
//------------
function read_albums($dir) {
	global $lang_albums5, $lang_albums6, $lang_albums14;
	$dirs = read_dir_contents($dir, 'dirs');

	if (!$dirs)
		echo '<span class="kop4">'.$lang_albums14.'</span>';

	elseif (isset($dirs)) {
		natcasesort($dirs);
		foreach ($dirs as $dir) {
		?>
			<div class="menudiv">
				<span>
					<img src="data/modules/albums/images/albums.png" alt="" />
				</span>
				<span style="width: 350px; font-size: 17pt;"><?php echo $dir; ?></span>
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
	}
}


//Function: readout album-images
//------------
function read_albumimages($dir) {
	global $lang_albums6, $lang_albums14, $lang_kop13, $lang_updown5, $var1;
	$files = read_dir_contents($dir, 'files');
	if (!$files)
		echo '<span class="kop4">'.$lang_albums14.'</span>';

	elseif ($files) {
		natcasesort($files);
		foreach ($files as $file) {
			list($fdirname, $ext) = explode('.', $file);
			if ($ext == 'jpg') {
				include ('data/settings/modules/albums/'.$var1.'/'.$fdirname.'.php');
				?>
				<div class="menudiv">
					<span>
						<a href="data/modules/albums/pages_admin/albums_getimage.php?image=<?php echo $var1.'/'.$fdirname; ?>.jpg" target="_blank">
							<img src="data/modules/albums/pages_admin/albums_getimage.php?image=<?php echo $var1; ?>/thumb/<?php echo $fdirname; ?>.jpg" title="<?php echo $name; ?>" alt="<?php echo $name; ?>" />
						</a>
					</span>
					<span style="width: 500px;">
						<span class="kop3"><?php echo $name; ?></span>
						<br />
						<i><?php echo $info; ?></i>
					</span>
					<span>
						<a href="?module=albums&amp;page=editimage&amp;var1=<?php echo $fdirname; ?>&amp;var2=<?php echo $var1; ?>">
							<img src="data/image/edit.png" title="<?php echo $lang_albums6; ?>" alt="<?php echo $lang_albums6; ?>" />
						</a>
					</span>
					<span>
						<a href="?module=albums&amp;page=imageup&amp;var1=<?php echo $fdirname; ?>&amp;var2=<?php echo $var1; ?>">
							<img src="data/image/up.png" title="<?php echo $lang_updown5; ?>" alt="<?php echo $lang_updown5; ?>" />
						</a>
					</span>
					<span>
						<a href="?module=albums&amp;page=imagedown&amp;var1=<?php echo $fdirname; ?>&amp;var2=<?php echo $var1; ?>">
							<img src="data/image/down.png" title="<?php echo $lang_updown5; ?>" alt="<?php echo $lang_updown5; ?>" />
						</a>
					</span>
					<span>
						<a href="?module=albums&amp;page=deleteimage&amp;var1=<?php echo $fdirname; ?>&amp;var2=<?php echo $var1; ?>">
							<img src="data/image/delete_from_trash.png" title="<?php echo $lang_kop13; ?>" alt="<?php echo $lang_kop13; ?>" />
						</a>
					</span>
				</div>
				<?php
			}
		}
	}
}
?>