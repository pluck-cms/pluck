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

$filename = get_page_filename($var1);

//Include the actual siteinfo.
require ('data/settings/pages/'.$filename);

if (isset($_POST['save']) || isset($_POST['save_exit'])) {
	//Remove .php from the filename. We add it again in save_page.
	$filename_cut = preg_replace('/.php$/', '', $filename);

	//Save the page
	if (isset($module_pageinc))
		save_page($filename_cut, $title, $content, $hidden, $cont1, $cont2, $module_pageinc);
	else
		save_page($filename_cut, $title, $content, $hidden, $cont1, $cont2);

	//Redirect user only if they hit save and exit.
	if (isset($_POST['save_exit'])) {
		show_error($lang['editmeta']['changing'], 3);
		redirect('?action=page', 0);
	}
	
	//Include the site info again with updated data.
	require ('data/settings/pages/'.$filename);
	
}


//Introduction text
?>
	<p>
		<strong><?php echo $lang['editmeta']['message']; ?></strong>
	</p>
	<form method="post" action="">
		<p>
			<span class="kop2"><?php echo $lang['general']['description']; ?></span>
			<br />
			<textarea name="cont1" rows="3" cols="50"><?php if (isset($description)) echo $description; ?></textarea>
		</p>
		<p>
			<span class="kop2"><?php echo $lang['editmeta']['keywords']; ?></span> (<?php echo $lang['editmeta']['comma']; ?>)
			<br />
			<textarea name="cont2" rows="5" cols="50"><?php if (isset($keywords)) echo $keywords; ?></textarea>
		</p>
		<p>
			<?php show_common_submits('?action=page', true); ?>
		</p>
	</form>