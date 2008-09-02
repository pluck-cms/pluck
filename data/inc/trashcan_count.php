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

//PAGES
$count_pages_array = glob("data/trash/pages/*.*");
if((isset($count_pages_array)) && (!empty($count_pages_array))) {
	$count_pages = count($count_pages_array);
}

//IMAGES
$count_images_array = glob("data/trash/images/*.*");
if((isset($count_images_array)) && (!empty($count_images_array))) {
	$count_images = count($count_images_array);
}

//Combine all numbers...
$trashcan_items = $count_pages + $count_images;
return $trashcan_items;
?>