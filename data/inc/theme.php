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

//Save the theme-data.
if (isset($_POST['save'], $cont1) && file_exists('data/themes/'.$cont1)) {
	save_theme($cont1);

	//Redirect user
	show_error($lang['theme']['saved'], 3);
	redirect('?action=options', 2);
	include_once ('data/inc/footer.php');
	exit;
}
?>
<div class="rightmenu">
	<div class="menudiv">
		<span>
			<img src="data/image/install.png" alt="<?php echo $lang['theme_install']['title']; ?>" title="<?php echo $lang['theme_install']['title']; ?>" />
		</span>
		<span class="kop3">
			<a href="?action=themeinstall" title="<?php echo $lang['theme_install']['title']; ?>"><?php echo $lang['theme_install']['title']; ?></a>
		</span>
	</div>
	<div class="menudiv">
		<span><img src="data/image/delete.png" alt="<?php echo $lang['theme_uninstall']['title']; ?>" title="<?php echo $lang['theme_uninstall']['title']; ?>" /></span>
		<span class="kop3">
			<a href="?action=themeuninstall" title="<?php echo $lang['theme_uninstall']['title']; ?>"><?php echo$lang['theme_uninstall']['title']; ?></a>
		</span>
	</div>
</div>
<p>
	<strong><?php echo $lang['theme']['choose']; ?></strong>
</p>
<?php run_hook('admin_theme_before'); ?>
<form action="" method="post">
	<p>
		<select name="cont1">
			<?php
			$dirs = read_dir_contents('data/themes', 'dirs');
			if ($dirs) {
				natcasesort($dirs);
				foreach ($dirs as $dir) {
					if (file_exists('data/themes/'.$dir.'/info.php')) {
						include_once ('data/themes/'.$dir.'/info.php');
						//If theme is current theme, select it
						if ($themedir == THEME) {
						?>
							<option value="<?php echo $themedir; ?>" selected="selected"><?php echo $themename; ?></option>
						<?php
						}
						//Otherwise, don't select it
						else {
						?>
							<option value="<?php echo $themedir; ?>"><?php echo $themename; ?></option>
						<?php
						}
					}
				}
				unset($dir);
			}
			?>
		</select>
	</p>
	<?php show_common_submits('?action=options'); ?>
</form>