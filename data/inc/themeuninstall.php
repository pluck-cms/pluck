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
	exit;
}
echo '<p>'.$lang['theme_uninstall']['message'].'</p>';

$dirs = read_dir_contents('data/themes', 'dirs');
if ($dirs) {
	natcasesort($dirs);
	foreach ($dirs as $dir) {
		if (file_exists('data/themes/'.$dir.'/info.php')) {
			include_once ('data/themes/'.$dir.'/info.php');
			//If theme is current theme, dont show it
			if ($themedir !== THEME) {
			?>
			<span><a href="?action=theme_delete&amp;var1=<?php echo $themedir; ?>" onclick="return confirm('<?php echo $lang['theme_uninstall']['uninstall_confirm']; ?>');"><img src="data/image/delete_from_trash.png" title="<?php echo $lang['theme_uninstall']['title']; ?>" alt="<?php echo $lang['theme_uninstall']['title']; ?>" /><?php echo $themename; ?></a></span><br />
			<?php
			}
		}
	}
	unset($dir);
}
?>