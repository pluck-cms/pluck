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
	<strong><?php echo $lang['page']['message']; ?></strong>
</p>
<?php
//Run hook.
run_hook('admin_pages_before');
//New page button.
showmenudiv($lang['page']['new'], null, 'data/image/newpage.png', '?action=editpage');
//Manage images button.
showmenudiv($lang['images']['title'], null, 'data/image/image.png', '?action=images');

//Show pages.
$pages = get_pages();

if ($pages) {
	foreach ($pages as $page)
		show_page_box($page);
	unset($page);
}
?>