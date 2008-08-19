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

//Variables for module programmers
//----------------
//Filename of current page
if (isset($_GET['file'])) {
	$current_page_filename = $_GET['file'];
}
//Name of directory of current module
if (isset($_GET['module'])) {
	$current_module_dir = $_GET['module'];
}
//Name of current module page
if (isset($_GET['page'])) {
	$current_module_page = $_GET['page'];
}
$page_title = get_pagetitle(); //Also works for modules

?>