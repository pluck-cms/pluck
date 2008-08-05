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

//Predefined variable
$blogpost = $_GET['blogpost'];
$cat = $_GET['cat'];
$pageback = $_GET['pageback'];
list($reactiondir, $extension) = explode(".", $blogpost);

//Include the blogpost
include("data/blog/$cat/posts/$blogpost");

echo "<div style=\"margin-bottom: 20px;\">
<span class=\"postinfo\" style=\"font-size: 10px;\">$lang_blog14 <span style=\"font-weight: bold;\">$cat</span> - $postdate</span><br /><br />
$content
</div>";

//Then show form the reactions
echo "<span style=\"font-size: 19px\">$lang_blog16</span>";

//Define the function to readout the reactions
function read_reactions($dir) {
   $path = opendir($dir);
   while (false !== ($file = readdir($path))) {
       if(($file !== ".") and ($file !== "..")) {
           if(is_file($dir."/".$file))
               $files[]=$file;
           else
               $dirs[]=$dir."/".$file;    
       }
   }
   if($dirs) {
   echo "";
   }
   if($files) {
       natcasesort($files);
		 $files = array_reverse($files);
       foreach ($files as $file) {
			  $blogpost = $_GET['blogpost'];
			  $cat = $_GET['cat'];
			  list($reactiondir, $extension) = explode(".", $blogpost);
			  
			  include("data/blog/$cat/reactions/$reactiondir/$file");
           //Include Translation data
			  include ("data/settings/langpref.php");
			  include ("data/inc/lang/en.php");
			  include ("data/inc/lang/$langpref");
			  
			  echo "<div class=\"blogpost\" style=\"margin-bottom: 15px; margin-top: 5px;\">
			  <span class=\"posttitle\" style=\"font-size: 16px;\">$title</span><br />
			  <span class=\"postinfo\" style=\"font-size: 10px;\">$lang_blog18 <span style=\"font-weight: bold;\">$name</span> - $postdate</span><br />
			  $message
			  </div>";
			  }
   }
   closedir($path);
}
//and then include the reactions
read_reactions("data/blog/$cat/reactions/$reactiondir/");

//...and show a form to place new reactions
echo "<form method=\"post\" action=\"\" style=\"margin-top: 5px; margin-bottom: 15px;\"><div>
$lang_blog17 <br /><input name=\"title\" type=\"text\" value=\"\" /><br />
$lang_contact3 <br /><input name=\"name\" type=\"text\" value=\"\" /><br />
$lang_contact5 <br /><textarea name=\"message\" rows=\"7\" cols=\"45\"></textarea><br />
<input type=\"submit\" name=\"Submit\" value=\"$lang_contact10\" />
</div></form>";

//If form is posted...
if(isset($_POST['Submit'])) {
//First fetch our posted variables
$title = $_POST['title'];
$name = $_POST['name'];
$message = $_POST['message'];

//Then check if everything has been filled in
if (($title) && ($name) && ($message)) {

//Check for HTML, and eventually block it
if ((ereg("<", $title)) || (ereg(">", $title)) || (ereg("<", $name)) || (ereg(">", $name)) || (ereg("<", $message)) || (ereg(">", $message))) {
echo "<span style=\"color: red;\">$lang_blog22</span>"; }

//If no HTML is present
else {

//Then delete unwanted characters etc.
$title = stripslashes($title);
$title = str_replace("\"", "\\\"", $title);
$name = stripslashes($name);
$name = str_replace("\"", "\\\"", $name);
$message = stripslashes($message);
$message = str_replace("\"", "\\\"", $message);
$message = str_replace("\n", "<br />", $message);

//Now we have to check which filenames are already in use 
if (file_exists("data/blog/$cat/reactions/$reactiondir/message1.php")) {
$i = 2;
$o = 3;
while ((file_exists("data/blog/$cat/reactions/$reactiondir/message$i.php")) || (file_exists("data/blog/$cat/reactions/$reactiondir/message$o.php"))) {
$i = $i+1;
$o = $o+1; }
$newfile = "data/blog/$cat/reactions/$reactiondir/message$i.php"; }
else {
$newfile = "data/blog/$cat/reactions/$reactiondir/message1.php";
}
$data = $newfile;

//Determine the date
$day = date("d");
$month = date("m");
$year = date("Y");
$time = date("H:i");
$date = "$month-$day-$year, $time";

$file = fopen($data, "w");
fputs($file, "<?php
\$title = \"$title\";
\$name = \"$name\";
\$message = \"$message\";
\$postdate = \"$date\";
?>");
fclose($file);

//Encode items so that blogname can be placed in URL
$pageback = urlencode($pageback);
$blogpost = urlencode($blogpost);
$cat = urlencode($cat);
echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"0; URL=?blogpost=$blogpost&amp;cat=$cat&amp;pageback=$pageback\">";
	}
}

//...or if not all fields are filled
else {
echo "<span style=\"color: red;\">$lang_contact6</span>"; }
}

//Show a link to go back
echo "<p><a href=\"?file=$pageback\">&lt;&lt;&lt; $lang_theme12</a></p>";
?>