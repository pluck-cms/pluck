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
if((!ereg('index.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('update.php', $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error.
    echo 'access denied';
    //Block all other code.
    exit();
}

//Function: check if module is compatible.
//--------------------
function module_is_compatible($module) {
	//Global variable.
	global $pluck_version;
	//Include module information.
	if (file_exists('data/modules/'.$module.'/module_info.php')) {
		include('data/modules/'.$module.'/module_info.php');
	}

	if (isset($module_compatibility)) {
		if (preg_match('/,/', $module_compatibility)) {
			$version_compat = explode(',', $module_compatibility);
		}
		else {
			$version_compat[0] = $module_compatibility;
		}
		//Now check if we have an incompatible version.
		foreach ($version_compat as $number => $version) {		
			if ($version == $pluck_version) {
				$compatible = 'yes';
			}
		}
	}

	if ($compatible == 'yes') {
		return true;
	}
	else {
		return false;
	}
	unset($compatible);
}

//Function: recursively delete an entire directory.
//--------------------
function recursive_remove_directory($directory, $empty = false)	{
	if(substr($directory,-1) == '/') {
		$directory = substr($directory, 0, -1);
	}

	if(!file_exists($directory) || !is_dir($directory)) {
		return false;
	}
	elseif(is_readable($directory)) {
		$handle = opendir($directory);

		while (false !== ($item = readdir($handle))) {
			if($item != '.' && $item != '..') {
				$path = $directory.'/'.$item;
				if(is_dir($path)) {
					recursive_remove_directory($path);
				}
				else {
					unlink($path);
				}
			}
		}
		closedir($handle);
		if($empty == false) {
			if(!rmdir($directory)) {
				return false;
			}
		}
	}
	return true;
}

//Function: get site title.
//--------------------
function get_sitetitle() {
	if(file_exists('data/settings/options.php')) {
		include('data/settings/options.php');
		return $sitetitle;
	}
}

//Function: redirect.
//--------------------
function redirect($url, $time) {
	//First, urlencode the entire url.
	$url = urlencode($url);

	//Then undo that for ? chars.
	$url = str_replace('%3F', '?', $url);
	//And undo that for = chars.
	$url = str_replace('%3D', '=', $url);
	//And undo that for & chars.
	$url = str_replace('%26', '&', $url);

	//finally generate the metatag for redirecting
	echo '<meta http-equiv="refresh" content="'.$time.'; url='.$url.'">';
}

//Function: read files in a dir, and return the names in an array.
//--------------------
function read_dir_contents($directory,$mode) {
	$path = opendir($directory);
	while(false !== ($file = readdir($path))) {
		if(($file != '.') && ($file != '..')) {
			if(is_file($directory.'/'.$file)) {
				$files[] = $file;
			}
			elseif(is_dir($directory.'/'.$file)) {
				$dirs[] = $file;
			}
		}
	}
	closedir($path);
	if($mode == 'files') {
		return $files;
	}
	if($mode == 'dirs') {
		return $dirs;
	}
}
?>