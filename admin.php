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

//First define that we are in pluck.
define('IN_PLUCK', true);

//Then start session support.
session_start();

//Include security-enhancements.
require_once ('data/inc/security.php');
//Include functions.
require_once ('data/inc/functions.modules.php');
require_once ('data/inc/functions.all.php');
require_once ('data/inc/functions.admin.php');
//Include variables.
require_once ('data/inc/variables.all.php');

//First check if we've installed pluck.
if (!file_exists('data/settings/install.dat')) {
	$titelkop = $lang['install']['not'];
	include_once ('data/inc/header2.php');
	redirect('install.php', 3);
	show_error($lang['install']['not_message'], 1);
	include_once ('data/inc/footer.php');
	exit;
}

//If pluck has been installed, proceed.
else {

	//Then check if we are properly logged in.
	require_once ('data/settings/token.php');
	if (!isset($_SESSION[$token]) || ($_SESSION[$token] != 'pluck_loggedin')) {
		$_SESSION['pluck_before'] = 'admin.php?'.$_SERVER['QUERY_STRING'];
		$titelkop = $lang['login']['not'];
		include_once ('data/inc/header2.php');
		show_error($lang['login']['not_message'], 2);
		redirect('login.php', 3);
		include_once ('data/inc/footer.php');
		exit;
	}

	//Define pages.
	//------------
	if (isset($_GET['action']) && in_array($_GET['action'], array('start', 'credits', 'page', 'editpage', 'images', 'files', 'modules', 'managemodules', 'module_addtosite', 'modulesettings', 'options', 'settings', 'language', 'theme', 'changepass', 'themeinstall', 'themeuninstall', 'theme_delete', 'installmodule', 'trashcan', 'trashcan_empty', 'logout', 'module_delete', 'trash_deleteitem', 'trash_restoreitem', 'trash_viewitem', 'deleteimage', 'deletefile', 'deletepage', 'pageup', 'pagedown', 'writable'))) {
		switch ($_GET['action']) {

			//Page:Logout
			case 'logout':
				//Destroy current session. First get token.
				unset($_SESSION[$token]);
				unset($token);
				$titelkop = $lang['login']['log_out'];
				// We will not use logout.php file
				//$file_to_include = 'logout.php';
				redirect('index.php', 0);
				break;

			//Page:Editpage
			case 'editpage':
				if (isset($_GET['page']))
					$titelkop = $lang['page']['edit'];
				else
					$titelkop = $lang['page']['new'];
				$file_to_include = 'editpage.php';
				break;

			//Additional cases
			//Page:Manage Modules
			case 'managemodules':
				$titelkop = $lang['modules_manage']['title'];
				$file_to_include = 'modules_manage.php';
				break;

			//Page:Module Add To Site
			case 'module_addtosite':
				$titelkop = $lang['modules_addtosite']['title'];
				$file_to_include = 'modules_manage_addtosite.php';
				break;

			//Page:Module settings
			case 'modulesettings':
				$titelkop = $lang['modules_settings']['title'];
				$file_to_include = 'modules_settings.php';
				break;

			//Page:Options:Themeinstall
			case 'themeinstall':
				$titelkop = $lang['theme_install']['title'];
				$file_to_include = 'themeinstall.php';
				break;

			//Page:Options:Themeinstall
			case 'themeuninstall':
				$titelkop = $lang['theme_uninstall']['title'];
				$file_to_include = 'themeuninstall.php';
				break;

			//Page:Options:Theme_Delete
			case 'theme_delete':
				$titelkop = $lang['theme_uninstall']['title'];
				$file_to_include = 'themeuninstall_delete.php';
				break;

			//Page:Options:Moduleinstall
			case 'installmodule':
				$titelkop = $lang['modules_install']['title'];
				$file_to_include = 'modules_install.php';
				break;

			//Page:Empty Trashcan
			case 'trashcan_empty':
				$titelkop = $lang['trashcan']['title'];
				$file_to_include = 'trashcan_empty.php';
				break;

			//Page:Uninstall module
			case 'module_delete':
				$file_to_include = 'modules_manage_delete.php';
				break;

			//Page:Trash_deleteitem
			case 'trash_deleteitem':
				$file_to_include = 'trashcan_deleteitem.php';
				break;

			//Page:Trash_restoreitem
			case 'trash_restoreitem':
				$file_to_include = 'trashcan_restoreitem.php';
				break;

			//Page:Trash_viewitem
			case 'trash_viewitem':
				$titelkop = $lang['trashcan']['view_item'];
				$file_to_include = 'trashcan_viewitem.php';
				break;
			
			//Page:Pageup
			case 'pageup':
				$titelkop = $lang['page']['change_order'];
				$file_to_include = 'pageup.php';
				break;

			//Page:Pagdown
			case 'pagedown':
				$titelkop = $lang['page']['change_order'];
				$file_to_include = 'pagedown.php';
				break;

			//Main case, we are still in in_array function
			case $_GET['action']:
				$titelkop = $lang[$_GET['action']]['title'];
				$file_to_include = $_GET['action'].'.php';
				break;
		}
	}

	//Module pages.
	elseif (isset($_GET['module']))
		require_once ('data/inc/modules_admininclude.php');

	//Unknown pages.
	else {
		header('Location: ?action=start');
		exit;
	}
	
	// Include files
	include_once ('data/inc/header.php');
	if (isset($file_to_include) && file_exists('data/inc/'.$file_to_include))
		include_once ('data/inc/'.$file_to_include);
	include_once ('data/inc/footer.php');
}
?>