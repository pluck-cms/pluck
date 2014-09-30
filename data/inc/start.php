<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) pluck team
 * http://www.pluck-cms.org

 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');

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
showmenudiv($lang['writable']['title'], $lang['writable']['title'], 'data/image/update-no.png', '?action=writable');
showmenudiv($lang['start']['help'], $lang['start']['love'], 'data/image/help.png', 'http://www.phphelp.com/forum/pluck-cms/', true);
?>
