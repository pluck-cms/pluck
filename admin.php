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

//Include security-enhancements
require("data/inc/security.php");
//Include functions
require("data/inc/functions.all.php");
//Include variables
require("data/inc/variables.all.php");

//First check if we've installed pluck
if ( ! file_exists( 'data/settings/install.dat') ){
	$titelkop = $lang_error1;
	include ('data/inc/header2.php');
	redirect( 'install.php', '3');
	echo $lang_login2;
	include ('data/inc/footer.php');
} 

else {
session_start();
//Then check if we are properly logged in
if ($_SESSION["cmssystem_loggedin"] != "ok") {
$titelkop = $lang_error3;
include ("data/inc/header2.php");
echo "$lang_error4<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"3; URL=login.php\">";
include ("data/inc/footer.php");
exit; }

//Include proper POST/GETs
include("data/inc/post_get.php");

//Variables for module programmers
//----------------
//First, get some information
include("data/settings/options.php");
//General variables
$site_langfile = $langpref;
$site_lang = preg_replace('/.php/','',$site_langfile);
$site_email = $email;

//------------------------
//------------------------
//	Action pages
//------------------------
//------------------------
if($action) {

//Page:Start
if ($action=="start") {
$titelkop = $lang_kop1;
include("data/inc/header.php");
include("data/inc/start.php"); }

//Page:Credits
elseif ($action=="credits") {
$titelkop = $lang_credits;
include("data/inc/header.php");
include("data/inc/credits.php"); }

//Page:Pages
elseif ($action=="page") {
$titelkop = $lang_kop2;
include("data/inc/header.php");
include("data/inc/page.php"); }

//Page:New Page
elseif ($action=="newpage") {
$tinymce = "yes";
$titelkop = $lang_kop11;
include("data/inc/header.php");
include("data/inc/newpage.php"); }

//Page:Manage Images
elseif ($action=="images") {
$titelkop = $lang_kop17;
include("data/inc/header.php");
include("data/inc/images.php"); }

//Page:Modules
elseif ($action=="modules") {
$titelkop = $lang_modules;
include("data/inc/header.php");
include("data/inc/modules.php"); }

//Page:Manage Modules
elseif ($action=="managemodules") {
$titelkop = $lang_modules3;
include("data/inc/header.php");
include("data/inc/modules_manage.php"); }

//Page:Module Add To Site
elseif ($action=="module_addtosite") {
$titelkop = $lang_modules14;
include("data/inc/header.php");
include("data/inc/modules_manage_addtosite.php"); }

//Page:Options
elseif ($action=="options") {
$titelkop = $lang_kop4;
include("data/inc/header.php");
include("data/inc/options.php"); }

//Page:Options:Settings
elseif ($action=="settings") {
$titelkop = $lang_settings;
include("data/inc/header.php");
include("data/inc/settings.php"); }

//Page:Options:Language
elseif ($action=="language") {
$titelkop = $lang_kop14;
include("data/inc/header.php");
include("data/inc/language.php"); }

//Page:Options:Theme
elseif ($action=="theme") {
$titelkop = $lang_kop16;
include("data/inc/header.php");
include("data/inc/theme.php"); }

//Page:Options:Changepass
elseif ($action=="changepass") {
$titelkop = $lang_kop10;
include("data/inc/header.php");
include("data/inc/changepass.php"); }

//Page:Options:Themeinstall
elseif ($action=="themeinstall") {
$titelkop = $lang_theme5;
include("data/inc/header.php");
include("data/inc/themeinstall.php"); }

//Page:Options:Moduleinstall
elseif ($action=="installmodule") {
$titelkop = $lang_modules23;
include("data/inc/header.php");
include("data/inc/modules_install.php"); }

//Page:Trashcan
elseif ($action=="trashcan") {
$titelkop = $lang_trash;
include("data/inc/header.php");
include("data/inc/trashcan.php"); }

//Page:Empty Trashcan
elseif ($action=="trashcan_empty") {
$titelkop = $lang_trash;
include("data/inc/header.php");
include("data/inc/trashcan_empty.php"); }

//Page:Logout
elseif ($action=="logout") {
$titelkop = $lang_kop5;
session_destroy();
include("data/inc/header.php");
include("data/inc/logout.php"); }

//Page:Uninstall module
elseif ($action=="module_delete") {
$titelkop = $lang_modules10;
include("data/inc/header.php");
include("data/inc/modules_manage_delete.php"); }

//Unknown page => Redirect
else {
header("Location: ?action=start");
exit; }
}

//------------------------
//------------------------
//	Editpage pages
//------------------------
//------------------------
elseif($editpage) {
$tinymce = "yes";
$titelkop = $lang_page3;
include("data/inc/header.php");
include("data/inc/editpage.php"); 
}

//------------------------
//------------------------
//	Editmeta pages
//------------------------
//------------------------
elseif($editmeta) {
$titelkop = $lang_meta1;
include("data/inc/header.php");
include("data/inc/page_editmeta.php"); 
}

//------------------------
//------------------------
//	Deletepage pages
//------------------------
//------------------------
elseif($deletepage) {
$titelkop = $lang_trash1;
include("data/inc/header.php");
echo $lang_trash2;
include("data/inc/deletepage.php"); 
}

//------------------------
//------------------------
//	Deleteimage pages
//------------------------
//------------------------
elseif($deleteimage) {
$titelkop = $lang_trash1;
include("data/inc/header.php");
echo $lang_trash2;
include("data/inc/deleteimage.php"); 
}

//------------------------
//------------------------
//	Pageup pages
//------------------------
//------------------------
elseif($pageup) {
$titelkop = $lang_updown1;
include("data/inc/header.php");
include("data/inc/pageup.php"); 
}

//------------------------
//------------------------
//	Pagedown pages
//------------------------
//------------------------
elseif($pagedown) {
$titelkop = $lang_updown1;
include("data/inc/header.php");
include("data/inc/pagedown.php"); 
}

//------------------------
//------------------------
//	Trash_viewitem pages
//------------------------
//------------------------
elseif($trash_viewitem) {
$titelkop = $lang_trash7;
include("data/inc/header.php");
include("data/inc/trashcan_viewitem.php");
}

//------------------------
//------------------------
//	Trash_restoreitem pages
//------------------------
//------------------------
elseif($trash_restoreitem) {
$titelkop = $lang_trash10;
include("data/inc/header.php");
include("data/inc/trashcan_restoreitem.php");
}

//------------------------
//------------------------
//	Trash_deleteitem pages
//------------------------
//------------------------
elseif($trash_deleteitem) {
$titelkop = $lang_trash8;
include("data/inc/header.php");
include("data/inc/trashcan_deleteitem.php");
}

//------------------------
//	Unknown pages
//------------------------
else {
if ((!$modulestart) && (!$module)) {
header("Location: ?action=start");
exit; }
}

//-----------------------
//Include module pages
//-----------------------
include("data/inc/modules_admininclude.php");

//Include footer
include ("data/inc/footer.php");
}
?>