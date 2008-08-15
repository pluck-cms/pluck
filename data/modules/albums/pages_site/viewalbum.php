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
$album = $_GET['album'];
$pageback = $_GET['pageback'];

if (!file_exists("data/settings/modules/albums/$album")) {
	echo "<p>$lang_albums18</p>";
}

//If the album exists
else {

//Define how to readout the images
function read_albumimages($dir) {
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

   foreach ($files as $file) {
   //Check if the files are JPG
   list($fdirname, $ext) = explode(".", $file);
	if (($ext == "jpg") || ($ext == "JPG")) {
   $album = $_GET['album'];
   include ("data/settings/modules/albums/$album/$fdirname.php");

				echo "<div class=\"album\" style=\"margin: 15px;\">
							<table>
							<tr>
								<td>
									<a href=\"data/modules/albums/pages_admin/albums_getimage.php?image=$album/$fdirname.jpg\" rel=\"lightbox[album]\" title=\"$name - $info\"><img src=\"data/modules/albums/pages_admin/albums_getimage.php?image=$album/thumb/$fdirname.jpg\" alt=\"$name\" style=\"border: 0px;\" /></a>
								</td>
								<td>
									<span style=\"font-size: 17pt;\">$name</span><br />
									<i>$info</i>
								</td>
								</tr>
							</table>
						</div>";
           }
        }
   }
   closedir($path);
}

//Start reading out those images...
read_albumimages("data/settings/modules/albums/$album");
}
?>

<p><a href="?file=<?php echo $pageback; ?>">&lt;&lt;&lt; <?php echo $lang_theme12; ?></a></p>