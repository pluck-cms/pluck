<?php
//This is a module for pluck, an opensource content management system
//Website: http://www.pluck-cms.org

//MODULE NAME: albums
//DESCRIPTION: this module lets the user create albums with JPEG-pictures to display on the website
//LICENSE: GPLv3
//This module is included with pluck

//Get albumname
if (isset($_GET['album'])) {
	$module_page['viewalbum'] = $_GET['album'];
}

$includepage = "albums_include.php";
?>