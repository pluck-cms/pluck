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

//First, define that we are in pluck.
define('IN_PLUCK', true);

//Then start session support.
session_start();

//Include security-enhancements.
require_once ('data/inc/security.php');
//Include functions.
require_once ('data/inc/functions.modules.php');
require_once ('data/inc/functions.all.php');
require_once ('data/inc/functions.admin.php');
//Include Translation data.
require_once ('data/inc/variables.all.php');

//Only allow a requirements check if pluck is not yet installed.
if (file_exists('data/settings/install.dat')) {
	$titelkop = 'requirements check';
	include_once ('data/inc/header2.php');
	redirect('login.php', 3);
	show_error($lang['install']['already'], 2);
	include_once ('data/inc/footer.php');
	exit;
}

//Include header
$titelkop = 'requirements check';
include_once ('data/inc/header2.php');

//1: Check for the the required PHP version.
if (version_compare(PHP_VERSION, '5.2.0') !== 1) {
	$messages[] = array(
		'text'  => 'pluck is only officially supported with PHP <strong>5.2.0</strong> or higher. Your version is: <strong>'.PHP_VERSION.'</strong>.',
		'class' => 1
	);
	$error = true;
}

//2: Check for Zlib.
if (!get_extension_funcs('zlib')) {
	$messages[] = array(
		'text'  => '<strong>Zlib</strong> is needed for installing themes and modules. You can however do it manually with FTP.',
		'class' => 1
	);
	$error = true;
}

//3: Check for GD.
if (!get_extension_funcs('gd')) {
	$messages[] = array(
		'text'  => '<strong>GD</strong> is needed for image manipulation. You will not be able to use the albums module.',
		'class' => 1
	);
	$error = true;
}

//4: Check for CURL.
if (!get_extension_funcs('curl')) {
	$messages[] = array(
		'text'  => '<strong>CURL</strong> is used to check for updates. You will have to go to <a href="http://www.pluck-cms.org">pluck-cms.org</a> and check for updates manually.',
		'class' => 2
	);
}

//5: Check for safe_mode.
if (ini_get('safe_mode') === 1) {
	$messages[] = array(
		'text'  => '<strong>safe_mode</strong> is turned on. pluck does not support Safe Mode, and we can\'t guarantee it will work.',
		'class' => 2
	);
}

//6: Check for register_globals.
if (ini_get('register_globals') === 1) {
	$messages[] = array(
		'text'  => '<strong>register_globals</strong> is turned on. pluck works around it, but for safety and performance reasons, it should be off.',
		'class' => 2
	);
}

//7: Check for magic_quotes_gpc.
if (get_magic_quotes_gpc() === 1) {
	$messages[] = array(
		'text'  => '<strong>magic_quotes_gpc</strong> is turned on. pluck does not use MySQL, so it should be turned off for performance reasons.',
		'class' => 2
	);
}

//Are there any messages?
if (isset($messages)) {
	foreach ($messages as $message)
		show_error($message['text'], $message['class']);

		//If there are just one error, pluck should not be installed.
	if (isset($error))
		echo '<br /><p><strong>Not all requirements are met. You should not <a href="install.php">install pluck</a> untill the error(s) have been fixed.</strong></p>';
		//If there are only notices, pluck can be installed.
	else
		echo '<br /><p><strong>All requirements are met, but you should try to fix the notice(s) before you <a href="install.php">install pluck</a>.</strong></p>';
}

//If everything is okay, pluck can be installed.
else
	echo '<p><strong>All requirements and settings are met. You can safely <a href="install.php">install pluck now</a>.</strong></p>';

include_once ('data/inc/footer.php');
?>