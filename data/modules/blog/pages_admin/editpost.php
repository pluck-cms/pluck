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

//Include the postinformation
include("data/blog/$var2/posts/$var");

//Generate the menu on the right
echo "<div class=\"rightmenu\">";
echo "$lang_page8 <br>";
read_imagesinpages("images");
echo "</div>";

//Form
echo "<form method=\"post\" action=\"\">
<span class=\"kop2\">$lang_install17</span><br>
<input name=\"kop\" type=\"text\" value=\"$title\"><br><br>

<span class=\"kop2\">$lang_install18</span><br>
<textarea class=\"tinymce\" name=\"tekst\" cols=\"70\" rows=\"20\">$content</textarea><br>";

echo "<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\">
<input type=\"button\" name=\"Cancel\" value=\"$lang_install14\" onclick=\"javascript: window.location='?editblog=$var2';\">
</form>";

//If form is posted...
if(isset($_POST['Submit'])) {
$data = "data/blog/$var2/posts/$var";
include("data/inc/page_stripslashes.php");

$file = fopen($data, "w");
fputs($file, "<?php
\$title = \"$kop\";
\$content = \"$tekst\";
\$postdate = \"$postdate\";
?>");
fclose($file);
redirect("?module=blog&page=editblog&var=$var2","0"); }
?>