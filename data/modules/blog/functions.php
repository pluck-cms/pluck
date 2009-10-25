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

define('BLOG_POSTS_DIR', 'data/settings/modules/blog/posts');
define('BLOG_CATEGORIES_DIR', 'data/settings/modules/blog/categories');

/**
 * Save or edit a blog post.
 *
 * @param string $title The title of the blog post.
 * @param string $category The category of the blog post.
 * @param string $content The contents of the blog post (the post itself).
 * @param string $seoname The current seoname of the blog post, if it already exists.
 */
function blog_save_post($title, $category, $content, $current_seoname = null) {
	//Sanitize variables.
	$title = sanitize($title);
	$content = sanitize($content, false);

	$seoname = seo_url($title);

	if (!empty($current_seoname)) {
		$current_filename = blog_get_post_filename($current_seoname);
		$parts = explode('.', $current_filename);
		$number = $parts[0];

		//Get the post time.
		include BLOG_POSTS_DIR.'/'.$current_filename;

		if ($seoname != $current_seoname) {
			unlink(BLOG_POSTS_DIR.'/'.$current_filename);

			if (is_dir(BLOG_POSTS_DIR.'/'.$current_seoname))
				rename(BLOG_POSTS_DIR.'/'.$current_seoname, BLOG_POSTS_DIR.'/'.$seoname);
		}
	}

	else {
		$files = read_dir_contents(BLOG_POSTS_DIR, 'files');

		//Find the number.
		if ($files) {
			$number = count($files);
			$number++;
		}
		else
			$number = 1;

		$post_time = time();
	}

	//Save information.
	$data['post_title']    = $title;
	$data['post_category'] = $category;
	$data['post_content']  = $content;
	$data['post_time']     = $post_time;

	save_file(BLOG_POSTS_DIR.'/'.$number.'.'.$seoname.'.php', $data);

	//Return seoname under which post has been saved (to allow for redirect).
	return $seoname;
}

function blog_get_post_filename($seoname) {
	$posts = read_dir_contents(BLOG_POSTS_DIR, 'files');

	if ($posts) {
		foreach ($posts as $filename) {
			if (strpos($filename, '.'.$seoname.'.'))
				return $filename;
		}
		unset($filename);
	}

	return false;
}

function blog_get_post_seoname($filename) {
	if (file_exists(BLOG_POSTS_DIR.'/'.$filename)) {
		$parts = explode('.', $filename);
		return $parts[1];
	}

	else
		return false;
}

/**
 * Save/add a reaction to a blog post.
 *
 * @param string $post The seoname of the blog post to which the reaction should be added.
 * @param string $name The name of the person posting the reaction.
 * @param string $email The e-mail of the person posting the reaction.
 * @param string $message The message of the reaction.
 * @param int $id If an existing reaction needs to be edited, the id of the reaction should go here.
 */
function blog_save_reaction($post, $name, $email, $website, $message, $id = null) {
	global $lang;

	//Sanitize variables.
	$name = sanitize($name);
	$message = sanitize($message);

	//Have to make sure that the dir exists.
	if (!is_dir(BLOG_POSTS_DIR.'/'.$post)) {
		mkdir(BLOG_POSTS_DIR.'/'.$post);
		chmod(BLOG_POSTS_DIR.'/'.$post, 0777);
	}

	if (!empty($id)) {
		include BLOG_POSTS_DIR.'/'.$post.'/'.$id.'.php';

		$number = $id;
	}

	else {
		$files = read_dir_contents(BLOG_POSTS_DIR.'/'.$post, 'files');

		if ($files) {
			$number = count($files);
			$number++;
		}

		else
			$number = 1;

		$post_time = time();
	}

	$data['reaction_name']    = $name;
	$data['reaction_email']   = $email;
	$data['reaction_website'] = $website;
	$data['reaction_message'] = $message;
	$data['reaction_time']    = $post_time;

	save_file(BLOG_POSTS_DIR.'/'.$post.'/'.$number.'.php', $data);
}

function blog_get_reaction($post, $id) {
	if (file_exists(BLOG_POSTS_DIR.'/'.$post.'/'.$id.'.php')) {
		include BLOG_POSTS_DIR.'/'.$post.'/'.$id.'.php';

		return array(
			'id'      => $id,
			'name'    => $reaction_name,
			'email'   => $reaction_email,
			'website' => $reaction_website,
			'message' => $reaction_message,
			'date'    => blog_date_convert($reaction_time),
			'time'    => blog_time_convert($reaction_time)
		);
	}

	else
		return false;
}

function blog_get_reactions($post) {
	$files = read_dir_contents(BLOG_POSTS_DIR.'/'.$post, 'files');

	if ($files) {
		asort($files);

		foreach ($files as $reaction) {
			include BLOG_POSTS_DIR.'/'.$post.'/'.$reaction;
			$parts = explode('.', $reaction);
			$reactions[] = blog_get_reaction($post, $parts[0]);
		}
		unset($reaction);

		return $reactions;
	}

	else
		return false;
}

/**
 * Load posts in an array. Will return FALSE if no posts exist.
 */
function blog_get_posts() {
	$files = read_dir_contents(BLOG_POSTS_DIR, 'files');

	if ($files) {
		arsort($files);

		foreach ($files as $post)
			$posts[] = blog_get_post(blog_get_post_seoname($post));
		unset($post);

		return $posts;
	}

	else
		return false;
}

function blog_get_post($seoname) {
	if (file_exists(BLOG_POSTS_DIR.'/'.blog_get_post_filename($seoname))) {
		include BLOG_POSTS_DIR.'/'.blog_get_post_filename($seoname);

		return array(
			'title'            => $post_title,
			'seoname'          => $seoname,
			'content'          => $post_content,
			'category'         => blog_get_category_title($post_category),
			'category_seoname' => $post_category,
			'date'             => blog_date_convert($post_time),
			'time'             => blog_time_convert($post_time)
		);
	}

	else
		return false;
}

/**
 * Load categories in an array. Will return FALSE if no categories exist.
 */
function blog_get_categories() {
	$files = read_dir_contents(BLOG_CATEGORIES_DIR, 'files');

	if ($files) {
		foreach ($files as $category) {
			include BLOG_CATEGORIES_DIR.'/'.$category;
			$categories[] = array(
				'title'   => $category_title,
				'seoname' => str_replace('.php', '', $category)
			);
		}
		unset($category);

		return $categories;
	}

	else
		return false;
}

function blog_get_category_title($seoname) {
	global $lang;

	if (blog_category_exists($seoname)) {
		include BLOG_CATEGORIES_DIR.'/'.$seoname.'.php';
		return $category_title;
	}

	elseif (empty($seoname) || !blog_category_exists($seoname))
		return $lang['blog']['no_cat'];
	else
		return false;
}

/**
 * Checks whether a blog category exists. Returns TRUE or FALSE.
 *
 * @param string $category The category to check for.
 */
function blog_category_exists($category) {
	if (blog_get_categories()) {
		$files = blog_get_categories();

		foreach ($files as $file) {
			if ($file['seoname'] == $category)
				return true;
		}
		unset($file);
	}

	return false;
}

/**
 * Create a blog category.
 *
 * @param string $category The name of the category that needs to be created.
 */
function blog_create_category($category) {
	$data['category_title'] = sanitize($category);

	save_file(BLOG_CATEGORIES_DIR.'/'.seo_url($category).'.php', $data);
}

function blog_date_convert($timestamp) {
	return date('j/m-y', $timestamp);
}

function blog_time_convert($timestamp) {
	return date('H:i', $timestamp);
}
?>