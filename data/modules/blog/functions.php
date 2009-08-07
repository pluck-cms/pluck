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

/**
 * Save or edit a blog post.
 *
 * @param string $title The title of the blog post.
 * @param string $category The category of the blog post.
 * @param string $content The contents of the blog post (the post itself).
 * @param string $name The filename of the blog post, if the post already exists (with extension).
 * @param string $post_day The day when the post is posted (by default current day).
 * @param string $post_month The month when the post is posted (by default current month).
 * @param string $post_year The year when the post is posted (by default current year).
 * @param string $post_time The time when the post is posted (by default current time).
 */
function blog_save_post($title, $category, $content, $name = null, $post_day = null, $post_month = null, $post_year = null, $post_time = null) {
	global $post_reaction_title, $post_reaction_name, $post_reaction_content, $post_reaction_day, $post_reaction_month, $post_reaction_year, $post_reaction_time;

	//Sanitize variables
	$title = sanitize($title);
	$category = sanitize($category);
	$content = sanitize($content, false);

	//Get dates.
	if (!isset($post_day))
		$post_day = date('d');
	if (!isset($post_month))
		$post_month = date('m');
	if (!isset($post_year))
		$post_year = date('Y');
	if (!isset($post_time))
		$post_time = date('H:i');

	//Generate filename
	$newfile = strtolower($title);
	$newfile = str_replace('.', '', $newfile);
	$newfile = str_replace(',', '', $newfile);
	$newfile = str_replace('?', '', $newfile);
	$newfile = str_replace(':', '', $newfile);
	$newfile = str_replace('<', '', $newfile);
	$newfile = str_replace('>', '', $newfile);
	$newfile = str_replace('=', '', $newfile);
	$newfile = str_replace('"', '', $newfile);
	$newfile = str_replace('\'', '', $newfile);
	$newfile = str_replace('/', '', $newfile);
	$newfile = str_replace('\\', '', $newfile);
	$newfile = str_replace('-', '', $newfile);
	$newfile = str_replace('  ', '-', $newfile);
	$newfile = str_replace(' ', '-', $newfile);

	//Make sure chosen filename doesn't exist
	while((!isset($name) && file_exists('data/settings/modules/blog/posts/'.$newfile.'.php')) || (isset($name) && $name != $newfile.'.php' && file_exists('data/settings/modules/blog/posts/'.$newfile.'.php')))
		$newfile = $newfile.'-new';
	//Include extension.
	$newfile = $newfile.'.php';

	//If post already exists, check if we need to update the post index (if the title has changed).
	if (isset($name) && $name != $newfile) {
		//Change old filename into new filename in post index.
		if (file_exists('data/settings/modules/blog/post_index.dat')) {
			$contents = file_get_contents('data/settings/modules/blog/post_index.dat');

			//Check if post index contains old filename, and change it into new filename.
			if (strpos($contents, $name."\n") !== FALSE)
				$contents = str_replace($name."\n", $newfile."\n", $contents);
			elseif (strpos($contents, "\n".$name) !== FALSE)
				$contents = str_replace("\n".$name, "\n".$newfile, $contents);
			elseif (strpos($contents, $name) !== FALSE)
				$contents = str_replace($name, $newfile, $contents);

			//Save updated post index.
			$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
			fputs($file, $contents);
			fclose($file);
			chmod('data/settings/modules/blog/post_index.dat', 0777);
		}

		//Check if the old post exists, then delete it.
		if (file_exists('data/settings/modules/blog/posts/'.$name)) {
			//Delete the post
			unlink('data/settings/modules/blog/posts/'.$name);
		}
	}

	//If post does not exist.
	elseif (!isset($name)) {
		if(file_exists('data/settings/modules/blog/post_index.dat')) {
			$contents = file_get_contents('data/settings/modules/blog/post_index.dat');
			$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
			if(!empty($contents))
				fputs($file, $newfile."\n".$contents);
			else
				fputs($file, $newfile);
		}
		else {
			$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
			fputs($file, $newfile);
		}
		fclose($file);
		unset($file);
		chmod('data/settings/modules/blog/post_index.dat', 0777);
	}

	//Save information
	$file = fopen('data/settings/modules/blog/posts/'.$newfile, 'w');
	fputs($file, '<?php'."\n"
	.'$post_title = \''.$title.'\';'."\n"
	.'$post_category = \''.$category.'\';'."\n"
	.'$post_content = \''.$content.'\';'."\n"
	.'$post_day = \''.$post_day.'\';'."\n"
	.'$post_month = \''.$post_month.'\';'."\n"
	.'$post_year = \''.$post_year.'\';'."\n"
	.'$post_time = \''.$post_time.'\';'."\n");

	//Check if there are reactions
	if (isset($name) && isset($post_reaction_title)) {
		foreach ($post_reaction_title as $reaction_key => $value) {

			//Sanitize reaction variables
			$post_reaction_title[$reaction_key] = sanitize($post_reaction_title[$reaction_key]);
			$post_reaction_name[$reaction_key] = sanitize($post_reaction_name[$reaction_key]);
			$post_reaction_content[$reaction_key] = sanitize($post_reaction_content[$reaction_key]);

			//And save the existing reaction
			fputs($file, '$post_reaction_title['.$reaction_key.'] = \''.$post_reaction_title[$reaction_key].'\';'."\n"
			.'$post_reaction_name['.$reaction_key.'] = \''.$post_reaction_name[$reaction_key].'\';'."\n"
			.'$post_reaction_content['.$reaction_key.'] = \''.$post_reaction_content[$reaction_key].'\';'."\n"
			.'$post_reaction_day['.$reaction_key.'] = \''.$post_reaction_day[$reaction_key].'\';'."\n"
			.'$post_reaction_month['.$reaction_key.'] = \''.$post_reaction_month[$reaction_key].'\';'."\n"
			.'$post_reaction_year['.$reaction_key.'] = \''.$post_reaction_year[$reaction_key].'\';'."\n"
			.'$post_reaction_time['.$reaction_key.'] = \''.$post_reaction_time[$reaction_key].'\';'."\n");
		}
		unset($reaction_key);
	}
	fputs($file, '?>');
	fclose($file);
	chmod('data/settings/modules/blog/posts/'.$newfile, 0777);

	//Return filename under which post has been saved (to allow for redirect).
	return $newfile;
}

