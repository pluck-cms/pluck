<?php

/**
 * Save or edit a blog post.
 *
 * @param string $title The title of the blog post.
 * @param string $category The category of the blog post.
 * @param string $content The contents of the blog post (the post itself).
 * @param string $name The filename of the blog post, if the post already exists (with .php).
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
	$newfile = str_replace("\\", '', $newfile);
	$newfile = str_replace('-', '', $newfile);
	$newfile = str_replace('  ', '-', $newfile);
	$newfile = str_replace(' ', '-', $newfile);

	//Make sure chosen filename doesn't exist
	if(file_exists('data/settings/modules/blog/posts/'.$newfile.'.php'))
		$newfile = $newfile.'-new';
	//Include extension.
	$newfile = $newfile.'.php';

	//If post already exists, check if we need to update the post index (if the title has changed).
	if (isset($name) && $name != $newfile) {
		//Change old filename into new filename in post index.
		if (file_exists('data/settings/modules/blog/post_index.dat')) {
			//Get post index.
			$contents = file_get_contents('data/settings/modules/blog/post_index.dat');
				//Check if post index contains old filename, and change it into new filename.
			if (ereg($name."\n",$contents))
				$contents = str_replace($name."\n", $newfile."\n", $contents);
			elseif (ereg("\n".$name,$contents))
				$contents = str_replace("\n".$name, "\n".$newfile, $contents);
			elseif (ereg($name, $contents))
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
?>