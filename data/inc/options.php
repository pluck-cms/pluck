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
		<strong><?php echo $lang['options']['message']; ?></strong>
	</p>
<?php
run_hook('admin_options_before');
showmenudiv($lang['settings']['title'], $lang['options']['settings_descr'], 'data/image/settings.png', '?action=settings');
showmenudiv($lang['modules_manage']['title'], $lang['options']['modules_descr'], 'data/image/modules.png', '?action=managemodules');
showmenudiv($lang['modules_settings']['title'], $lang['options']['modules_sett_descr'], 'data/image/settings2.png', '?action=modulesettings');
showmenudiv($lang['theme']['title'], $lang['options']['themes_descr'], 'data/image/themes.png', '?action=theme');
showmenudiv($lang['language']['title'], $lang['options']['lang_descr'], 'data/image/language.png', '?action=language');
showmenudiv($lang['changepass']['title'], $lang['options']['pass_descr'], 'data/image/password.png', '?action=changepass');
run_hook('admin_options_after');
?>