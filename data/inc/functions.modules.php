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

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');

//Load all the modules, so we can use hooks.
//This has to be done before including other functions files.
$module_list = array();
$path = opendir('data/modules');
while (false !== ($dir = readdir($path))) {
	if ($dir != '.' && $dir != '..') {
		if (is_dir('data/modules/'.$dir))
			$module_list[] = $dir;
	}
}
closedir($path);

//Sort the modules.
natcasesort($module_list);

//Then include necessary module files for each module.
foreach ($module_list as $module) {
	if (file_exists('data/modules/'.$module.'/'.$module.'.php')) {
		require_once ('data/modules/'.$module.'/'.$module.'.php');

		//If we are on the index.php, include the needed functions.
		if (strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') !== false && file_exists('data/modules/'.$module.'/'.$module.'.site.php'))
			require_once ('data/modules/'.$module.'/'.$module.'.site.php');
	}
}
unset($module);

/**
 * Run a module hook. Parameters are passed by reference.
 *
 * Hooks should be declared with the reference sign for parameters.
 * e.g. function mymodule_the_hook(&$parameter)
 *
 * The hook must be called with the parameters inside $par passed by reference.
 * e.g. run_hook('the_hook', array(&$parameter));
 *
 * @since 4.7
 * @package all
 * @param string $name Name of the hook.
 * @param array $par The parameters for the hook.
 */
function run_hook($name, $par = null) {
	global $module_list;
	if (empty($name)) return false;

	foreach ($module_list as $module) {
		if (file_exists('data/modules/'.$module.'/'.$module.'.php')) {
			require_once ('data/modules/'.$module.'/'.$module.'.php');
			if (function_exists($module.'_'.$name) && module_is_compatible($module)) {
				if (!isset($par)) {
					call_user_func($module.'_'.$name);
				} else {
					call_user_func_array($module.'_'.$name, $par);
				}
			}
		}
	}

	return true;
}

/**
 * Check if module is compatible with the current version of pluck.
 *
 * @since 4.6
 * @package all
 * @param string $module The module you want to check.
 * @return bool
 */
function module_is_compatible($module) {
	//Include module information.
	if (function_exists($module.'_info')) {
		//NOTE: If pluck is an alpha, beta or dev version, it will always be compatible.
		if (preg_match('/(alpha|beta|dev)/', PLUCK_VERSION)) return true;

		$module_info = call_user_func($module.'_info');
		if (isset($module_info['compatibility'])) {
			$version_compat = explode(',', $module_info['compatibility']);

			//Now check if we have a compatible version.
			foreach ($version_compat as $version) {
				if (preg_match('/^'.$version.'/', PLUCK_VERSION)) {
					return true;
				}
			}
		}
	}

	return false;
}

/**
 * Checks if a module is included in a page.
 *
 * @since 4.7
 * @package all
 * @param string $module The module you want to check.
 * @param string $page_seoname The seoname of the page you want to check.
 * @return bool
 */
function module_is_included_in_page($module, $page_seoname) {
	$page_filename = get_page_filename($page_seoname);

	if (is_file(PAGE_DIR.'/'.$page_filename)) {
		$content = '';
		include(PAGE_DIR.'/'.$page_filename);
		if (preg_match('/\{pluck show_module\('.$module.'(,[^)]*)?\)\}/', $content)) {
			return true;
		}
	}

	return false;
}

function module_insert_at_position($array, $data, $position) {
	array_splice($array, $position - 1, 0, $data);
	return $array;
}

function module_insert_before($array, $data, $subject) {
	$search = array_search($subject, $array);

	if ($search !== false)
		return module_insert_at_position($array, $data, $search + 1);
	else
		return $array;
}

function module_insert_after($array, $data, $subject) {
	$search = array_search($subject, $array);

	if ($search !== false)
		return module_insert_at_position($array, $data, $search + 2);
	else
		return $array;
}

/**
 * Save module settings in configuration file.
 *
 * @since 4.7
 * @package all
 * @param string $module The module for which the settings need to be saved.
 * @param array $settings Settings in array.
 */
function module_save_settings($module, $settings) {
	if (module_is_compatible($module)) {
		foreach ($settings as $setting => $value) {
			$settings[$setting] = sanitize($value);
		}
		save_file('data/settings/'.$module.'.settings.php', $settings);
	}
}

/**
 * Returns the current value of a module setting. If no setting has been saved, the default value will be returned.
 *
 * @since 4.7
 * @package all
 * @param string $module The module.
 * @param string $setting The setting from which to obtain the value.
 */
function module_get_setting($module, $setting) {
	if (module_is_compatible($module)) {

		//First retrieve default module settings.
		$default_settings = call_user_func($module.'_settings_default');

		if (isset($default_settings[$setting])) {
			//Load default setting
			$$setting = $default_settings[$setting];
			//Check if a saved setting is available
			if (file_exists('data/settings/'.$module.'.settings.php')) {
				include('data/settings/'.$module.'.settings.php');
			}
			return $$setting;
		} else {
			trigger_error('Module setting '.$setting.' does not exist in module '.$module.'.', E_USER_WARNING);
		}
	}
}
?>