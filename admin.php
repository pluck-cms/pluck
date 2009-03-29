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

//Load all the modules, so we can use hooks.
//This has to be done before anything else.
$path = opendir('data/modules');
while (false !== ($dir = readdir($path))) {
	if ($dir != '.' && $dir != '..') {
		if (is_dir('data/modules/'.$dir))
			$modules[] = $dir;
	}
}
closedir($path);

foreach ($modules as $module) {
	if (file_exists('data/modules/'.$module.'/'.$module.'.php')) {
		require_once ('data/modules/'.$module.'/'.$module.'.php');
		$module_list[] = $module;
	}
}
unset($module);

//Include security-enhancements.
require_once ('data/inc/security.php');
//Include functions.
require_once ('data/inc/functions.all.php');
require_once ('data/inc/functions.admin.php');
//Include variables.
require_once ('data/inc/variables.all.php');

//First check if we've installed pluck.
if (!file_exists('data/settings/install.dat')) {
	$titelkop = $lang['install']['not'];
	include_once ('data/inc/header2.php');
	redirect('install.php', 3);
	echo $lang['install']['not_message'];
	include_once ('data/inc/footer.php');
	exit;
}

else {
	session_start();
	//Then check if we are properly logged in.
	if (!isset($_SESSION ['cmssystem_loggedin'])) {
		$titelkop = $lang['login']['not'];
		include_once ('data/inc/header2.php');
		redirect('login.php', 3);
		echo $lang['login']['not_message'];
		include_once ('data/inc/footer.php');
		exit;
	}

	//Define pages.
	//------------
	if (isset($action)) {
		switch ($action) {
			//Page:Start
			case 'start':
				$titelkop = $lang['start']['title'];
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
				$titelkop = $lang['modules']['title'];
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
				$titelkop = $lang['settings']['title'];
				include_once ('data/inc/header.php');
				include_once ('data/inc/settings.php');
				break;

			//Page:Options:Language
			case 'language':
				$titelkop = $lang['language']['title'];
				include_once ('data/inc/header.php');
				include_once ('data/inc/language.php');
				break;

			//Page:Options:Theme
			case 'theme':
				$titelkop = $lang['theme']['title'];
				include_once ('data/inc/header.php');
				include_once ('data/inc/theme.php');
				break;

			//Page:Options:Changepass
			case 'changepass':
				$titelkop = $lang['changepass']['title'];
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
				$titelkop = $lang['trashcan']['title'];
				include_once ('data/inc/header.php');
				include_once ('data/inc/trashcan.php');
				break;

			//Page:Empty Trashcan
			case 'trashcan_empty':
				$titelkop = $lang['trashcan']['title'];
				include_once ('data/inc/header.php');
				include_once ('data/inc/trashcan_empty.php');
				break;

			//Page:Logout
			case 'logout':
				$titelkop = $lang['login']['log_out'];
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

			//Page:Deletepage
			case 'deletepage':
				$titelkop = $lang_trash1;
				include_once ('data/inc/header.php');
				include_once ('data/inc/deletepage.php');
				break;

			//Page:Editmeta
			case 'editmeta':
				$titelkop = $lang_meta1;
				include_once ('data/inc/header.php');
				include_once ('data/inc/page_editmeta.php');
				break;

			//Page:Editpage
			case 'editpage':
				$titelkop = $lang_page3;
				include_once ('data/inc/header.php');
				include_once ('data/inc/editpage.php');
				break;

			//Page:Pageup
			case 'pageup':
				$titelkop = $lang_updown1;
				include_once ('data/inc/header.php');
				include_once ('data/inc/pageup.php');
				break;

			//Page:Pagdown
			case 'pagedown':
				$titelkop = $lang_updown1;
				include_once ('data/inc/header.php');
				include_once ('data/inc/pagedown.php');
				break;

			//Unknown page => Redirect
			default:
				header('Location: ?action=start');
				exit;
				break;
		}
	}

	//Module pages.
	elseif (isset($module))
		require_once ('data/inc/modules_admininclude.php');

	//Unknown pages.
	else {
		header('Location: ?action=start');
		exit;
	}

	//Include footer.
	include_once ('data/inc/footer.php');
}
?>