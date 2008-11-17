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
if((!ereg('index.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error.
    echo 'access denied';
    //Block all other code.
    exit();
}

//Introduction text
?>
<p><strong><?php echo $lang_modules15; ?></strong></p>
<?php

//Include info of theme (to see which positions we can use)
include_once ('data/themes/'.$themepref.'/info.php');
//Include the existing module-settings for the theme
if (file_exists('data/settings/themes/'.$themedir.'/moduleconf.php')) {
	include_once ('data/settings/themes/'.$themedir.'/moduleconf.php');
}

//Start html-form
echo "<form action=\"\" method=\"post\">";

foreach ($module_space as $index => $position) {
	echo "<div class=\"menudiv\" style=\"margin: 10px;\">
	<table>
	<tr>
	<td>
	<img src=\"data/image/page.png\" border=\"0\" alt=\"\">
	</td>

	<td>
	<span style=\"font-size: 17pt; color:gray;\">$position</span><br>
	<b>$lang_modules7</b>
	<table>
	<tr>";

	//Define path to the module-dir
	$path = "data/modules";
	//Open the folder
	$dir_handle = @opendir($path) or die("Unable to open $path. Check if it's readable.");

	//First count how many modules we have, and exclude disabled modules
	$number_modules = count(glob("data/modules/*"));
	while ($dir = readdir($dir_handle)) {
		if($dir != "." && $dir != "..") {
			if (!module_is_compatible($dir)) {
				$number_modules = $number_modules-1;
			}
		}
	}
	closedir($dir_handle);

	//Loop through dirs, and display the modules
	$dir_handle = @opendir($path) or die("Unable to open $path. Check if it's readable.");
	while ($dir = readdir($dir_handle)) {
		if($dir != "." && $dir != "..") {
			//Only show if module is compatible
			if (module_is_compatible($dir)) {
				include("data/modules/$dir/module_info.php");

				echo "<td>$module_name</td>
				<td><select name=\"$position|$module_dir\">
				<option value=\"0\">$lang_modules6";

				$counting_modules = 1;
				while ($counting_modules <= $number_modules) {
					//Check if this is the current setting
					//...and select the html-option if needed
					$space = null;
					$currentsetting = $space [$position] [$module_dir];
					if ($currentsetting == $counting_modules) {
						echo "<option value=\"$counting_modules\" selected>$counting_modules";
					}
					//...if this is not the current setting, don't select the html-option
					else {
						echo "<option value=\"$counting_modules\">$counting_modules";
					}

					//Higher counting_modules
					$counting_modules++;
				}
			}
			echo "</select></td></tr>";
		}
	}
	closedir($dir_handle);

	//Close html-elements
	echo "</table></td></tr></table></div>";
}

//Show submit button etc.
echo "<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\">
<input type=\"button\" name=\"Cancel\" value=\"$lang_install14\" onclick=\"javascript: window.location='?action=managemodules';\">
</form>";


//If the form has been posted, save the data
//------------------------------------------
if (isset($_POST['Submit'])) {

//Get POST-data
$themedir = $themepref;
if (isset($_POST['moduledir']))
	$moduledir = $_POST['moduledir'];
if (isset($_POST['position']))
	$position = $_POST['position'];

//First, check if the settings/modules_inc dir exists
//If not, create the dir
if (!file_exists("data/settings/themes")) {
	mkdir("data/settings/themes", 0777);
	chmod("data/settings/themes", 0777);
}

//Then, check if a dir for the current theme is already available
//If not, create the appropriate dirs
if (!file_exists("data/settings/themes/$themedir")) {
	mkdir("data/settings/themes/$themedir", 0777);
	chmod("data/settings/themes", 0777);
}

//Then fopen it
$file = fopen("data/settings/themes/$themedir/moduleconf.php", "w");
fputs($file, "<?php");

//Call all POST-variables
foreach ($_POST as $variabletosplice => $display_order) {
	//Exclude the Submit-button variable
	if ($variabletosplice != "Submit") {
		list($areaname, $modulename) = explode("|", $variabletosplice);
		//Save the data
		fputs($file, "\n\$space['$areaname']['$modulename'] = \"$display_order\";");
	}
}

//Close the file
fputs($file, "\n?>");
fclose($file);
chmod('data/settings/themes/'.$themedir.'/moduleconf.php', 0777);

//And redirect the user
//redirect('?action=module_addtosite','0');
}
?>