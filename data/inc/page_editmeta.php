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

//redirect for a cancel
if (isset($_POST['cancel']))
	redirect('?action=page', 0);

$filename = get_page_filename($var1);

//Include the actual siteinfo
require ('data/settings/pages/'.$filename);

if (isset($_POST['save']) || isset($_POST['save_exit'])){
	//Remove .php from the filename. We add it again in save_page.
	$filenameCut = preg_replace('/.php$/', '', $filename);

	//Save the page
	if (isset($module_pageinc))
		save_page($filenameCut, $title, $content, $hidden, $cont1, $cont2, $module_pageinc);
	else
		save_page($filenameCut, $title, $content, $hidden, $cont1, $cont2);

	//Redirect user only if they hit save and exit
	if (isset($_POST['save_exit'])){
		echo $lang_meta4;
		redirect('?action=page', 0);
	}
	
	//Include the site info again with updated data
	require ('data/settings/pages/'.$filename);
	
}


//Introduction text
?>
	<p>
		<strong><?php echo $lang_meta2; ?></strong>
	</p>
	<form method="post" action="">
		<span class="kop2"><?php echo $lang_albums11; ?></span>
		<br />
		<textarea name="cont1" rows="3" cols="50"><?php if (isset($description)) echo $description; ?></textarea>
		<br /><br />
		<span class="kop2"><?php echo $lang_siteinfo4; ?></span> (<?php echo $lang_siteinfo5; ?>)
		<br />
		<textarea name="cont2" rows="5" cols="50"><?php if (isset($keywords)) echo $keywords; ?></textarea>
		<br /><br />
		<input class="save" type="submit" name="save" value="<?php echo $lang['general']['save']; ?>"/>
		<input type="submit" name="save_exit" value="<?php echo $lang['general']['save_exit']; ?>" title="<?php echo $lang['general']['save_exit']; ?>" />
		<input class="cancel" type="submit" name="cancel" title="<?php echo $lang['general']['cancel']; ?>" value="<?php echo $lang['general']['cancel']; ?>" />
	</form>