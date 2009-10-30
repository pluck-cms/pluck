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
?>
<p>
	<strong><?php echo $lang['modules']['message']; ?></strong>
</p>
<?php
foreach ($module_list as $module) {
	//Load module admin pages.
	if (file_exists('data/modules/'.$module.'/'.$module.'.admin.php'))
		require_once ('data/modules/'.$module.'/'.$module.'.admin.php');

	//Only show the button if there are admincenter pages for the module, and if the modules is compatible.
	if (module_is_compatible($module) && function_exists($module.'_pages_admin')) {
		$module_info = call_user_func($module.'_info');
		showmenudiv($module_info['name'], $module_info['intro'], 'data/modules/'.$module.'/'.$module_info['icon'], '?module='.$module);
	}
}
unset($module);
?>