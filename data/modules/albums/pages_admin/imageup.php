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
	exit();
}

//First, load the functions
include("data/modules/albums/functions.php");

//Check if images exist
if ((file_exists("data/settings/modules/albums/$var2/$var.jpg")) && (file_exists("data/settings/modules/albums/$var2/$var.php")) && (file_exists("data/settings/modules/albums/$var2/thumb/$var.jpg"))) {

//We can't higher kop1.php, so we have to check:
if ($var == "image1") {
echo $lang_updown6;
redirect("?module=albums&page=editalbum&var=$var2","2");
include ("data/inc/footer.php");
exit; }

//Determine the imagenumber
list($filename, $pagenumber) = explode("e", $var);

//Define prefixes
$temp = "_temp";
$kop = "image";
//First make temporary files
rename ("data/settings/modules/albums/$var2/$var.jpg", "data/settings/modules/albums/$var2/$var$temp.jpg");
rename ("data/settings/modules/albums/$var2/$var.php", "data/settings/modules/albums/$var2/$var$temp.php");
rename ("data/settings/modules/albums/$var2/thumb/$var.jpg", "data/settings/modules/albums/$var2/thumb/$var$temp.jpg");

//Then make the higher images one lower
$higherpagenumber = ($pagenumber-1);
rename ("data/settings/modules/albums/$var2/$kop$higherpagenumber.jpg", "data/settings/modules/albums/$var2/$var.jpg");
rename ("data/settings/modules/albums/$var2/$kop$higherpagenumber.php", "data/settings/modules/albums/$var2/$var.php");
rename ("data/settings/modules/albums/$var2/thumb/$kop$higherpagenumber.jpg", "data/settings/modules/albums/$var2/thumb/$var.jpg");

//Finally, give the temp-files its final name
rename ("data/settings/modules/albums/$var2/$var$temp.jpg", "data/settings/modules/albums/$var2/$kop$higherpagenumber.jpg");
rename ("data/settings/modules/albums/$var2/$var$temp.php", "data/settings/modules/albums/$var2/$kop$higherpagenumber.php");
rename ("data/settings/modules/albums/$var2/thumb/$var$temp.jpg", "data/settings/modules/albums/$var2/thumb/$kop$higherpagenumber.jpg");

//Show message
echo $lang_updown3;
}

//Redirect
redirect("?module=albums&page=editalbum&var=$var2","0");
?>