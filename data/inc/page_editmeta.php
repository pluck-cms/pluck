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

$filename = get_page_filename($var1);

//Include the actual siteinfo.
require ('data/settings/pages/'.$filename);

if (isset($_POST['save']) || isset($_POST['save_exit'])) {
	$subpage = get_sub_page_dir($var1);
	if (!$subpage)
		$subpage = null;

	//Save the page
	if (isset($module_pageinc))
		save_page($title, $content, $hidden, $subpage, $cont1, $cont2, $module_pageinc, $var1);
	else
		save_page($title, $content, $hidden, $subpage, $cont1, $cont2, null, $var1);

	//Redirect user only if they hit save and exit.
	if (isset($_POST['save_exit'])) {
		show_error($lang['editmeta']['changing'], 3);
		redirect('?action=page', 0);
	}
	
	//Include the site info again with updated data.
	require ('data/settings/pages/'.$filename);
	
}
?>
	<p>
		<strong><?php echo $lang['editmeta']['message']; ?></strong>
	</p>
	<form method="post" action="">
		<p>
			<label for="cont1" class="kop2"><?php echo $lang['general']['description']; ?></label>
			<br />
			<textarea id="cont1" name="cont1" rows="3" cols="50"><?php if (isset($description)) echo $description; ?></textarea>
		</p>
		<p>
			<label for="cont2" class="kop2"><?php echo $lang['editmeta']['keywords']; ?></label>
			<br />
			<span class="kop4"><?php echo $lang['editmeta']['comma']; ?></span>
			<br />
			<textarea id="cont2" name="cont2" rows="5" cols="50"><?php if (isset($keywords)) echo $keywords; ?></textarea>
		</p>
		<?php show_common_submits('?action=page', true); ?>
	</form>