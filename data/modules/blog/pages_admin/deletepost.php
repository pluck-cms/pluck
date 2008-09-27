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
include('data/modules/blog/functions.php');

//First, update the post index
if(file_exists('data/settings/modules/blog/post_index.dat')) {
	//Get post index
	$contents = file_get_contents('data/settings/modules/blog/post_index.dat');

	//Check if post index contains post we want to delete, and filter out the post
	if(ereg($var."\n",$contents)) {
		$contents = str_replace($var."\n",'',$contents);
	}
	elseif(ereg("\n".$var,$contents)) {
		$contents = str_replace("\n".$var,'',$contents);
	}
	elseif(ereg($var,$contents)) {
		$contents = str_replace($var,'',$contents);
	}

	//Save updated post index
	$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
	fputs($file,$contents);
	fclose($file);

	//Reload contents of post index in variable
	$contents = file_get_contents('data/settings/modules/blog/post_index.dat');
	//If variable/file is empty, delete post index
	if(empty($contents)) {
		unlink('data/settings/modules/blog/post_index.dat');
	}
}

//Check if post exists, then delete it
if(file_exists('data/settings/modules/blog/posts/'.$var)) {
	//Delete the post
	unlink('data/settings/modules/blog/posts/'.$var);
}

//Redirect
redirect('?module=blog','0');
?>