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

//Check if an image was defined, and if the image exists
if((isset($var)) && (file_exists("data/settings/modules/albums/$var2/$var.php"))) {
//Include the image-information
include("data/settings/modules/albums/$var2/$var.php");
//Replace html-breaks by real ones
$info = str_replace ("<br>","\n", $info);

echo "<form name=\"form1\" method=\"post\" action=\"\">

<b>$lang_install17</b><br>
<input name=\"cont1\" type=\"text\" value=\"$name\"><br>
<b>$lang_albums11</b><br>
<textarea cols=\"50\" rows=\"5\" name=\"cont2\">$info</textarea><br>
<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\">
<input type=\"button\" name=\"Cancel\" value=\"$lang_install14\" onclick=\"javascript: window.location='?module=albums&page=editalbum&var=$var2';\"></form>";

//When the information is posted:
if(isset($_POST['Submit'])) {
//Strip slashes and sanitize data
$cont1 = stripslashes($cont1);
$cont2 = stripslashes($cont2);
$cont1 = str_replace ("\"","\\\"", $cont1);
$cont2 = str_replace ("\"","\\\"", $cont2);
$cont2 = str_replace ("\n","<br>", $cont2);
$cont1 = htmlentities($cont1);
$cont2 = htmlentities($cont2);

$data = "data/settings/modules/albums/$var2/$var.php";     
$file = fopen($data, "w");  
fputs($file, "<?php 
\$name = \"$cont1\";;
\$info = \"$cont2\";
?>");  
fclose($file);

redirect("?module=albums&page=editalbum&var=$var2","0"); }
}
?>