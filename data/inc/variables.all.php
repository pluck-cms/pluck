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
if((!ereg('index.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error.
    echo 'access denied';
    //Block all other code.
    exit();
}

//Include Translation data.
require_once ('data/settings/langpref.php');
require_once ('data/inc/lang/en.php');
if ($langpref != 'en.php')
	require_once ('data/inc/lang/'.$langpref);

//Variables for module programmers.
if (file_exists('data/settings/options.php'))
	require_once ('data/settings/options.php');
if (file_exists('data/settings/themepref.php'))
	require_once ('data/settings/themepref.php');

//Some constants.
define('PLUCK_VERSION', '4.7 alpha');
define('SITE_TITLE', get_sitetitle());
define('EMAIL', $email);
define('LANG', preg_replace('/.php/','',$langpref));
define('LANG_FILE', $langpref);
define('THEME', $themepref);
define('THEME_DIR', 'data/themes/'.$themepref);
define('HOME_PAGE', '?file=kop1.php');

//General variables (included for compatibiltiy with pluck 4.6)
$pluck_version = PLUCK_VERSION;
$site_title = SITE_TITLE;
$site_langfile = LANG_FILE;
$site_lang = LANG;
$site_email = EMAIL;
$site_theme = THEME;
$themedirectory = THEME_DIR;
$homepage = HOME_PAGE;

//GETS
if (isset($_GET['action']))
	$action = $_GET['action'];
if (isset($_GET['editpage']))
	$editpage = $_GET['editpage'];
if (isset($_GET['deletepage']))
	$deletepage = $_GET['deletepage'];
if (isset($_GET['deleteimage']))
	$deleteimage = $_GET['deleteimage'];
if (isset($_GET['editmeta']))
	$editmeta = $_GET['editmeta'];
if (isset($_GET['pageup']))
	$pageup = $_GET['pageup'];
if (isset($_GET['pagedown']))
	$pagedown = $_GET['pagedown'];
if (isset($_GET['trash_viewitem']))
	$trash_viewitem = $_GET['trash_viewitem'];
if (isset($_GET['trash_restoreitem']))
	$trash_restoreitem = $_GET['trash_restoreitem'];
if (isset($_GET['trash_deleteitem']))
	$trash_deleteitem = $_GET['trash_deleteitem'];
if (isset($_GET['modulestart']))
	$modulestart = $_GET['modulestart'];
if (isset($_GET['module']))
	$module = $_GET['module'];
if (isset($_GET['page']))
	$page = $_GET['page'];
if (isset($_GET['cat']))
	$cat = $_GET['cat'];

//Some GET-variables for general use
if (isset($_GET['var1']))
	$var1 = $_GET['var1'];
if (isset($_GET['var2']))
	$var1 = $_GET['var2'];
if (isset($_GET['var3']))
	$var3 = $_GET['var3'];

//POSTS
if (isset($_POST['kop']))
	$kop = $_POST['kop'];
if (isset($_POST['tekst']))
	$tekst = $_POST['tekst'];
if (isset($_POST['back']))
	$back = $_POST['back'];
if (isset($_POST['txt']))
	$txt = $_POST['txt'];
if (isset($_POST['type']))
	$type = $_POST['type'];
if (isset($_POST['cont']))
	$cont = $_POST['cont'];
if (isset($_POST['password']))
	$password = $_POST['password'];
if (isset($_POST['password2']))
	$password2 = $_POST['password2'];
if (isset($_POST['chosen_lang']))
	$chosen_lang = $_POST['chosen_lang'];
if (isset($_POST['email']))
	$email = $_POST['email'];
if (isset($_POST['email2']))
	$email2 = $_POST['email2'];
if (isset($_POST['contactform']))
	$contactform = $_POST['contactform'];
if (isset($_POST['hidepage']))
	$hidepage = $_POST['hidepage'];
if (isset($_POST['album_name']))
	$album_name = $_POST['album_name'];
if (isset($_POST['quality']))
	$quality = $_POST['quality'];
if (isset($_POST['incmodule']))
	$incmodule = $_POST['incmodule'];

//Some variables for general use
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