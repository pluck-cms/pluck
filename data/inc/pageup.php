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
	exit();
}

//Check if file exists
if (file_exists('data/settings/pages/'.$pageup.'')) {

	//We can't higher kop1.php, so we have to check:
	if ($pageup == 'kop1.php') {
		echo $lang_updown2;
		redirect('?action=page', 2);
		include_once('data/inc/footer.php');
		exit;
	}

	//Determine the page number
	list($pagenumber1, $extension) = explode('.', $pageup);
	list($filename, $pagenumber) =  explode('p', $pagenumber1);

	//Define prefixes
	$temp = '_temp';
	$kop = 'kop';
	$ext = 'php';
	//First make temporary file
	rename('data/settings/pages/'.$pageup.'', 'data/settings/pages/'.$pageup.$temp.'');

	//Then make the higher page one lower
	$higherpagenumber = ($pagenumber-1);
	rename('data/settings/pages/'.$kop.$higherpagenumber.'.'.$ext.'', 'data/settings/pages/'.$pageup.'');

	//Finally, give the temp-file its final name
	rename('data/settings/pages/'.$pageup.$temp.'', 'data/settings/pages/'.$kop.$higherpagenumber.'.'.$ext.'');

	//Display message
	echo $lang_updown3;
}
redirect('?action=page', 0);
?>