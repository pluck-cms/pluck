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

//Generate the menu on the right
echo "<div class=\"rightmenu\">";
echo "$lang_page8 <br>";
read_imagesinpages("images");
echo "</div>";

//Form
echo "<form method=\"post\" action=\"\">
<span class=\"kop2\">$lang_install17</span><br>
<input name=\"kop\" type=\"text\" value=\"\"><br><br>

<span class=\"kop2\">$lang_install18</span><br>
<textarea class=\"tinymce\" name=\"tekst\" cols=\"70\" rows=\"20\"></textarea><br>";

echo "<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\">
<input type=\"button\" name=\"Cancel\" value=\"$lang_install14\" onclick=\"javascript: window.location='?editblog=$var';\">
</form>";

//If form is posted...
if(isset($_POST['Submit'])) {

//Now we have to check which filenames are already in use 
if (file_exists("data/blog/$var/posts/post1.php")) {
$i = 2;
$o = 3;
while ((file_exists("data/blog/$var/posts/post$i.php")) || (file_exists("data/blog/$var/posts/post$o.php"))) {
$i = $i+1;
$o = $o+1; }
$newfile = "data/blog/$var/posts/post$i.php";
$filename = "post$i"; }
else {
$newfile = "data/blog/$var/posts/post1.php";
$filename = "post1";
}
$data = $newfile;
include("data/inc/page_stripslashes.php");

//Determine the date
$day = date("d");
$month = date("m");
$year = date("Y");
$time = date("H:i");
$date = "$month-$day-$year, $time";

$file = fopen($data, "w");
fputs($file, "<?php
\$title = \"$kop\";
\$content = \"$tekst\";
\$postdate = \"$date\";
?>");
fclose($file);
mkdir ("data/blog/$var/reactions/$filename");
chmod ("data/blog/$var/reactions/$filename", 0777);

redirect("?module=blog&page=editblog&var=$var","0"); }
?>