/**
 * Delete a blog post.
 *
 * @param string $post The filename of the blog post that needs to be deleted (with extension).
 */
function blog_delete_post($post) {
	//First, update the post index.
	if(file_exists('data/settings/modules/blog/post_index.dat')) {
		$contents = file_get_contents('data/settings/modules/blog/post_index.dat');

		//Check if post index contains post we want to delete, and filter out the post.
		if(strpos($contents, $post."\n") !== FALSE)
			$contents = str_replace($post."\n",'',$contents);
		elseif(strpos($contents, "\n".$post) !== FALSE)
			$contents = str_replace("\n".$post,'',$contents);
		elseif(strpos($contents, $post) !== FALSE)
			$contents = str_replace($post,'',$contents);

		//Save updated post index.
		$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
		fputs($file, $contents);
		fclose($file);

		//Reload contents of post index in variable.
		$contents = file_get_contents('data/settings/modules/blog/post_index.dat');
		//If file is empty, delete post index.
		if(empty($contents))
			unlink('data/settings/modules/blog/post_index.dat');
	}

	//Check if post exists, then delete it.
	if(file_exists('data/settings/modules/blog/posts/'.$post))
		unlink('data/settings/modules/blog/posts/'.$post);
}

/**
 * Save/add a reaction to a blog post.
 *
 * @param string $post The filename of the blog post to which the reaction should be added.
 * @param string $title The title of the reaction.
 * @param string $name The name of the person posting the reaction.
 * @param string $message The message of the reaction.
 * @param int $id If an existing reaction needs to be edited, the id of the reaction should go here.
 */
