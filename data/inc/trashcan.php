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
echo "<p><b>$lang_trash5</b></p>";

//Define how much items we have in the trashcan
include("data/inc/trashcan_count.php");

//Define which image we have to use: a full trashcan or an empty one
if ($trashcan_items == "0") {
$trash_image = "trash-big.png"; }
else {
$trash_image = "trash-full-big.png"; }

//Show some info about the trashcan
echo "<div style=\"background-color: #f4f4f4; border: 1px dotted gray; margin: 20px; margin-bottom: 10px;\">
<table>
	<tr>
		<td>
			<img src=\"data/image/$trash_image\" border=\"0\">
		</td>
		<td>
			$trashcan_items $lang_trash3<br>
			<a href=\"?action=trashcan_empty\" onclick=\"return confirm('$lang_trash11');\">$lang_trash6</a>
		</td>
	</tr>
</table>
</div>";

//Pages menu
echo "<span class=\"kop2\">$lang_kop2</span><br>";
read_pages_trashcan("data/trash/pages");

echo "<br><br>";

//Images menu
echo "<span class=\"kop2\">$lang_trash9</span><br>";
read_images_trashcan("data/trash/images");
?>