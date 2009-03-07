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
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit;
}

if ((isset($_GET['post'])) && (isset($_GET['key']))) {
	//Set variables
	$post = $_GET['post'];
	$key = $_GET['key'];

	//Check if post exists
	if (file_exists('data/settings/modules/blog/posts/'.$post)) {
		include('data/settings/modules/blog/posts/'.$post);

		//Check if the post actually contains reactions
		if(isset($post_reaction_title)) {
			//Strip slashes from post itself
			$post_title = sanitize($post_title);
			$post_category = sanitize($post_category);
			$post_content = sanitize($post_content, false);

			//Then, save existing post information
			$file = fopen('data/settings/modules/blog/posts/'.$post, 'w');
			fputs($file, '<?php'."\n"
			.'$post_title = \''.$post_title.'\';'."\n"
			.'$post_category = \''.$post_category.'\';'."\n"
			.'$post_content = \''.$post_content.'\';'."\n"
			.'$post_day = \''.$post_day.'\';'."\n"
			.'$post_month = \''.$post_month.'\';'."\n"
			.'$post_year = \''.$post_year.'\';'."\n"
			.'$post_time = \''.$post_time.'\';'."\n");

			//Set new key to 0
			$new_key = 0;

			//Save reactions
			foreach($post_reaction_title as $reaction_key => $value) {
				//Don't save the reaction we want to delete
				if($reaction_key != $key) {
					//Sanitize reaction variables
					$post_reaction_title[$reaction_key] = sanitize($post_reaction_title[$reaction_key]);
					$post_reaction_name[$reaction_key] = sanitize($post_reaction_name[$reaction_key]);
					$post_reaction_content[$reaction_key] = sanitize($post_reaction_content[$reaction_key]);
					fputs($file, '$post_reaction_title['.$new_key.'] = \''.$post_reaction_title[$reaction_key].'\';'."\n"
					.'$post_reaction_name['.$new_key.'] = \''.$post_reaction_name[$reaction_key].'\';'."\n"
					.'$post_reaction_content['.$new_key.'] = \''.$post_reaction_content[$reaction_key].'\';'."\n"
					.'$post_reaction_day['.$new_key.'] = \''.$post_reaction_day[$reaction_key].'\';'."\n"
					.'$post_reaction_month['.$new_key.'] = \''.$post_reaction_month[$reaction_key].'\';'."\n"
					.'$post_reaction_year['.$new_key.'] = \''.$post_reaction_year[$reaction_key].'\';'."\n"
					.'$post_reaction_time['.$new_key.'] = \''.$post_reaction_time[$reaction_key].'\';'."\n");
					//Adjust new key
					$new_key++;
				}
			}
			fputs($file, '?>');
			fclose($file);
			chmod('data/settings/modules/blog/posts/'.$post, 0777);
			redirect('?module=blog&page=editreactions&var='.$post, 0);
		}
	}
}

//Redirect
else {
	redirect('?module=blog', 0);
}
?>