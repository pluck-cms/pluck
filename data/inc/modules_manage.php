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
if((!ereg("index.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("admin.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("install.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("login.php", $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo "access denied";
    //Block all other code
    exit();
}

//Introduction text
?>
<p><strong><?php echo $lang_modules5; ?></strong></p>
<div class="smallmenu">
	<span class="smallmenu_button">
		<a class="smallmenu_a" href="?action=module_addtosite" style="background:url(data/image/add_small.png) no-repeat;"><?php echo $lang_modules13; ?></a>
	</span>
	<span class="smallmenu_button">
		<a class="smallmenu_a" href="?action=installmodule" style="background:url(data/image/install_small.png) no-repeat;"><?php echo $lang_modules11; ?></a>
	</span>
</div>
<?php
//Include Theme data
include("data/settings/themepref.php");
//Include info of theme (to see which positions we can use)
include("data/themes/$themepref/info.php");

//Define path to the module-dir
$path = "data/modules";
//Open the folder
$dir_handle = @opendir($path) or die("Unable to open $path. Check if it's readable.");

//Loop through dirs, and display the modules
while ($dir = readdir($dir_handle)) {
if($dir == "." || $dir == "..")
   continue;
	include("data/modules/$dir/module_info.php");
?>
<div class="menudiv">
	<div>
		<span>
			<img src="data/modules/<?php echo $module_dir; ?>/<?php echo $module_icon; ?>" alt="" />
		</span>
		<span>
			<span class="title-module"><?php echo $module_name; ?></span><br />
		<?php //If module has been disabled, show warning
		if (!module_is_compatible($dir)) {
		echo "<span class=\"red\">$lang_modules27</span>";
		}?>
		</span>
		<span>
			<a href="#" onclick="return kadabra('<?php echo $dir; ?>');"><img src="data/image/credits.png" alt="<?php echo $lang_modules8; ?>" title="<?php echo $lang_modules8; ?>" /></a>		
		</span>
		<span>
			<a href="?action=module_delete&amp;var1=<?php echo $dir; ?>" onclick="return confirm('<?php echo $lang_modules19; ?>');"><img src="data/image/delete_from_trash.png" title="<?php echo $lang_modules10; ?>" alt="<?php echo $lang_modules10; ?>" /></a>		
		</span>
	</div>
	<div>
		<p id="<?php echo $dir; ?>" style="display:none;">
			<?php echo $module_intro; ?><br />
			<strong><?php echo $lang_modules2; ?></strong>: <?php echo $module_version; ?><br />
			<strong><?php echo $lang_modules18; ?></strong>: <?php echo $module_author; ?><br />
			<strong><?php echo $lang_modules17; ?></strong>: <a href="<?php echo $module_website; ?>" target="_blank"><?php echo $module_website; ?></a><br />			
		</p>
	</div>
</div>
<?php
}
//Close module-dir
closedir($dir_handle);
?>