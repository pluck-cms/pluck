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
defined('IN_PLUCK') or exit('Access denied!');

/**
 * Recursively delete an entire directory.
 *
 * @param string $directory The dir you want to remove.
 * @param bool $empty Should the dir remain empty?
 * @return bool
 */
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
		if (!$empty) {
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
 */
function read_dir_contents($directory, $mode) {
	if (!is_dir($directory))
		return false;

	$path = opendir($directory);
	while (false !== ($file = readdir($path))) {
		if ($file != '.' && $file != '..') {
			if (is_file($directory.'/'.$file))
				$files[] = $file;

			elseif (is_dir($directory.'/'.$file))
				$dirs[] = $file;
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
 * Universal function for saving files.
 *
 * @param string $file Full patch to the file.
 * @param mixed $content The page content. If it's an array, it will create the structure for you.
 * @param int $chmod With leading zero!
 */
function save_file($file, $content, $chmod = 0777) {
	$data = fopen($file, 'w');

	//If it's an array, we have to create the structure.
	if (is_array($content) && !empty($content)) {
		$final_content = '<?php'."\n";
		foreach ($content as $var => $value) {
			$final_content .= '$'.$var.' = \''.$value.'\';'."\n";
		}
		$final_content .= '?>';

		fputs($data, $final_content);
	}

	else
		fputs($data, $content);

	fclose($data);
	chmod($file, $chmod);
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

/**
 * Displays or returns an error, notice or success message.
 *
 * @param string $message The message to display.
 * @param integer $level <b>1:</b> error, <b>2:</b> notice and <b>3:</b> success.
 * @param bool $return Should it return the error?
 * @return string <b>NOTE:</b> Only returns when $return is true.
 */
function show_error($message, $level, $return = false) {
	switch ($level) {
		case 1:
			$class = 'error';
			break;
		case 2:
			$class = 'notice';
			break;
		case 3:
			$class = 'success';
			break;
		default:
			$class = 'notice';
	}

	$value = '<span class="'.$class.'">'.$message.'</span>';

	if ($return == true)
		return $value;
	else
		echo $value;
}

/**
 * Convert a given string to a SEO safe URL.
 *
 * @param string $url String to convert to a SEO URL.
 * @return string A SEO safe URL.
 */
function seo_url($url) {
	//Decode HTML entities
	//Replace non-alphanumeric characters to dashes. Exceptions: %, _, -
	//Note that multiple separators are collapsed automatically by the preg_replace.
	//Convert all characters to lowercase.
	//Trim spaces on both sides.
	$slug = rtrim(mb_strtolower(preg_replace('/[^\p{L}\p{N}_]+/u', '-', preg_replace('/\p{Po}/u', '', html_entity_decode($url)))), '-');

	return $slug;
}

/**
 * Get the seoname of a page.
 *
 * @param string $filename The filename
 * @return string The seoname
 */
function get_page_seoname($filename) {
	//Remove "data/settings/pages/" from the patch, if it exist.
	$filename = str_replace('data/settings/pages/', '', $filename);

	if (strpos($filename, '/') !== false) {
		//Split the page name, and count how many matches there are.
		$matches = explode('/', $filename);
		$count = count($matches);

		//The last match is the page we want to find.
		$page = $matches[$count - 1];

		//Remove the last match, so we can find the file patch.
		unset($matches[$count - 1]);
		$patch = implode('/', $matches);

		//Run through the pages in the folder, if it exists.
		if (file_exists('data/settings/pages/'.$patch)) {
			$pages = read_dir_contents('data/settings/pages/'.$patch, 'files');
			if ($pages != false) {
				foreach ($pages as $filename) {
					//Is there a page with the name?
					if ($filename == $page) {
						$seoname = explode('.', $filename);
						return $patch.'/'.$seoname[1];
					}
				}
				unset($filename);
			}
		}
	}

	elseif (file_exists('data/settings/pages/'.$filename)) {
		$seoname = explode('.', $filename);
		return $seoname[1];
	}
	return false;
}

/**
 * Get the filename of a page.
 *
 * @param string $seoname The seoname
 * @return string The filename
 */
function get_page_filename($seoname) {
	//Remove "data/settings/pages/" from the patch, if it exist.
	$seoname = str_replace('data/settings/pages/', '', $seoname);

	//Read the pages.
	$pages = read_dir_contents('data/settings/pages', 'files');

	//Are there any pages?
	if ($pages != false) {
		//Is it a sub page?
		if (strpos($seoname, '/') !== false) {
			//Split the page name, and count how many matches there are.
			$matches = explode('/', $seoname);
			$count = count($matches);

			//The last match is the page we want to find.
			$page = $matches[$count - 1];

			//Remove the last match, so we can find the file patch.
			unset($matches[$count - 1]);
			$patch = implode('/', $matches);

			//Run thought the pages in the sub-folder, if it exists.
			if (file_exists('data/settings/pages/'.$patch)) {
				$pages = read_dir_contents('data/settings/pages/'.$patch, 'files');
				if ($pages != false) {
					foreach ($pages as $filename) {
						if (strpos($filename, '.'.$page.'.'))
							return $patch.'/'.$filename;
					}
				}
			}
		}

		//Or just a normal one?
		else {
			foreach ($pages as $filename) {
				if (strpos($filename, '.'.$seoname.'.'))
					return $filename;
			}
		}
	}
	return false;
}

/**
 * Get the sub-page directory of a page.
 *
 * @param <type> $page The page seoname
 * @return <type> The sub-page directory
 */
function get_sub_page_dir($page) {
	//Don't do anything if it's not a sub-page.
	if (strpos($page, '/') !== false && file_exists('data/settings/pages/'.get_page_filename($page))) {
		$page = explode('/', $page);
		$count = count($page);
		unset($page[$count -1]);
		$page = implode('/', $page);
		return $page;
	}

	return false;
}
?>