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

 * This is a file that checks for hacking attempts and blocks them.
*/

//Error reporting
//error_reporting(E_ALL|E_STRICT);

//--------------------------------
//Register Globals
//If Register Globals are ON, unset injected variables
if(isset($_REQUEST)) {
	foreach ($_REQUEST as $key => $value) { 
		if (isset($GLOBALS[$key])) {
			unset($GLOBALS[$key]); 
		}
	}
}
//--------------------------------

//--------------------------------
//Cross Site Scripting, Remote File Inclusion, etc.
//Check for strange characters in $_GET keys
//All keys with or "/" or ".." or ":" or "<" or ">" or "=" or ";" or ")" are blocked, so that it's virtually impossible to inject any HTML-code, or external websites
foreach ($_GET as $get_key => $get_value) {
	if ((ereg("[\]+", $get_value)) || (ereg("\.\.", $get_value)) || (ereg("/", $get_value)) || (ereg(":", $get_value)) || (ereg("<", $get_value)) || (ereg(">", $get_value)) || (ereg("=", $get_value)) || (ereg(";", $get_value)) || (ereg(")", $get_value))) {
		die ("A hacking attempt has been detected. For security reasons, we're blocking any code execution.");
	}
}
//--------------------------------

?>