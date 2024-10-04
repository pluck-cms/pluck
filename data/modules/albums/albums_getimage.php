<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) pluck team
 * http://www.pluck-cms.org

 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Run security
foreach ($_GET as $get_value) {
	if (is_array($get_value) || preg_match('/\.\.|[\\\\:<>&="?*]/', $get_value))
		die ('A hacking attempt has been detected. For security reasons, we\'re blocking any code execution.');
}
unset($get_value);

//Define variable
$image = $_GET['image'];

//Then, check for hacking attempts (Remote Code Execution), and block them.
if (strpos($image, 'thumb') === false) {
	if (preg_match('#([.*])([/])([A-Za-z0-9.]{0,11})#', $image, $matches)) {
		if ($image != $matches[0]) {
			unset($image);
			die('A hacking attempt has been detected. For security reasons, we\'re blocking any code execution.');
		}
	}
}

elseif (strpos($image, 'thumb') !== false) {
	if (preg_match('#([.*])([/])thumb([/])([A-Za-z0-9.]{0,11})#', $image, $matches)) {
		if ($image != $matches[0]) {
			unset($image);
			die('A hacking attempt has been detected. For security reasons, we\'re blocking any code execution.');
		}
	}
}

//check if the requested file has the correct extention:
	$imagewhitelist = array('jfif', '.png', '.jpg', '.gif', 'jpeg');  
	if (!in_array(strtolower(substr($image, -4)), $imagewhitelist)){
		die('An attempt to access a none image file is detected. For security reasons, we\'re blocking any code execution.');
	}


//...if no hacking attempts found:
//Check if file exists.
if (file_exists('../../settings/modules/albums/'.$image)) {
	//Generate the image, make sure it doesn't end up in the visitors buffer.
	$imgext =  str_replace('.', '', strtolower(substr($image, -4)));
	$headertext = '';
	switch($imgext)  {
		case 'gif': 	
			$headertext = 'Content-Type: image/gif';
			break;
		case 'jfif':
			$headertext = 'Content-Type: image/jfif';
			break;
		case 'png':
			$headertext = 'Content-Type: image/png';
			break;
		case 'jpg':
			$headertext = 'Content-Type: image/jpeg';
			break;
		case 'jpeg':
			$headertext = 'Content-Type: image/jpeg';
			break;
		}
	
	header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
	header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
	header('Pragma: no-cache');
	header($headertext);
	echo readfile('../../settings/modules/albums/'.$image);
}

//If image doesn't exist, send 404 header.
else
	header('HTTP/1.0 404 Not Found');
?>