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
echo "<p><b>$lang_blog2</b></p>";

//Edit categories
echo "<span class=\"kop2\">$lang_blog3</span><br>";
read_blog_catg("data/blog");

//New category
echo "<br><br><span class=\"kop2\">$lang_blog4</span><br>";
echo "<form method=\"post\"><span class=\"kop4\">$lang_blog5</span><br>
<input name=\"cont1\" type=\"text\" value=\"\"> <input type=\"submit\" name=\"Submit\" value=\"$lang_install13\">
</form>";

//When form is submitted
if(isset($_POST['Submit'])) {
if($cont1) {
$cont1 = stripslashes($cont1);
$cont1 = str_replace ("\"","", $cont1);
$cont1 = str_replace ("'","", $cont1);
$cont1 = str_replace (".","", $cont1);
$cont1 = str_replace ("/","", $cont1);
mkdir("data/blog/$cont1");
mkdir("data/blog/$cont1/posts");
mkdir("data/blog/$cont1/reactions");
chmod("data/blog/$cont1", 0777);
chmod("data/blog/$cont1/posts", 0777);
chmod("data/blog/$cont1/reactions", 0777);
redirect("?module=blog","0"); }
}

echo "<p><a href=\"?action=modules\"><<< $lang_theme12</a></p>";
?>