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

//Introduction text
?>
	<p>
		<strong><?php echo $lang['start']['welcome']; ?></strong>
		<br />
		<?php echo $lang['start']['manage']; ?>
	</p>
	<span class="kop2"><?php echo $lang['start']['more']; ?></span>
<?php
//Show the divs
showmenudiv($lang['start']['website'], $lang['start']['result'], 'data/image/website.png', 'index.php', true);
showmenudiv($lang['credits']['title'], $lang['start']['people'], 'data/image/credits.png', '?action=credits');
showmenudiv($lang['start']['help'], $lang['start']['love'], 'data/image/hulp.png', 'http://www.pluck-cms.org/help.php', true);
?>