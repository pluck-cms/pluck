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
error_reporting(E_ALL|E_STRICT);
//Set timezone
date_default_timezone_set('UTC');

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
//FIXME: ereg is slow, use preg_match or strpos.
foreach ($_GET as $get_key => $get_value) {
	if ((ereg("[\]+", $get_value)) || (ereg("\.\.", $get_value)) || (ereg("/", $get_value)) || (ereg(":", $get_value)) || (ereg("<", $get_value)) || (ereg(">", $get_value)) || (ereg("=", $get_value)) || (ereg(";", $get_value)) || (ereg(")", $get_value))) {
		die ("A hacking attempt has been detected. For security reasons, we're blocking any code execution.");
	}
}
//--------------------------------

//Undo magic quotes. Mostly taken from a php.net comment.
ini_set('magic_quotes_sybase', 0);
ini_set('magic_quotes_runtime', 0);
if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() == 1) {
	foreach($_GET as $k => $v) $_GET[$k] = stripslashes($v);
	foreach($_POST as $k => $v) $_POST[$k] = stripslashes($v);
	foreach($_COOKIE as $k => $v) $_COOKIE[$k] = stripslashes($v);
}
?>