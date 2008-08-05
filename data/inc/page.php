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
echo "<p><b>$lang_page1</b></p>";

//Add newpage button
echo "<div style=\"background-color: #f4f4f4; border: 1px dotted gray; margin: 20px; margin-bottom: 10px;\">
<table>
	<tr>
		<td>
			<img src=\"data/image/newpage.png\" border=\"0\" alt=\"\">
		</td>
		<td>
			<span style=\"font-size: 17pt;\"><a href=\"?action=newpage\">$lang_page2</a></span>
		</td>
	</tr>
</table>
</div>";

//Add image button
echo "<div style=\"background-color: #f4f4f4; border: 1px dotted gray; margin: 20px; margin-top: 10px;\">
<table>
	<tr>
		<td>
			<img src=\"data/image/image.png\" border=\"0\" alt=\"\">
		</td>
		<td>
			<span style=\"font-size: 17pt;\"><a href=\"?action=images\">$lang_kop17</a></span>
		</td>
	</tr>
</table>
</div>";

//Show pages
read_pages("data/settings/pages");
?>