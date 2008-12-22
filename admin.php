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
require_once ('data/inc/security.php');
//Include functions
require_once ('data/inc/functions.all.php');
require_once ('data/inc/functions.admin.php');
//Include variables
require_once ('data/inc/variables.all.php');

//First check if we've installed pluck
if (!file_exists('data/settings/install.dat')) {
	$titelkop = $lang_error1;
	include_once ('data/inc/header2.php');
	redirect('install.php', 3);
	echo $lang_login2;
	include_once ('data/inc/footer.php');
}

else {
	session_start();
	//Then check if we are properly logged in
	if ($_SESSION ['cmssystem_loggedin'] != 'ok') {
		$titelkop = $lang_error3;
		include_once ('data/inc/header2.php');
		redirect('login.php', 3);
		echo $lang_error4;
		include_once ('data/inc/footer.php');
		exit;
	}

	//Define pages
	//------------
	if (isset($action)) {
		switch ($action) {
			//Page:Start
			case 'start':
				$titelkop = $lang_kop1;
				include_once ('data/inc/header.php');
				include_once ('data/inc/start.php');
				break;

			//Page:Credits
			case 'credits':
				$titelkop = $lang_credits;
				include_once ('data/inc/header.php');
				include_once ('data/inc/credits.php');
				break;

			//Page:Pages
			case 'page':
				$titelkop = $lang_kop2;
				include_once ('data/inc/header.php');
				include_once ('data/inc/page.php');
				break;

			//Page:New Page
			case 'newpage':
				$tinymce = 'yes';
				$titelkop = $lang_kop11;
				include_once ('data/inc/header.php');
				include_once ('data/inc/newpage.php');
				break;

			//Page:Manage Images
			case 'images':
				$titelkop = $lang_kop17;
				include_once ('data/inc/header.php');
				include_once ('data/inc/images.php');
				break;

			//Page:Modules
			case 'modules':
				$titelkop = $lang_modules;
				include_once ('data/inc/header.php');
				include_once ('data/inc/modules.php');
				break;

			//Page:Manage Modules
			case 'managemodules':
				$titelkop = $lang_modules3;
				include_once ('data/inc/header.php');
				include_once ('data/inc/modules_manage.php');
				break;

			//Page:Module Add To Site
			case 'module_addtosite':
				$titelkop = $lang_modules14;
				include_once ('data/inc/header.php');
				include_once ('data/inc/modules_manage_addtosite.php');
				break;

			//Page:Options
			case 'options':
				$titelkop = $lang_kop4;
				include_once ('data/inc/header.php');
				include_once ('data/inc/options.php');
				break;

			//Page:Options:Settings
			case 'settings':
				$titelkop = $lang_settings;
				include_once ('data/inc/header.php');
				include_once ('data/inc/settings.php');
				break;

			//Page:Options:Language
			case 'language':
				$titelkop = $lang_kop14;
				include_once ('data/inc/header.php');
				include_once ('data/inc/language.php');
				break;

			//Page:Options:Theme
			case 'theme':
				$titelkop = $lang_kop16;
				include_once ('data/inc/header.php');
				include_once ('data/inc/theme.php');
				break;

			//Page:Options:Changepass
			case 'changepass':
				$titelkop = $lang_kop10;
				include_once ('data/inc/header.php');
				include_once ('data/inc/changepass.php');
				break;

			//Page:Options:Themeinstall
			case 'themeinstall':
				$titelkop = $lang_theme5;
				include_once ('data/inc/header.php');
				include_once ('data/inc/themeinstall.php');
				break;

			//Page:Options:Moduleinstall
			case 'installmodule':
				$titelkop = $lang_modules23;
				include_once ('data/inc/header.php');
				include_once ('data/inc/modules_install.php');
				break;

			//Page:Trashcan
			case 'trashcan':
				$titelkop = $lang_trash;
				include_once ('data/inc/header.php');
				include_once ('data/inc/trashcan.php');
				break;

			//Page:Empty Trashcan
			case 'trashcan_empty':
				$titelkop = $lang_trash;
				include_once ('data/inc/header.php');
				include_once ('data/inc/trashcan_empty.php');
				break;

			//Page:Logout
			case 'logout':
				$titelkop = $lang_kop5;
				session_destroy();
				include_once ('data/inc/header.php');
				include_once ('data/inc/logout.php');
				break;

			//Page:Uninstall module
			case 'module_delete':
				$titelkop = $lang_modules10;
				include_once ('data/inc/header.php');
				include_once ('data/inc/modules_manage_delete.php');
				break;

			//Page:Trash_deleteitem
			case 'trash_deleteitem':
				$titelkop = $lang_trash8;
				include_once ('data/inc/header.php');
				include_once ('data/inc/trashcan_deleteitem.php');
				break;
				
			//Page:Trash_restoreitem
			case 'trash_restoreitem':
				$titelkop = $lang_trash10;
				include_once ('data/inc/header.php');
				include_once ('data/inc/trashcan_restoreitem.php');
				break;

			//Page:Trash_viewitem
			case 'trash_viewitem':
				$titelkop = $lang_trash7;
				include_once ('data/inc/header.php');
				include_once ('data/inc/trashcan_viewitem.php');
				break;

			//Page:Deleteimage
			case 'deleteimage':
				$titelkop = $lang_trash1;
				include_once ('data/inc/header.php');
				echo $lang_trash2;
				include_once ('data/inc/deleteimage.php');
				break;

			//Unknown page => Redirect
			default:
				header('Location: ?action=start');
				exit;
				break;
		}
	}


	//Editpage pages
	elseif (isset($editpage)) {
		$tinymce = 'yes';
		$titelkop = $lang_page3;
		include_once ('data/inc/header.php');
		include_once ('data/inc/editpage.php');
	}

	//Editmeta pages
	elseif (isset($editmeta)) {
		$titelkop = $lang_meta1;
		include_once ('data/inc/header.php');
		include_once ('data/inc/page_editmeta.php');
	}

	//Deletepage pages
	elseif (isset($deletepage)) {
		$titelkop = $lang_trash1;
		include_once ('data/inc/header.php');
		echo $lang_trash2;
		include_once ('data/inc/deletepage.php');
	}

	//Pageup pages
	elseif (isset($pageup)) {
		$titelkop = $lang_updown1;
		include_once ('data/inc/header.php');
		include_once ('data/inc/pageup.php');
	}

	//Pagedown pages
	elseif (isset($pagedown)) {
		$titelkop = $lang_updown1;
		include_once ('data/inc/header.php');
		include_once ('data/inc/pagedown.php');
	}

	//Unknown pages
	else {
		if (!isset($module)) {
			header('Location: ?action=start');
			exit;
		}
	}

	//Include module pages
	include_once ('data/inc/modules_admininclude.php');

	//Include footer
	include_once ('data/inc/footer.php');
}
?>