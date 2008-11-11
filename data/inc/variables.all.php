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

//General variables.
$pluck_version = '4.7 alpha';
$site_title = get_sitetitle();
$site_langfile = $langpref;
$site_lang = preg_replace('/.php/','',$site_langfile);
$site_email = $email;
$site_theme = $themepref;

//Set our homepage (where we can redirect if a page doesn't exist or something).
$homepage = '?file=kop1.php';

//Set themedir.
$themedirectory = 'data/themes/'.$site_theme;

//New constants in 4.7.
define(PLUCK_VERSION, '4.7 alpha');
define(TITLE, get_sitetitle());
define(EMAIL, $email);
define(LANG, preg_replace('/.php/','',$langpref));
define(LANG_FILE, $langpref);
define(THEME, $themepref);
define(THEME_DIR, 'data/themes/'.$themepref);
define(HOME_PAGE, '?file=kop1.php');
?>