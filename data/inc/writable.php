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
?>

	<p>
		<strong><?php echo $lang['writable']['check']; ?></strong>
	</p>
	<?php
		//Writable checks.
		foreach (array('images', 'files', 'data/modules', 'data/trash', 'data/themes', 'data/themes/default', 'data/themes/oldstyle', 'data/settings', 'data/settings/langpref.php') as $check)
			check_writable($check);
		unset($check);
	?>
	<p>
		<a href="javascript:refresh()"><?php echo $lang['install']['refresh']; ?></a>
	</p>