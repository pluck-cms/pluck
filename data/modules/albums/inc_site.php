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

//Define how we want to show a random image
function getRandomImage($path, $img) {
    if ( $list = getImagesList($path) ) {
        mt_srand( (double)microtime() * 1000000 );
        $num = array_rand($list);
        $img = $list[$num];
    } 
    return $path . $img;
}

function getImagesList($path) {
    $ctr = 0;
    if ( $img_dir = @opendir($path) ) {
        while ( false !== ($img_file = readdir($img_dir)) ) {
            // can add checks for other image file types here
            if ( preg_match("/(\.gif|\.jpg)$/", $img_file) ) {
                $images[$ctr] = $img_file;
                $ctr++;
            }
        }
        closedir($img_dir);
        return $images;
    } 
    return false;
}

//Define how to readout the images
function showalbums($dir) {
   $path = opendir($dir);
   while (false !== ($file = readdir($path))) {
       if(($file !== ".") and ($file !== "..")) {
           if(is_file($dir."/".$file))
               $files[]=$file;
           else
               $dirs[]=$file;           
       }
   }

   if($dirs) {
	natcasesort($dirs);

   foreach ($dirs as $dir) {
   	//Define the directories we want to read out random images
		$path_to_images = "data/settings/modules/albums/$dir/thumb/";
		$default_img = "data/image/image.png";
		//Get current page
		$currentpage = $_GET['file'];

		echo "\n <div class=\"album\" style=\"margin: 15px; padding: 5px;\">
<table>
<tr>
<td>
<img alt=\"\" src=\"";
echo getRandomImage($path_to_images, $default_img);
echo "\" />
</td>
<td><span style=\"font-size: 17pt\"><a href=\"?module=albums&amp;page=viewalbum&amp;album=$dir&amp;pageback=$currentpage\">$dir</a></span>
</td>
</tr>
</table>
</div> \n";
        }
   }
   closedir($path);
}
?>