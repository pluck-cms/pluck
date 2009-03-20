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
?>
<p>
	<strong><?php echo $lang_page1; ?></strong>
</p>
<?php
//Run hook.
run_hook('admin_pages_before');
//New page button.
showmenudiv($lang_page2, null, 'data/image/newpage.png', '?action=newpage');
//Manage images button.
showmenudiv($lang_kop17, null, 'data/image/image.png', '?action=images');

//Show pages.
$files = read_dir_contents('data/settings/pages', 'files');
if ($files) {
	natcasesort($files);
	foreach ($files as $file) {
		include ('data/settings/pages/'.$file);
		?>
			<div class="menudiv">
				<span>
					<img src="data/image/page.png" alt="" />
				</span>
				<span class="title-page"><?php echo $title; ?></span>
				<?php run_hook('admin_page_list_before'); ?>
				<span>
					<a href="?action=editpage&amp;var1=<?php echo $file; ?>">
					<img src="data/image/edit.png" title="<?php echo $lang_page3; ?>" alt="<?php echo $lang_page3; ?>" />
					</a>
				</span>
				<span>
					<a href="?action=editmeta&amp;var1=<?php echo $file; ?>">
						<img src="data/image/siteinformation.png" title="<?php echo $lang_meta1; ?>" alt="<?php echo $lang_meta1; ?>" />
					</a>
				</span>
				<span>
					<a href="?action=pageup&amp;var1=<?php echo $file; ?>">
						<img src="data/image/up.png" title="<?php echo $lang_updown1; ?>" alt="<?php echo $lang_updown1; ?>" />
					</a>
				</span>
				<span>
					<a href="?action=pagedown&amp;var1=<?php echo $file; ?>">
						<img src="data/image/down.png" title="<?php echo $lang_updown1; ?>" alt="<?php echo $lang_updown1; ?>" />
					</a>
				</span>
				<span>
					<a href="?action=deletepage&amp;var1=<?php echo $file; ?>">
						<img src="data/image/delete.png" title="<?php echo $lang_trash1; ?>" alt="<?php echo $lang_trash1; ?>" />
					</a>
				</span>
				<?php run_hook('admin_page_list_after'); ?>
			</div>
		<?php
	}
	unset($file);
}
?>