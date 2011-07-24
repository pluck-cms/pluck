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
?>
<p>
	<strong><?php echo $lang['theme_uninstall']['message']; ?></strong>
</p>

<?php
$dirs = read_dir_contents('data/themes', 'dirs');
if ($dirs) {
	natcasesort($dirs);
	foreach ($dirs as $dir) {
		if (file_exists('data/themes/'.$dir.'/info.php')) {
			include_once ('data/themes/'.$dir.'/info.php');
			//If theme is current theme, dont show it
			if ($themedir !== THEME) {
			?>
			<div class="menudiv">
				<span>
					<img src="data/image/themes.png" alt="" />
				</span>
				<span class="title-module"><?php echo $themename; ?></span>
				<span>
					<a href="?action=theme_delete&amp;var1=<?php echo $themedir; ?>" onclick="return confirm('<?php echo $lang['theme_uninstall']['uninstall_confirm']; ?>');"><img src="data/image/delete_from_trash.png" title="<?php echo $lang['theme_uninstall']['title']; ?>" alt="<?php echo $lang['theme_uninstall']['title']; ?>" /></a>
				</span>
			</div>
			<?php
			}
		}
	}
	unset($dir);
}
?>
<p>
		<a href="?action=theme" title="<?php echo $lang['general']['back']; ?>">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a></p>