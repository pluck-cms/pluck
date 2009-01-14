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
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'update.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit();
}

//Function: check if module is compatible.
//--------------------
function module_is_compatible($module) {
	//Include module information.
	if (file_exists('data/modules/'.$module.'/module_info.php')) {
		include ('data/modules/'.$module.'/module_info.php');
	}

	if (isset($module_compatibility)) {
		if (preg_match('/,/', $module_compatibility))
			$version_compat = explode(',', $module_compatibility);
		else
			$version_compat [0] = $module_compatibility;

		//Now check if we have a compatible version. NOTE: If pluck is an alpha or beta version, it will always be compatible.
		foreach ($version_compat as $number => $version) {
			if ($version == PLUCK_VERSION || preg_match('/(alpha|beta)/', PLUCK_VERSION)) {
				$compatible = 'yes';
			}
		}
	}

	if (isset($compatible) && $compatible == 'yes')
		return true;
	else
		return false;

	unset($compatible);
}

//Function: recursively delete an entire directory.
//--------------------
function recursive_remove_directory($directory, $empty = false)	{
	if (substr($directory, -1) == '/')
		$directory = substr($directory, 0, -1);

	if (!file_exists($directory) || !is_dir($directory))
		return false;

	elseif (is_readable($directory)) {
		$handle = opendir($directory);

		while (false !== ($item = readdir($handle))) {
			if ($item != '.' && $item != '..') {
				$path = $directory.'/'.$item;
				if (is_dir($path))
					recursive_remove_directory($path);
				else
					unlink($path);
			}
		}
		closedir($handle);
		if ($empty == false) {
			if (!rmdir($directory))
				return false;
		}
	}
	return true;
}

/**
 * Get the site title from the options, and return it.
 *
 * @global string $sitetitle
 * @return string The site title.
 */
function get_sitetitle() {
	if (file_exists('data/settings/options.php')) {
		global $sitetitle;
		return $sitetitle;
	}
}

/**
 * Redirect the user to a given address after a number of seconds.
 *
 * @param string $url The redirect address.
 * @param integer $time The number of seconds before the redirect.
 */
function redirect($url, $time) {
	//First, urlencode the entire url.
	$url = urlencode($url);

	//Then undo that for ? chars.
	$url = str_replace('%3F', '?', $url);
	//And undo that for = chars.
	$url = str_replace('%3D', '=', $url);
	//And undo that for & chars.
	$url = str_replace('%26', '&', $url);

	//Finally generate the metatag for redirecting
	echo '<meta http-equiv="refresh" content="'.$time.'; url='.$url.'" />';
}

/**
 * Read files or directories in a directory, and return the names in an array.
 *
 * @param  string $directory The directory where the files are in.
 * @param  string $mode Should it read dirs or files?
 * @return array The directories or files.
 * @todo   Should mode be a boolean (true for dirs and false for files)?
 */
function read_dir_contents($directory, $mode) {
	$path = opendir($directory);
	while (false !== ($file = readdir($path))) {
		if (($file != '.') && ($file != '..')) {
			if (is_file($directory.'/'.$file)) {
				$files[] = $file;
			}
			elseif (is_dir($directory.'/'.$file)) {
				$dirs[] = $file;
			}
		}
	}
	closedir($path);

	if ($mode == 'files' && isset($files))
		return $files;
	elseif ($mode == 'dirs' && isset($dirs))
		return $dirs;
	else
		return false;
}

/**
 * Sanitize a variable, to make it ready for saving in a file.
 *
 * @param string $var The variable to sanitize.
 * @param boolean $html Should it convert HTML too?
 * @return string The sanitized variable.
 */
function sanitize($var, $html = true) {
	$var = str_replace('\'', '\\\'', $var);

	if ($html == true)
		$var = htmlspecialchars($var, ENT_COMPAT, 'UTF-8', false);

	return $var;
}


function add_hook($hookname, $funcname) {
	global $hooks;
	if (!isset($hookname) || !isset($funcname))
		return;
	$hook = array('hookname' => $hookname, 'funcname' => $funcname);
	$hooks[] = $hook;
}

function run_hook($name) {
	global $hooks;
	if (!isset($name))
		return;
	foreach ($hooks as $hook) {
		if (is_callable($hook['funcname']) && $hook['hookname'] == $name )
			call_user_func($hook['funcname']);
	}
}

function load_modules() {
	$dir = 'data/modules';
	$modules = read_dir_contents($dir, 'dirs');
	foreach ($modules as $module) {
		//FIXME: module_is_compatible() need to be updated.
		if (/*module_is_compatible($module) &&*/ file_exists($dir.'/'.$module.'/'.$module.'.php'))
			require_once ($dir.'/'.$module.'/'.$module.'.php');
	}
}
?>