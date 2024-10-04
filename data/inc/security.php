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

 * This is a file that checks for hacking attempts and blocks them.
*/

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');

/*
 * Version constant
 * This constant is defined here to allow for hooks to be added inside modules.
 * For other constants, see variables.all.php and variables.site.php
 */
define('PLUCK_VERSION', '4.7.19 dev');

//Error reporting default is (E_ALL ^ E_NOTICE) - but use server configuration for production environment
//Uncomment next line for development (shows every possible error)
//error_reporting(-1);

//Set default timezone.
date_default_timezone_set('UTC');

/* Register Globals.
 * If Register Globals are ON, unset injected variables.
 */
if (isset($_REQUEST)) {
	foreach ($_REQUEST as $key => $value) {
		if (isset($GLOBALS[$key]))
			unset($GLOBALS[$key]);
	}
	unset($key);
}

/* Cross Site Scripting, Remote File Inclusion, etc.
 * First check if $_GET values are arrays.
 * Then check for strange characters in $_GET values.
 * All values with ".." or "\" or ":" or "<" or ">" or "&" or "=" or '"' or "?" or "*" are blocked, so that it's virtually impossible to inject any HTML-code, or external websites.
 * TODO: This is just a quick and dirty fix for the actual problem!
 */
foreach ($_GET as $get_value) {
	if (is_array($get_value) || preg_match('/\.\.|[\\\\:<>&="?*]/', $get_value))
		die ('A hacking attempt has been detected. For security reasons, we\'re blocking any code execution.');
}
unset($get_value);

/*
 * Undo magic quotes; http://php.net/manual/en/security.magicquotes.disabling.php.
 */
ini_set('magic_quotes_sybase', 0);
ini_set('magic_quotes_runtime', 0);
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() === 1) {
	function stripslashes_deep($value) {
		$value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
		return $value;
	}

	$_POST = array_map('stripslashes_deep', $_POST);
	$_GET = array_map('stripslashes_deep', $_GET);
	$_COOKIE = array_map('stripslashes_deep', $_COOKIE);
	$_REQUEST = array_map('stripslashes_deep', $_REQUEST);
}

/*
 * Check if we have a saved security token. If not, generate one and save it.
 */
if (!file_exists('data/settings/token.php') && is_writable('data/settings')) {
	$token = hash('sha512', uniqid(mt_rand(), true));
	$data = fopen('data/settings/token.php', 'w');
	fputs($data, '<?php $token = \''.$token.'\'; ?>');
	fclose($data);
	chmod('data/settings/token.php', 0777);
	unset($token);
}
?>
