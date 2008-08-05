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
echo "<p><b>$lang_blog20</b></p>";

//Define in which dir the reactions are stored
list($reactiondir, $extension) = explode(".", $var);
//and readout and display the reactions
read_blog_reactions("data/blog/$var2/reactions/$reactiondir");

//If form is posted...
//--------------------
if(isset($_POST['Submit'])) {
$data = "data/blog/$var2/reactions/$reactiondir/$cont5";

//replace wrong characters etc.
include("data/inc/page_stripslashes.php");
$cont2 = str_replace("\n", "<br />", $cont2);

$file = fopen($data, "w");
fputs($file, "<?php
\$title = \"$cont1\";
\$name = \"$cont3\";
\$message = \"$cont2\";
\$postdate = \"$cont4\";
?>");
fclose($file);

//Redirect
redirect("?module=blog&page=editreactions&var=$var&var2=$var2","0");
}


//Button to go back
echo "<p><a href=\"?module=blog&page=editblog&var=$var2\"><<< $lang_theme12</a></p>";
?>