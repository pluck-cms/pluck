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

//Include functions
include("data/modules/blog/functions.php");

//Introduction text
echo "<p><b>$lang_blog8</b></p>";

//Add new post button
echo "<div style=\"background-color: #f4f4f4; border: 1px dotted gray; margin: 10px; margin-bottom: 25px;\">
<table>
	<tr>
		<td>
			<img src=\"data/image/newpage.png\" border=\"0\" alt=\"\">
		</td>
		<td>
			<span style=\"font-size: 17pt;\"><a href=\"?module=blog&page=newpost&var=$var\">$lang_blog10</a></span>
		</td>
	</tr>
</table>
</div>";

//Edit categories
echo "<span class=\"kop2\">$lang_blog9</span><br>";
read_blog_posts("data/blog/$var/posts");

echo "<p><a href=\"?module=blog\"><<< $lang_theme12</a></p>";
?>