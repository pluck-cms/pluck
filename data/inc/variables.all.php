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
defined('IN_PLUCK') or exit('Access denied!');

//Include Translation data.
require_once ('data/settings/langpref.php');
require_once ('data/inc/lang/en.php');
if ($langpref != 'en.php')
	require_once ('data/inc/lang/'.$langpref);

if (isset($module_list)) {
	foreach ($module_list as $module) {
		if (file_exists('data/modules/'.$module.'/lang/en.php'))
			require_once ('data/modules/'.$module.'/lang/en.php');
		if ($langpref != 'en.php' && file_exists('data/modules/'.$module.'/lang/'.$langpref))
			require_once ('data/modules/'.$module.'/lang/'.$langpref);
	}
	unset($module);
}

//Variables for module programmers.
if (file_exists('data/settings/options.php'))
	require_once ('data/settings/options.php');
if (file_exists('data/settings/themepref.php'))
	require_once ('data/settings/themepref.php');

//Some constants.
define('PLUCK_VERSION', '4.7 beta');
define('SITE_URL', 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']));
define('SITE_TITLE', get_sitetitle());
if (file_exists('data/settings/options.php'))
	define('EMAIL', $email);
define('LANG', str_replace('.php', '', $langpref));
define('LANG_FILE', $langpref);
define('PAGE_DIR', 'data/settings/pages');
if (file_exists('data/settings/themepref.php')) {
	define('THEME', $themepref);
	define('THEME_DIR', 'data/themes/'.$themepref);
}
if (isset($direction) && $direction = 'rtl')
	define('DIRECTION_RTL', true);
else
	define('DIRECTION_RTL', false);

if (isset($_GET['module'])) {
	define('MODULE_DIR', 'data/modules/'.$_GET['module']);
	define('MODULE_SETTINGS_DIR', 'data/settings/modules/'.$_GET['module']);
}

if (file_exists('data/settings/pages')) {
	$homepage = read_dir_contents('data/settings/pages', 'files');

	if ($homepage != false) {
		sort($homepage, SORT_NUMERIC);
		$homepage = get_page_seoname($homepage[0]);
	}

	//FIXME: Is there a better way to do this?
	else
		$homepage = '404';

	run_hook('const_home_page', array(&$homepage));
	define('HOME_PAGE', '?file='.$homepage);
	unset($homepage);
}

//Some GET-variables for general use.
if (isset($_GET['var1']))
	$var1 = $_GET['var1'];
if (isset($_GET['var2']))
	$var2 = $_GET['var2'];
if (isset($_GET['var3']))
	$var3 = $_GET['var3'];
if (isset($_GET['var4']))
	$var4 = $_GET['var4'];
if (isset($_GET['var5']))
	$var5 = $_GET['var5'];

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
if (isset($_POST['cont6']))
	$cont6 = $_POST['cont6'];
if (isset($_POST['cont7']))
	$cont7 = $_POST['cont7'];
if (isset($_POST['cont8']))
	$cont8 = $_POST['cont8'];
if (isset($_POST['cont9']))
	$cont9 = $_POST['cont9'];
if (isset($_POST['cont10']))
	$cont10 = $_POST['cont10'];
?>