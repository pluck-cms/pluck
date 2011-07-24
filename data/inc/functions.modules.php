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

		//If we are on the index.php, include the needed functions.
		if (strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') !== false && file_exists('data/modules/'.$module.'/'.$module.'.site.php'))
				require_once ('data/modules/'.$module.'/'.$module.'.site.php');

		$module_list[] = $module;
	}
}
unset($module);

//Sort the modules.
natcasesort($module_list);

/**
 * Run a module hook. Can also filter strings.
 *
 * @param string $name Name of the hook.
 * @param array $par The strings to filter, if it's a filter hook.
 */
function run_hook($name, $par = null) {
	global $module_list;
	if (!isset($name))
		return;
	foreach ($module_list as $module) {
		if (function_exists($module.'_'.$name) && module_is_compatible($module)) {
			if ($par == null) {
				$output = call_user_func($module.'_'.$name);
				if (!empty($output)) {
					$function_output[] = $output;
					unset($output);
				}
			}
			else {
				$output = call_user_func_array($module.'_'.$name, $par);
				if (!empty($output)) {
					$function_output[] = $output;
					unset($output);
				}
			}
			if (isset($function_output))
				return $function_output;
		}
	}
	unset($module);
}

/**
 * Check if module is compatible with the current version of pluck.
 *
 * @param string $module The module you want to check.
 * @return bool
 */
function module_is_compatible($module) {
	//Include module information.
	if (file_exists('data/modules/'.$module.'/'.$module.'.php')) {
		$module_info = call_user_func($module.'_info');
		if (isset($module_info['compatibility'])) {
			if (strpos($module_info['compatibility'], ','))
				$version_compat = explode(',', $module_info['compatibility']);
			else
				$version_compat[0] = $module_info['compatibility'];

			//Now check if we have a compatible version. NOTE: If pluck is an alpha or beta version, it will always be compatible.
			foreach ($version_compat as $number => $version) {
				if (preg_match('/'.$version.'/', PLUCK_VERSION) || preg_match('/(alpha|beta)/', PLUCK_VERSION)) {
					return true;
				}
			}
			unset($number);
		}
	}

	else
		return false;
}

/**
 * Checks if a module is included in a page.
 *
 * @param string $module The module you want to check.
 * @param string $page The seoname of the page you want to check.
 * @return bool
 */
function module_is_included_in_page($module, $page_seoname) {
	include(PAGE_DIR.'/'.get_page_filename($page_seoname));
	if (strpos($content, '{pluck show_module('.$module))
		return TRUE;
	else
		return FALSE;
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
 * @param string $module The module for which the settings need to be saved.
 * @param array $settings Settings in array.
 */
function module_save_settings($module, $settings) {
	if(module_is_compatible($module)) {
		foreach ($settings as $setting => $value) {
			$settings[$setting] = sanitize($value);
		}
		save_file('data/settings/'.$module.'.settings.php', $settings);
	}
}

/**
 * Returns the current value of a module setting. If no setting has been saved, the default value will be returned.
 *
 * @param string $module The module.
 * @param string $setting The setting from which to obtain the value.
 */
function module_get_setting($module, $setting) {
	if(module_is_compatible($module)) {

		//First retrieve default module settings.
		$default_settings = call_user_func($module.'_settings_default');

		if (isset($default_settings[$setting])) {
			//Load default setting
			$$setting = $default_settings[$setting];
			//Check if a saved setting is available
			if(file_exists('data/settings/'.$module.'.settings.php')) {
				include('data/settings/'.$module.'.settings.php');
			}
			return $$setting;
		}
		else
			show_error('Module setting '.$setting.' does not exist in module '.$module.'.', 1);
	}
}
?>