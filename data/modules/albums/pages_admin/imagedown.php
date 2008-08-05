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

//First, load the functions
include("data/modules/albums/functions.php");

//Check if images exist
if ((file_exists("data/settings/modules/albums/$var2/$var.jpg")) && (file_exists("data/settings/modules/albums/$var2/$var.php")) && (file_exists("data/settings/modules/albums/$var2/thumb/$var.jpg"))) {

//Determine the imagenumber
list($filename, $pagenumber) = explode("e", $var);

//Define prefixes
$temp = "_temp";
$kop = "image";
$lowerpagenumber = ($pagenumber+1);

//We can't higher kop1.php, so we have to check:
if (!file_exists("data/settings/modules/albums/$var2/$kop$lowerpagenumber.jpg")) {
echo $lang_updown7;
redirect("?module=albums&page=editalbum&var=$var2","2");
include ("data/inc/footer.php");
exit; }

//First make temporary files
rename("data/settings/modules/albums/$var2/$var.jpg", "data/settings/modules/albums/$var2/$var$temp.jpg");
rename("data/settings/modules/albums/$var2/$var.php", "data/settings/modules/albums/$var2/$var$temp.php");
rename("data/settings/modules/albums/$var2/thumb/$var.jpg", "data/settings/modules/albums/$var2/thumb/$var$temp.jpg");

//Then make the higher images one lower
rename("data/settings/modules/albums/$var2/$kop$lowerpagenumber.jpg", "data/settings/modules/albums/$var2/$var.jpg");
rename("data/settings/modules/albums/$var2/$kop$lowerpagenumber.php", "data/settings/modules/albums/$var2/$var.php");
rename("data/settings/modules/albums/$var2/thumb/$kop$lowerpagenumber.jpg", "data/settings/modules/albums/$var2/thumb/$var.jpg");

//Finally, give the temp-files its final name
rename("data/settings/modules/albums/$var2/$var$temp.jpg", "data/settings/modules/albums/$var2/$kop$lowerpagenumber.jpg");
rename("data/settings/modules/albums/$var2/$var$temp.php", "data/settings/modules/albums/$var2/$kop$lowerpagenumber.php");
rename("data/settings/modules/albums/$var2/thumb/$var$temp.jpg", "data/settings/modules/albums/$var2/thumb/$kop$lowerpagenumber.jpg");

//Show message
echo $lang_updown3;
}

//Redirect
redirect("?module=albums&page=editalbum&var=$var2","0");
?>