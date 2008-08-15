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

//Get form-data
include ("data/settings/options.php");

//Generate the menu on the right
echo "<div class=\"rightmenu\">";
echo "$lang_page8 <br />";
read_imagesinpages("images");
read_pagesinpages("data/settings/pages");
echo "</div>";

//Form
?>
<form method="post" action="">
<span class="kop2"><?php echo $lang_install17; ?></span><br />
<input name="kop" type="text" value="" /><br /><br />

<span class="kop2"><?php echo $lang_install18; ?></span><br />
<textarea class="tinymce" name="tekst" cols="70" rows="20"></textarea><br />
			
<?php
//Modules div
?>
<div style="background-color: #f4f4f4; width: 600px; padding: 5px; border: 1px dotted gray; margin: 5px;">
<table>
<tr>
<td>
<img src="data/image/modules.png" alt="" />
</td>
<td>
<span class="kop3"><?php echo $lang_modules; ?></span><br />
<strong><?php echo $lang_modules16; ?></><br />
<table>
<?php
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

			echo "<tr><td>$module_name</td>";
			echo "<td><select name=\"incmodule[$module_dir]\">";
			echo "<option value=\"0\">$lang_modules6";

			$counting_modules = 1;
			while ($counting_modules <= $number_modules) {

				//Check if this is the current setting
				//...and select the html-option if needed
				$currentsetting = $module_pageinc[$module_dir];
				if ($currentsetting == $counting_modules) {
					echo "<option value=\"$counting_modules\" selected>$counting_modules";
				}
				//...if this is no the current setting, don't select the html-option
				else {
					echo "<option value=\"$counting_modules\">$counting_modules";
				}

				//Higher counting_modules
				$counting_modules++;
			}
		}
?>
		</select></td></tr>
<?php
	}
}
closedir($dir_handle);
?>
</table>
</td>
</tr>
</table>
</div>
<?php
//Options div		
echo "<div style=\"background-color: #f4f4f4; width: 600px; padding: 5px; border: 1px dotted gray; margin: 5px;\">
<table>
<tr>
<td>
<img src=\"data/image/options.png\" border=\"0\" alt=\"\" />
</td>
<td>
<span class=\"kop3\">$lang_contact2</span><br />";

//Display checkbox for the hidepage-option
echo "<input type=\"checkbox\" name=\"hidepage\" value=\"no\" checked> $lang_pagehide1<br />
</td>
</tr>
</table>
</div>";
			
//Submit button
echo "<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\" />
<input type=\"button\" name=\"Cancel\" value=\"$lang_install14\" onclick=\"javascript: window.location='?action=page';\" />
</form>";

//If form is posted...
if(isset($_POST['Submit'])) {

//Check if we want to show the page in the menu
if ($hidepage != "no") {
	$hidepage = "yes";
}

//Now we have to check which filenames are already in use 
if (file_exists("data/settings/pages/kop1.php")) {
	$i = 2;
	$o = 3;
	while ((file_exists("data/settings/pages/kop$i.php")) || (file_exists("data/settings/pages/kop$o.php"))) {
		$i = $i+1;
		$o = $o+1;
	}
	$newfile = "data/settings/pages/kop$i.php";
}
else {
	$newfile = "data/settings/pages/kop1.php";
}

$data = $newfile;
include("data/inc/page_stripslashes.php");

//Sanitize data
$kop = htmlentities($kop);

$file = fopen($data, "w");
fputs($file, "<?php
\$title = \"$kop\";
\$content = \"$tekst\";
\$hidden = \"$hidepage\";
\$description = \"$description\";
\$keywords = \"$keywords\";
\$copyright = \"$copyright\";");

//Save the module information
foreach ($incmodule as $modulename => $order) {
	fputs($file, "\n\$module_pageinc['$modulename'] = \"$order\";");
}

//Close the file
fputs($file, "\n?>");
fclose($file);
//Give the file the right permissions
chmod($data, 0777);

//and redirect user
echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"0; URL=?action=page\">"; }
?>