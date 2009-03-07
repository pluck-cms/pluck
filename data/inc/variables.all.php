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

//Make sure the file isn't accessed directly.
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit;
}

//Include Translation data.
require_once ('data/settings/langpref.php');
require_once ('data/inc/lang/en.php');
if ($langpref != 'en.php')
	require_once ('data/inc/lang/'.$langpref);

foreach ($module_list as $module) {
	if (file_exists('data/modules/'.$module.'/lang/en.php'))
		require_once ('data/modules/'.$module.'/lang/en.php');
	if ($langpref != 'en.php' && file_exists('data/modules/'.$module.'/lang/'.$langpref))
		require_once ('data/modules/'.$module.'/lang/'.$langpref);
}
unset($module);

//Variables for module programmers.
if (file_exists('data/settings/options.php'))
	require_once ('data/settings/options.php');
if (file_exists('data/settings/themepref.php'))
	require_once ('data/settings/themepref.php');

//Some constants.
define('PLUCK_VERSION', '4.7 alpha');
define('SITE_TITLE', get_sitetitle());
if (file_exists('data/settings/options.php'))
	define('EMAIL', $email);
define('LANG', str_replace('.php', '', $langpref));
define('LANG_FILE', $langpref);
if (file_exists('data/settings/themepref.php')) {
	define('THEME', $themepref);
	define('THEME_DIR', 'data/themes/'.$themepref);
}
define('HOME_PAGE', '?file=kop1.php');

//General variables (included for compatibiltiy with pluck 4.6).
$pluck_version = PLUCK_VERSION;
$site_title = SITE_TITLE;
$site_langfile = LANG_FILE;
$site_lang = LANG;
if (file_exists('data/settings/options.php'))
	$site_email = EMAIL;
if (file_exists('data/settings/themepref.php')) {
	$site_theme = THEME;
	$themedirectory = THEME_DIR;
}
$homepage = HOME_PAGE;

//GETS
if (isset($_GET['action']))
	$action = $_GET['action'];
if (isset($_GET['module']))
	$module = $_GET['module'];
if (isset($_GET['page']))
	$page = $_GET['page'];

//Some GET-variables for general use.
if (isset($_GET['var1']))
	$var1 = $_GET['var1'];
if (isset($_GET['var2']))
	$var2 = $_GET['var2'];
if (isset($_GET['var3']))
	$var3 = $_GET['var3'];

//Some POST-variables for general use.
if (isset($_POST['cont1']))
	$cont1 = $_POST['cont1'];
if (isset($_POST['cont2']))
	$cont2 = $_POST['cont2'];
if (isset($_POST['cont3']))
	$cont3 = $_POST['cont3'];
if (isset($_POST['cont4']))
	$cont4 = $_POST['cont4'];
if (isset($_POST['cont5']))
	$cont5 = $_POST['cont5'];
?>