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
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit();
}

global $lang_theme12;

//Predefined variable
$album = $_GET['album'];
$pageback = $_GET['pageback'];

if (!file_exists('data/settings/modules/albums/'.$album))
	echo '<p>'.$lang_albums18.'</p>';

//If the album exists
else {
	//Start reading out those images...
	$files = read_dir_contents('data/settings/modules/albums/'.$album, 'files');

	if ($files) {
		natcasesort($files);

	foreach ($files as $file) {
		//Check if the files are JPG
		list($fdirname, $ext) = explode(".", $file);
		if ($ext == 'jpg') {
			$album = $_GET['album'];
			include_once ('data/settings/modules/albums/'.$album.'/'.$fdirname.'.php');
			?>
				<div class="album">
					<table>
						<tr>
							<td>
								<a href="data/modules/albums/pages_admin/albums_getimage.php?image=<?php echo $album; ?>/<?php echo $fdirname; ?>.jpg" rel="lightbox[album]" title="<?php echo $name; ?>">
									<img src="data/modules/albums/pages_admin/albums_getimage.php?image=<?php echo $album; ?>/thumb/<?php echo $fdirname; ?>.jpg" alt="<?php echo $name; ?>" title="<?php echo $name; ?>" />
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
	}
}
?>
<p><a href="?file=<?php echo $pageback; ?>" title="return link">&lt;&lt;&lt; <?php echo $lang_theme12; ?></a></p>