function blog_save_reaction($post, $title, $name, $message, $id = null) {
	//Get information of blog post.
	include('data/settings/modules/blog/posts/'.$post);

	//Check for HTML, and block if needed.
	//FIXME: Replace ereg with strpos.
	if (ereg('<', $title) || ereg('>', $title) || ereg('<', $name) || ereg('>', $name) || ereg('<', $message) || ereg('>', $message))
		echo '<span style="color: red;">'.$lang_blog22.'</span>';

	else {
		//Delete unwanted characters
		$title = sanitize($title);
		$name = sanitize($name);
		$message = sanitize($message);
		$post_title = sanitize($post_title);
		$post_category = sanitize($post_category);
		$post_content = sanitize($post_content, false);

		//Determine the date
		$day = date('d');
		$month = date('m');
		$year = date('Y');
		$time = date('H:i');

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

		//Check if there already are other reactions
		if (isset($post_reaction_title)) {
			foreach ($post_reaction_title as $reaction_key => $value) {
				//Set key
				$key = $reaction_key + 1;

				//If we need to edit this reaction, do it.
				if (isset($id) && $reaction_key == $id) {
					fputs($file, '$post_reaction_title['.$reaction_key.'] = \''.$title.'\';'."\n"
					.'$post_reaction_name['.$reaction_key.'] = \''.$name.'\';'."\n"
					.'$post_reaction_content['.$reaction_key.'] = \''.$message.'\';'."\n"
					.'$post_reaction_day['.$reaction_key.'] = \''.$post_reaction_day[$reaction_key].'\';'."\n"
					.'$post_reaction_month['.$reaction_key.'] = \''.$post_reaction_month[$reaction_key].'\';'."\n"
					.'$post_reaction_year['.$reaction_key.'] = \''.$post_reaction_year[$reaction_key].'\';'."\n"
					.'$post_reaction_time['.$reaction_key.'] = \''.$post_reaction_time[$reaction_key].'\';'."\n");
				}

				//Save existing reactions.
				else {
					//Sanitize reaction variables.
					$post_reaction_title[$reaction_key] = sanitize($post_reaction_title[$reaction_key]);
					$post_reaction_name[$reaction_key] = sanitize($post_reaction_name[$reaction_key]);
					$post_reaction_content[$reaction_key] = sanitize($post_reaction_content[$reaction_key]);

					//And save the existing reaction.
					fputs($file, '$post_reaction_title['.$reaction_key.'] = \''.$post_reaction_title[$reaction_key].'\';'."\n"
					.'$post_reaction_name['.$reaction_key.'] = \''.$post_reaction_name[$reaction_key].'\';'."\n"
					.'$post_reaction_content['.$reaction_key.'] = \''.$post_reaction_content[$reaction_key].'\';'."\n"
					.'$post_reaction_day['.$reaction_key.'] = \''.$post_reaction_day[$reaction_key].'\';'."\n"
					.'$post_reaction_month['.$reaction_key.'] = \''.$post_reaction_month[$reaction_key].'\';'."\n"
					.'$post_reaction_year['.$reaction_key.'] = \''.$post_reaction_year[$reaction_key].'\';'."\n"
					.'$post_reaction_time['.$reaction_key.'] = \''.$post_reaction_time[$reaction_key].'\';'."\n");
				}
			}
			unset($reaction_key);
		}

		//If this is the first reaction, use key '0'.
		elseif (!isset($post_reaction_title) && !isset($id))
			$key = 0;

		//Only save a new reaction if $id is empty.
		if (!isset($id)) {
			fputs($file, '$post_reaction_title['.$key.'] = \''.$title.'\';'."\n"
			.'$post_reaction_name['.$key.'] = \''.$name.'\';'."\n"
			.'$post_reaction_content['.$key.'] = \''.$message.'\';'."\n"
			.'$post_reaction_day['.$key.'] = \''.$day.'\';'."\n"
			.'$post_reaction_month['.$key.'] = \''.$month.'\';'."\n"
			.'$post_reaction_year['.$key.'] = \''.$year.'\';'."\n"
			.'$post_reaction_time['.$key.'] = \''.$time.'\';'."\n");
		}

		//Finish file.
		fputs($file, '?>');
		fclose($file);
		chmod('data/settings/modules/blog/posts/'.$post, 0777);
	}
}

/**
 * Load categories in an array. Will return FALSE if no categories exist.
 */
