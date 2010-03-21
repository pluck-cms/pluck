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
?>
<p>
	<strong><?php echo $lang['modules_manage']['message']; ?></strong>
</p>
<div class="smallmenu">
	<span class="smallmenu_button">
		<a href="?action=module_addtosite" style="background: url('data/image/add_small.png') no-repeat;"><?php echo $lang['modules_manage']['add']; ?></a>
	</span>
	<span class="smallmenu_button">
		<a href="?action=installmodule" style="background: url('data/image/install_small.png') no-repeat;"><?php echo $lang['modules_manage']['install']; ?></a>
	</span>
</div>
<?php
//Display modules
foreach($module_list as $module) {
	$module_info = call_user_func($module.'_info');
	?>
	<div class="menudiv">
		<div>
			<span>
				<img src="data/modules/<?php echo $module; ?>/<?php echo $module_info['icon']; ?>" alt="" />
			</span>
			<span>
				<span class="title-module"><?php echo $module_info['name']; ?></span>
				<br />
				<?php
					//If module has been disabled, show warning
					if (!module_is_compatible($module)) {
					?>
						<span style="color: red;"><?php echo $lang['modules_manage']['not_compatible']; ?></span>
					<?php
					}
				?>
				</span>
			<span>
				<a href="#" onclick="return kadabra('<?php echo $module; ?>');">
					<img src="data/image/credits.png" alt="<?php echo $lang['modules_manage']['information']; ?>" title="<?php echo $lang['modules_manage']['information']; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=module_delete&amp;var1=<?php echo $module; ?>" onclick="return confirm('<?php echo $lang['modules_manage']['uninstall_confirm']; ?>');">
					<img src="data/image/delete_from_trash.png" title="<?php echo $lang['modules_manage']['uninstall']; ?>" alt="<?php echo $lang['modules_manage']['uninstall']; ?>" />
				</a>
			</span>
		</div>
		<div>
			<p id="<?php echo $module; ?>" class="module-text">
				<?php echo $module_info['intro']; ?>
				<br />
				<strong><?php echo $lang['modules_manage']['version']; ?></strong>: <?php echo $module_info['version']; ?>
				<br />
				<strong><?php echo $lang['modules_manage']['author']; ?></strong>: <?php echo $module_info['author']; ?>
				<br />
				<strong><?php echo $lang['modules_manage']['website']; ?></strong>: <a href="<?php echo $module_info['website']; ?>" target="_blank"><?php echo $module_info['website'] ?></a>
				<br />
			</p>
		</div>
	</div>
<?php
}
unset($module);
?>
<p>
	<a href="?action=options">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
</p>