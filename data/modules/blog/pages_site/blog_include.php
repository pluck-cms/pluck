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

//Make sure the file isn't accessed directly
if((!ereg("index.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("admin.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("install.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("login.php", $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo "access denied";
    //Block all other code
    exit();
}

//Display existing posts, but only if post-index file exists
if (file_exists('data/settings/modules/blog/post_index.dat')) {
	$handle = fopen('data/settings/modules/blog/post_index.dat', 'r');
	while (!feof($handle)) {
		$file = fgets($handle, 4096);
		//Filter out line breaks
		$file = str_replace ("\n",'', $file);
		//Check if post exists
		if ((file_exists('data/settings/modules/blog/posts/'.$file)) && (is_file('data/settings/modules/blog/posts/'.$file))) {
			//Include post information
			include_once('data/settings/modules/blog/posts/'.$file);
			?>
			<div class="blogpost">
				<span class="posttitle">
					<a href="?module=blog&amp;page=viewpost&amp;post=<?php echo $file; ?>&amp;pageback=<?php echo CURRENT_PAGE_FILENAME; ?>" title="post <?php echo $post_title; ?>"><?php echo $post_title; ?></a>
				</span><br />
				<span class="postinfo">
					<?php echo $lang_blog14; ?> <span style="font-weight: bold;"><?php echo $post_category; ?></span> - <?php echo $post_month; ?>-<?php echo $post_day; ?>-<?php echo $post_year; ?>, <?php echo $post_time; ?>
				</span>
				<br /><br />
				<?php echo $post_content; ?>
				<p>
					<a href="?module=blog&amp;page=viewpost&amp;post=<?php echo $file; ?>&amp;pageback=<?php echo CURRENT_PAGE_FILENAME; ?>" title="<?php echo $lang_blog23; ?>">&raquo; <?php echo $lang_blog23; ?></a>
				</p>
			</div>
			<?php
		}
	}
	//Close module-dir
	fclose($handle);
}
?>