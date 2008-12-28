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

//Open the module-folder
$dir_handle = @opendir('data/settings/modules/albums') or die('Unable to open data/modules. Check if it\'s readable.');

//Loop through dirs
while ($dir = readdir($dir_handle)) {
	if (file_exists('data/settings/modules/albums/'.$dir.'/thumb/image1.jpg')) {
	?>
		<div class="album">
			<table>
				<tr>
					<td><a href="?module=albums&amp;page=viewalbum&amp;album=<?php echo $dir; ?>&amp;pageback=<?php echo CURRENT_PAGE_FILENAME; ?>" title="album <?php echo $dir; ?>"><img alt="<?php echo $dir; ?>" title="<?php echo $dir; ?>" src="data/modules/albums/pages_admin/albums_getimage.php?image=<?php echo $dir; ?>/thumb/image1.jpg" /></a></td>
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
?>