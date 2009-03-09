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
?>
<p>
	<strong><?php echo $lang_modules5; ?></strong>
</p>
<div class="smallmenu">
	<span class="smallmenu_button">
		<a class="smallmenu_a" href="?action=module_addtosite" style="background: url('data/image/add_small.png') no-repeat;"><?php echo $lang_modules13; ?></a>
	</span>
	<span class="smallmenu_button">
		<a class="smallmenu_a" href="?action=installmodule" style="background: url('data/image/install_small.png') no-repeat;"><?php echo $lang_modules11; ?></a>
	</span>
</div>
<?php
//Readout dir and put dirs in array
$dirs = read_dir_contents('data/modules', 'dirs');
//Display modules
foreach($dirs as $dir) {
	$module_info = call_user_func($dir.'_info');
	?>
	<div class="menudiv">
		<div>
			<span>
				<img src="data/modules/<?php echo $dir; ?>/<?php echo $module_info['icon']; ?>" alt="" />
			</span>
			<span>
				<span class="title-module"><?php echo $module_info['name']; ?></span>
				<br />
				<?php
					//If module has been disabled, show warning
					if (!module_is_compatible($dir)) {
					?>
						<span class="red"><?php echo $lang_modules27; ?></span>
					<?php
					}
				?>
			</span>
			<span>
				<a href="#" onclick="return kadabra('<?php echo $dir; ?>');">
					<img src="data/image/credits.png" alt="<?php echo $lang_modules8; ?>" title="<?php echo $lang_modules8; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=module_delete&amp;var1=<?php echo $dir; ?>" onclick="return confirm('<?php echo $lang_modules19; ?>');">
					<img src="data/image/delete_from_trash.png" title="<?php echo $lang_modules10; ?>" alt="<?php echo $lang_modules10; ?>" />
				</a>
			</span>
		</div>
		<div>
			<p id="<?php echo $dir; ?>" style="display: none; padding-left: 43px;">
				<?php echo $module_info['intro']; ?>
				<br />
				<strong><?php echo $lang_modules2; ?></strong>: <?php echo $module_info['version']; ?>
				<br />
				<strong><?php echo $lang_modules18; ?></strong>: <?php echo $module_info['author']; ?>
				<br />
				<strong><?php echo $lang_modules17; ?></strong>: <a href="<?php echo $module_info['website']; ?>" target="_blank"><?php echo $module_info['website'] ?></a>
				<br />
			</p>
		</div>
	</div>
<?php
}
unset($dir);
?>
<div class="menudiv">
	<span>
		<img src="data/image/themes.png" alt="" />
	</span>
	<span>
		<span class="kop3">
			<a href="?action=options">&lt;&lt;&lt; <?php echo $lang['back']; ?></a>
		</span>
	</span>
</div>