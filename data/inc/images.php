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
echo "<p><b>$lang_image1</b></p>

<div style=\"background-color: #f4f4f4; padding: 5px; width: 500px; margin-top: 15px; margin-bottom: 20px; border: 1px dotted gray;\">
<table>
<tr><td>
	<img src=\"data/image/image.png\" border=\"0\" alt=\"\">
</td>
<td>
	<span class=\"kop2\">$lang_image8</span><br>
	<form name=\"form1\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">
	<input type=\"file\" name=\"imagefile\">
	<input type=\"submit\" name=\"Submit\" value=\"$lang_image9\"></form>
</td></tr></table>
</div>";

if(isset($_POST['Submit'])) {
//Check if the file is JPG, PNG or GIF
if (($_FILES['imagefile']['type'] == "image/pjpeg") || ($_FILES['imagefile']['type'] == "image/jpeg") || ($_FILES['imagefile']['type'] == "image/png") || ($_FILES['imagefile']['type'] == "image/gif")) {

//Strip all the spaces from the filename
$filename = $_FILES['imagefile']['name'];
$filename = str_replace (" ","", $filename);

copy ($_FILES['imagefile']['tmp_name'], "images/$filename")
	or die ("<br>$lang_image2");
chmod("images/$filename", 0666);

echo "<div style=\"background-color: #f4f4f4; border: 1px dotted gray; margin: 20px;\"><b>$lang_image3</b> $filename";
echo "<br><b>$lang_image4</b> ".$_FILES['imagefile']['size']." bytes";
echo "<br><b>$lang_image5</b> ".$_FILES['imagefile']['type']."";
echo "<br><b>$lang_image6</b></div>";
	}
}

//Display list of uploaded pictures
echo "<span class=\"kop2\">$lang_image7</span><br>";
//Show the uploaded images
read_images("images");

echo "<p><a href=\"?action=page\"><<< $lang_theme12</a></p>";
?>