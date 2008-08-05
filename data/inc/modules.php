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
echo "<p><b>$lang_modules1</b></p>";

//Define path to the module-dir
$path = "data/modules";
//Open the folder
$dir_handle = @opendir($path) or die("Unable to open $path. Check if it's readable.");

//Loop through dirs, and display the modules
while ($dir = readdir($dir_handle)) {
if($dir == "." || $dir == "..")
   continue;
   if(file_exists("data/modules/$dir/module_info.php")) {
		include("data/modules/$dir/module_info.php");
	}
	//Only show the button if there are admincenter pages for the module, and if the modules is compatible
	if((file_exists("data/modules/$dir/module_pages_admin.php")) && (module_is_compatible($dir))) {
		showmenudiv($module_name,$module_intro,"../modules/$module_dir/$module_icon","?module=$module_dir","false", "");
	}
}
//Close module-dir
closedir($dir_handle); 
?>