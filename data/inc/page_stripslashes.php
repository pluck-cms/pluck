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

//For pages with TinyMCE
if (isset($tinymce) && $tinymce == "yes") {
	$tekst = stripslashes($tekst);
	$tekst = str_replace("\"", "\\\"", $tekst);
	$kop = stripslashes($kop);
	$kop = str_replace("\"", "\\\"", $kop);
	if (isset($keywords)) {
		$keywords = stripslashes($keywords);
		$keywords = str_replace("\"", "\\\"", $keywords);
	}
	if (isset($description)) {
		$description = stripslashes($description);
		$description = str_replace("\"", "\\\"", $description);
	}
}

//For editmeta
elseif (isset($editmeta)) {
	$title = stripslashes($title);
	$title = str_replace("\"", "\\\"", $title);
	$content = stripslashes($content);
	$content = str_replace("\"", "\\\"", $content);
	if (isset($cont1)) {
		$cont1 = stripslashes($cont1);
		$cont1 = str_replace("\"", "\\\"", $cont1);
	}
	if (isset($cont2)) {
		$cont2 = stripslashes($cont2);
		$cont2 = str_replace("\"", "\\\"", $cont2);
	}
}

//For other instances
else {
	if (isset($cont1)) {
		$cont1 = stripslashes($cont1);
		$cont1 = str_replace("\"", "\\\"", $cont1);
	}
	if (isset($cont2)) {
		$cont2 = stripslashes($cont2);
		$cont2 = str_replace("\"", "\\\"", $cont2);
	}
	if (isset($cont3)) {
		$cont3 = stripslashes($cont3);
		$cont3 = str_replace("\"", "\\\"", $cont3);
	}
	if (isset($cont4)) {
		$cont4 = stripslashes($cont4);
		$cont4 = str_replace("\"", "\\\"", $cont4);
	}
	if (isset($cont5)) {
		$cont5 = stripslashes($cont5);
		$cont5 = str_replace("\"", "\\\"", $cont5);
	}
}
?>