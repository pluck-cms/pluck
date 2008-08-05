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
echo "<p><b>$lang_modules5</b></p>";

echo "<table class=\"smallmenu\">
<tr>
	<td style=\"width:45%;\"></td>
	<td class=\"smallmenu_button\">
		<a class=\"smallmenu_a\" href=\"?action=module_addtosite\" style=\"background:url(data/image/add_small.png) no-repeat;\">$lang_modules13</a>
	</td>
	<td class=\"smallmenu_button\">
		<a class=\"smallmenu_a\" href=\"http://www.pluck-cms.org/addons/\" target=\"_blank\" style=\"background:url(data/image/download_small.png) no-repeat;\">$lang_modules12</a>
	</td>
	<td class=\"smallmenu_button\">
		<a class=\"smallmenu_a\" href=\"?action=installmodule\" style=\"background:url(data/image/install_small.png) no-repeat;\">$lang_modules11</a>
	</td>
</tr>
</table>";

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


echo "<div class=\"menudiv\" style=\"margin:10px;\">
	<table>
		<tr>
			<td>
				<img src=\"data/modules/$module_dir/$module_icon\" border=\"0\" alt=\"\">
			</td>
			<td style=\"width: 600px;\">
				<span style=\"font-size: 17pt; color:gray;\">$module_name</span><br>";
	//If module has been disabled, show warning
	if (!module_is_compatible($dir)) {
		echo "<span style=\"color:red;\">$lang_modules27</span>";
	}
	echo "</td>
			<td>
				<a href=\"#\" onclick=\"return kadabra('$dir');\"><img src=\"data/image/credits.png\" border=\"0\" alt=\"$lang_modules8\" title=\"$lang_modules8\"></a>		
			</td>
			<td>
				<a href=\"?action=module_delete&var1=$dir\" onclick=\"return confirm('$lang_modules19');\"><img src=\"data/image/delete_from_trash.png\" border=\"0\" title=\"$lang_modules10\" alt=\"$lang_modules10\"></a>		
			</td>
		</tr>
		<tr>
		<td></td>
		<td>
		<p id=\"$dir\" style=\"display:none;\">
			$module_intro<br>
			<b>$lang_modules2</b>: $module_version<br>
			<b>$lang_modules18</b>: $module_author<br>
			<b>$lang_modules17</b>: <a href=\"$module_website\" target=\"_blank\">$module_website</a><br>			
		</p>
		</td>
		</tr>
	</table>
	</div>";
	
}
//Close module-dir
closedir($dir_handle);
?>