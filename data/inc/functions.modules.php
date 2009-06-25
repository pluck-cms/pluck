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

//Sort the mdoules.
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
			if ($par == null)
				call_user_func($module.'_'.$name);
			else
				call_user_func_array($module.'_'.$name, $par);
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
?>