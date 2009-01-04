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

//Include the actual siteinfo
require_once ('data/settings/pages/'.$_GET['var']);

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
		<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
		<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=page';" />
	</form>
<?php
if (isset($_POST['Submit'])) {
	//Remove .php from the filename. We add it again in save_page.
	$page = preg_replace('/.php$/', '', $_GET['var']);

	//Save the page
	if (isset($module_pageinc))
		save_page($page, $title, $content, $hidden, $cont1, $cont2, $module_pageinc);
	else
		save_page($page, $title, $content, $hidden, $cont1, $cont2);

	//Redirect user
	echo $lang_meta4;
	redirect('?action=page', 0);
}
?>