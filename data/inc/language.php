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

//Introduction text.
?>
	<p>
		<strong><?php echo $lang['language']['choose']; ?></strong>
	</p>
	<form action="" method="post">
		<select name="cont1">
			<option selected="selected" value="0"><?php echo $lang['general']['choose']; ?></option>
			<?php read_lang_files(LANG_FILE); ?>
		</select>
		<br /><br />
		<input type="submit" name="submit" value="<?php echo $lang['general']['save']; ?>" />
		<input type="button" value="<?php echo $lang['general']['cancel']; ?>" onclick="javascript: window.location='?action=options';" />
	</form>
<?php
//Check if chosen language is valid, and then save data.
if (isset($_POST['submit']) && isset($cont1) && $cont1 != '0' && file_exists('data/inc/lang/'.$cont1)) {
	save_language($cont1);

	//Redirect user.
	echo $lang['language']['saved'];
	redirect('?action=options', 2);
}
?>