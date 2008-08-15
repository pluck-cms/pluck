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

//Include Translation data
//----------------
include("data/settings/langpref.php");
include("data/inc/lang/en.php");
include("data/inc/lang/$langpref");

//Variables for module programmers
//----------------
//First, get some information
if(file_exists("data/settings/options.php")) {
	include("data/settings/options.php");
}

//General variables
$pluck_version = "4.6";
$site_title = get_sitetitle();
$site_langfile = $langpref;
$site_lang = preg_replace('/.php/','',$site_langfile);
$site_email = $email;
?>