function blog_get_categories() {
	if (file_exists('data/settings/modules/blog/categories.dat')) {
		//Load them.
		$categories = file_get_contents('data/settings/modules/blog/categories.dat');
		//Filter out linebreaks.
		$categories = str_replace("\n", '', $categories);
		//Then in an array.
		$categories = explode(',',$categories);

		return $categories;
	}
	else
		return FALSE;
}

/**
 * Checks whether a blog category exists. Returns TRUE or FALSE.
 *
 * @param string $category The category to check for.
 */
function blog_category_exists($category) {
	if (blog_get_categories()) {
		//Load them.
		$categories = blog_get_categories();

		//Set variable to FALSE.
		$cat_exists = FALSE;
		//Start checking categories.
		foreach ($categories as $key => $name) {
			if ($name == $category)
				$cat_exists = TRUE;
		}

		//Return result, unset variable.
		return $cat_exists;
		unset($cat_exists);
	}
	else
		return FALSE;
}

/**
 * Create a blog category.
 *
 * @param string $category The name of the category that needs to be created.
 */
function blog_create_category($category) {
	//Filter category name from inappropriate characters.
	$category = str_replace('"', '', $category);
	$category = str_replace('\'', '', $category);
	$category = str_replace(',', '', $category);
	$category = str_replace(',', '', $category);
	$category = str_replace('/', '', $category);
	$category = str_replace('\\', '', $category);

	//Read out existing categories, if they exist.
	if (file_exists('data/settings/modules/blog/categories.dat'))
		$categories = file_get_contents('data/settings/modules/blog/categories.dat');

	//If there are existing categories, but category we want to create doesn't exist yet; add it to array.
	if (isset($categories) && !blog_category_exists($category)) {
		//Load existing categories in array.
		$categories = explode(',', $categories);

		//Determine the array number for our new category.
		$num = 0;
		while (isset($categories[$num]))
			$num++;

		//Add new category to array.
		$categories[$num] = $category;
	}

	//If the category already exists, don't add it to array.
	elseif (isset($categories) && blog_category_exists($category)) {
		//Load existing categories in array, but don't add new category.
		$categories = explode(',', $categories);
	}

	//If there are no categories yet, just set new category in array.
	elseif (!isset($categories))
		$categories[0] = $category;

	//Now, sort the array.
	natcasesort($categories);
	//Reset keys of array.
	$categories = array_merge(array(), $categories);

	//Open config file to save categories.
	$file = fopen('data/settings/modules/blog/categories.dat', 'w');

	foreach($categories as $number => $name) {
		$number_next = $number + 1;
		if (isset($categories[$number_next]))
			fputs($file, $name.',');
		else
			fputs($file, $name);
	}
	unset($number);

	//Close file, and chmod it.
	fclose($file);
	chmod('data/settings/modules/blog/categories.dat', 0777);
}

/**
 * Delete a blog category.
 *
 * @param string $category The name of the category that needs to be deleted.
 */
function blog_delete_category($category) {
	//Check if config file exists.
	if (file_exists('data/settings/modules/blog/categories.dat')) {
		$categories = file_get_contents('data/settings/modules/blog/categories.dat');
		$categories = str_replace("\n", '', $categories);

		//If category is the only one, delete config file.
		if ($category == $categories) {
			unlink('data/settings/modules/blog/categories.dat');
		}

		//If category is not last in list, delete it.
		elseif (preg_match('/\b'.$category.',/', $categories, $matches) && blog_category_exists($category)) {
			$categories = preg_replace('/\b'.$category.',/', '', $categories);
			//Save new config file.
			$file = fopen('data/settings/modules/blog/categories.dat', 'w');
			fputs($file, $categories);
			fclose($file);
			chmod('data/settings/modules/blog/categories.dat', 0777);
		}

		//If category is last in list, delete it.
		elseif (preg_match('/,\b'.$category.'\b/', $categories, $matches) && blog_category_exists($category)) {
			$categories = preg_replace('/,\b'.$category.'\b/', '', $categories);
			//Save new config file.
			$file = fopen('data/settings/modules/blog/categories.dat', 'w');
			fputs($file, $categories);
			fclose($file);
			chmod('data/settings/modules/blog/categories.dat', 0777);
		}
	}
}
?>