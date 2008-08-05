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

//Check if we want to include blog
if ($incblog) {

//Execute the code for every blogcategory
foreach ($incblog as $blogcat => $value) {

//Check if the album exists
if (file_exists("data/blog/$blogcat")) {

//Readout the posts
$dir = "data/blog/$blogcat/posts";
$path = opendir($dir);
while (false !== ($file = readdir($path))) {
    if(($file !== ".") and ($file !== "..")) {
        if(is_file($dir."/".$file))
            $files[]=$file;
        else
            $dirs[]=$file;           
    }
}
if($files) {
natcasesort($files);
$files = array_reverse($files);
foreach ($files as $file) {
//Unset our old variable
unset($files);

//Include Translation data
include ("data/settings/langpref.php");
include ("data/inc/lang/en.php");
include ("data/inc/lang/$langpref");
//Include the post
include("data/blog/$blogcat/posts/$file");

echo "<div class=\"blogpost\" style=\"margin-top: 20px\">
<span class=\"posttitle\" style=\"font-size: 18px;\"><a href=\"?blogpost=$file&amp;cat=$blogcat&amp;pageback=$filetoread\">$title</a></span><br />
<span class=\"postinfo\" style=\"font-size: 10px;\">$lang_blog14 <span style=\"font-weight: bold;\">$blogcat</span> - $postdate</span><br /><br />
$content
<p><a href=\"?blogpost=$file&amp;cat=$blogcat&amp;pageback=$filetoread\">&raquo; $lang_blog23</a></p></div>";
		
}
}
closedir($path);


		}
	}
}
?>