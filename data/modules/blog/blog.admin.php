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

function blog_page_admin_list() {
	global $lang_blog, $lang_blog6, $lang_blog19, $lang_blog21, $lang_blog11, $lang_blog12, $lang_blog10;
	$module_page_admin[] = array(
		'func'  => 'blog',
		'title' => $lang_blog
	);
	$module_page_admin[] = array(
		'func'  => 'deletecategory',
		'title' => $lang_blog6
	);
	$module_page_admin[] = array(
		'func'  => 'editreactions',
		'title' => $lang_blog19
	);
	$module_page_admin[] = array(
		'func'  => 'deletereactions',
		'title' => $lang_blog21
	);
	$module_page_admin[] = array(
		'func'  => 'editpost',
		'title' => $lang_blog11
	);
	$module_page_admin[] = array(
		'func'  => 'deletepost',
		'title' => $lang_blog12
	);
	$module_page_admin[] = array(
		'func'  => 'newpost',
		'title' => $lang_blog10
	);
	return $module_page_admin;
}

//---------------
// Page: admin
//---------------
function blog_page_admin_blog() {
	global $lang_blog24, $lang_blog10, $lang_blog9, $lang_blog11, $lang_blog19, $lang_blog12, $lang_albums14, $lang_blog3, $lang_blog4, $lang_blog5, $lang_blog6, $lang_blog14, $lang;
	?>
	<p>
		<strong><?php echo $lang_blog24; ?></strong>
	</p>
<?php
showmenudiv($lang_blog10, false, 'data/image/newpage.png', '?module=blog&amp;page=newpost', false);
?>
	<span class="kop2"><?php echo $lang_blog9; ?></span><br />
<?php
//Display existing posts, but only if post-index file exists.
if (file_exists('data/settings/modules/blog/post_index.dat')) {
	$handle = fopen('data/settings/modules/blog/post_index.dat', 'r');
	while (!feof($handle)) {
		$file = fgets($handle, 4096);
		//Filter out line breaks.
		$file = str_replace ("\n", '', $file);
		//Check if post exists.
		if (file_exists('data/settings/modules/blog/posts/'.$file) && is_file('data/settings/modules/blog/posts/'.$file)) {
			//Include post information.
			include ('data/settings/modules/blog/posts/'.$file);
			?>
				<div class="menudiv" style="margin: 10px;">
					<span>
						<img src="data/modules/blog/images/blog.png" alt="" />
					</span>
					<span style="width: 500px;">
						<span style="font-size: 17pt;"><?php echo $post_title; ?></span>
					</span>
					<span>
						<a href="?module=blog&amp;page=editpost&amp;var1=<?php echo $file; ?>">
							<img src="data/image/edit.png" title="<?php echo $lang_blog11; ?>" alt="<?php echo $lang_blog11; ?>" />
						</a>
					</span>
					<span>
						<a href="?module=blog&amp;page=editreactions&amp;var1=<?php echo $file; ?>">
							<img src="data/modules/blog/images/reactions.png" title="<?php echo $lang_blog19; ?>" alt="<?php echo $lang_blog19; ?>" />
						</a>
					</span>
					<span>
						<a href="?module=blog&amp;page=deletepost&amp;var1=<?php echo $file; ?>">
							<img src="data/image/delete_from_trash.png" title="<?php echo $lang_blog12; ?>" alt="<?php echo $lang_blog12; ?>" />
						</a>
					</span>
					<br />
					<span style="margin-top: 10px">
						<span style="font-size: 12px; font-style: italic">
							<?php
							//Show post date and category.
							echo $post_month.'-'.$post_day.'-'.$post_year;
							if (isset($post_category))
								echo ', '.$lang_blog14.' '.$post_category;
							?>
						</span>
					</span>
				</div>
			<?php
		}
	}
	//Close module-dir.
	fclose($handle);
}

//If no posts exist, display message.
else
	echo '<span class="kop4">'.$lang_albums14.'</span><br />';
?>
	<br />
	<span class="kop2"><?php echo $lang_blog3; ?></span>
	<br />
<?php
//If there already are categories.
if (file_exists('data/settings/modules/blog/categories.dat')) {
	//Load them
	$categories = file_get_contents('data/settings/modules/blog/categories.dat');

	//Then in an array.
	$categories = split(',',$categories);

	//And show them.
	echo '<div>';
	foreach ($categories as $key => $name) {
	?>
		<span><?php echo $name; ?> &nbsp;</span>
		<span>
			<a href="?module=blog&amp;page=deletecategory&amp;var1=<?php echo $name; ?>">
				<img src="data/image/delete_from_trash_small.png" width="16" height="16" alt="<?php echo $lang_blog6; ?>" title="<?php echo $lang_blog6; ?>" />
			</a>
		</span><br />
	<?php
	}
	unset($key);
	echo '</div>';
}

//If no categories exist, show a message.
else
	echo '<span class="kop4">'.$lang_albums14.'</span><br />';

//New category.
?>
<br />
<label class="kop2" for="cont1"><?php echo $lang_blog4; ?></label>
<br />
<form method="post" action="">
	<span class="kop4"><?php echo $lang_blog5; ?></span>
	<br />
	<input name="cat_name" id="cont1" type="text" />
	<input type="submit" name="Submit" value="<?php echo $lang['general']['save']; ?>" />
</form>

<?php
//When form is submitted.
if (isset($_POST['cat_name'])) {
	$cat_name = $_POST['cat_name'];
	//Filter category name from inappropriate characters.
	$cat_name = str_replace ("\"","", $cat_name);
	$cat_name = str_replace ("'","", $cat_name);
	$cat_name = str_replace (",","", $cat_name);
	$cat_name = str_replace (".","", $cat_name);
	$cat_name = str_replace ("/","", $cat_name);
	$cat_name = str_replace ("\\","", $cat_name);

	//Read out existing categories, if they exist.
	if (file_exists('data/settings/modules/blog/categories.dat'))
		$categories = file_get_contents('data/settings/modules/blog/categories.dat');

	//Make sure category doesn't already exist.
	//FIXME: Replace ereg with strpos.
	if (!ereg($cat_name.',',$categories) || !ereg(','.$cat_name,$categories) || !isset($categories)) {

		//If there are already existing categories...
		if (file_exists('data/settings/modules/blog/categories.dat')) {
			//Load existing categories in array
			$categories = split(',',$categories);

			//Determine the array number for our new category.
			$num = 0;
			while (isset($categories[$num]))
				$num++;

			//Add new category to array.
			$categories[$num] = $cat_name;
		}

		//If there are no categories yet, just set new category in array.
		else
			$categories[0] = $cat_name;

		//Now, sort the array.
		natsort($categories);
		//Reset keys of array.
		$categories = array_merge(array(), $categories);

		//Open config file to save categories.
		$file = fopen('data/settings/modules/blog/categories.dat', 'w');

		foreach($categories as $number => $name) {
			$number_next = $number + 1;
			if (isset($categories[$number_next]))
				fputs($file,$name.',');
			else
				fputs($file,$name);
		}
		unset($number);

		//Close file, and chmod it.
		fclose($file);
		chmod('data/settings/modules/blog/categories.dat', 0777);
	}
	//Redirect user.
	redirect('?module=blog', 0);
}
?>
<p>
	<a href="?action=modules">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
</p>

<?php
}

//---------------
// Page: deletecategory
//---------------
function blog_page_admin_deletecategory() {
	global $var1;
	//Check if config file exists
	if(file_exists('data/settings/modules/blog/categories.dat')) {
		$categories = file_get_contents('data/settings/modules/blog/categories.dat');

		//Check if category exists in file, and if it has been saved comma seperated or not
		//If category is not last in list:
		if(ereg($var1.',',$categories)) {
			$categories = str_replace($var1.',','',$categories);
			//Open config file
			$file = fopen('data/settings/modules/blog/categories.dat', 'w');
			//Save categories
			fputs($file,$categories);
			//Close file, and chmod it
			fclose($file);
			chmod('data/settings/modules/blog/categories.dat', 0777);
		}
		//If category is last in list...
		elseif(ereg($var1,$categories)) {
			//...but category is not the only one
			if(ereg(','.$var1,$categories)) {
				$categories = str_replace(','.$var1,'',$categories);
				//Open config file
				$file = fopen('data/settings/modules/blog/categories.dat', 'w');
				//Save categories
				fputs($file,$categories);
				//Close file, and chmod it
				fclose($file);
				chmod('data/settings/modules/blog/categories.dat', 0777);
			}
			//...and category is the only one
			elseif(ereg($var1,$categories)) {
				unlink('data/settings/modules/blog/categories.dat');
			}
		}
	}

	//Redirect
	redirect('?module=blog', 0);
}

//---------------
// Page: editreactions
//---------------
function blog_page_admin_editreactions() {
	global $lang, $lang_blog20, $lang_blog21, $lang_contact6, $var1, $page;
?>

<p><b><?php echo $lang_blog20; ?></b></p>
<?php
//Include blog post, if it exists
if (file_exists('data/settings/modules/blog/posts/'.$var1)) {
	include_once('data/settings/modules/blog/posts/'.$var1);

//Display reactions
if(isset($post_reaction_title)) {
	foreach($post_reaction_title as $key => $value) {
		$post_reaction_content[$key] = str_replace('<br />',"\n",$post_reaction_content[$key]); ?>

<div class="menudiv" style="margin: 10px;">
	<table>
		<tr>
			<td>
				<img src="data/modules/blog/images/reactions.png" alt="" border="0">
			</td>
			<td style="width: 600px;">
				<form method="post" action="">
					<b><?php echo $lang['general']['title']; ?></b><br />
			  		<input name="title" type="text" value="<?php echo $post_reaction_title[$key]; ?>" /><br /><br />

					<textarea name="message" rows="5" cols="65"><?php echo $post_reaction_content[$key]; ?></textarea><br /><br />

					<input name="edit_key" type="hidden" value="<?php echo $key; ?>" />
					<input type="submit" name="Submit" value="<?php echo $lang['general']['save']; ?>" />
				</form>
			</td>
			<td>
				<a href="?module=blog&page=deletereactions&post=<?php echo $var1; ?>&key=<?php echo $key; ?>">
					<img src="data/image/delete_from_trash.png" border="0" title="<?php echo $lang_blog21; ?>" alt="<?php echo $lang_blog21; ?>" />
				</a>
			</td>
		</tr>
	</table>
</div>

<?php
		}
		unset($key);
	}
}

//If form is posted...
if(isset($_POST['Submit'])) {

	//Check if everything has been filled in
	if((!isset($_POST['title'])) || (!isset($_POST['message']))) { ?>
		<span style="color: red;"><?php echo $lang_contact6; ?></span>
	<?php
		exit;
	}

	else {
		//Fetch key variable
		$edit_key = $_POST['edit_key'];

		//Delete unwanted characters from post information
		$title = sanitize($_POST['title']);
		$name = sanitize($post_reaction_name[$edit_key]);
		$message = sanitize($_POST['message']);

		//Strip slashes from post itself too
		$post_title = sanitize($post_title);
		$post_category = sanitize($post_category);
		$post_content = sanitize($post_content, false);

		//Then, save existing post information
		$file = fopen('data/settings/modules/blog/posts/'.$var1, 'w');
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
				//If it's the post we want to edit
				if ($reaction_key == $edit_key) {
					//And save the modified reaction
					fputs($file, '$post_reaction_title['.$reaction_key.'] = \''.$title.'\';'."\n"
					.'$post_reaction_name['.$reaction_key.'] = \''.$name.'\';'."\n"
					.'$post_reaction_content['.$reaction_key.'] = \''.$message.'\';'."\n"
					.'$post_reaction_day['.$reaction_key.'] = \''.$post_reaction_day[$reaction_key].'\';'."\n"
					.'$post_reaction_month['.$reaction_key.'] = \''.$post_reaction_month[$reaction_key].'\';'."\n"
					.'$post_reaction_year['.$reaction_key.'] = \''.$post_reaction_year[$reaction_key].'\';'."\n"
					.'$post_reaction_time['.$reaction_key.'] = \''.$post_reaction_time[$reaction_key].'\';'."\n");
				}
				//If this is not the reaction we want to edit
				else {
					//Sanitize variables first
					$post_reaction_title[$reaction_key] = sanitize($post_reaction_title[$reaction_key]);
					$post_reaction_name[$reaction_key] = sanitize($post_reaction_name[$reaction_key]);
					$post_reaction_content[$reaction_key] = sanitize($post_reaction_content[$reaction_key]);

					//Then save it
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
		fputs($file, '?>');
		fclose($file);
		chmod('data/settings/modules/blog/posts/'.$var1, 0777);
		redirect('?module=blog&page=editreactions&var1='.$var1, 0);
	}
}
?>

<p>
	<a href="?module=blog">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
</p>

<?php
}

//---------------
// Page: deletereactions
//---------------
function blog_page_admin_deletereactions() {
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
				unset($reaction_key);

				fputs($file, '?>');
				fclose($file);
				chmod('data/settings/modules/blog/posts/'.$post, 0777);
				redirect('?module=blog&page=editreactions&var1='.$post, 0);
			}
		}
	}

	//Redirect
	else {
		redirect('?module=blog', 0);
	}
}

//---------------
// Page: editpost
//---------------
function blog_page_admin_editpost() {
	global $lang, $lang_page8, $lang_blog27, $lang_blog26, $lang_blog25, $var1, $cont1, $cont2, $cont3;

	//Include the postinformation
	if (file_exists('data/settings/modules/blog/posts/'.$var1))
		include('data/settings/modules/blog/posts/'.$var1);
	else
		exit;
?>

<div class="rightmenu">
<?php echo $lang_page8; ?><br />
<?php
	//Generate the menu on the right
	read_imagesinpages('images');
?>
</div>

<form method="post" action="">
	<span class="kop2"><?php echo $lang['general']['title']; ?></span><br>
	<input name="cont1" type="text" value="<?php echo $post_title; ?>">
	<br /><br />
	<span class="kop2"><?php echo $lang_blog26; ?></span>
	<br />
	<select name="cont2">
		<option value="<?php echo $lang_blog27; ?>"> <?php echo $lang_blog25; ?></option>
<?php
	//If there are categories
	if(file_exists('data/settings/modules/blog/categories.dat')) {
		//Load them
		$categories = file_get_contents('data/settings/modules/blog/categories.dat');

		//Then in an array
		$categories = split(',',$categories);

		//And show them
		foreach($categories as $key => $name) {
			if($post_category == $name)
				echo '<option value="'.$name.'" selected />'.$name.'</option>';
			else
				echo '<option value="'.$name.'" />'.$name.'</option>';
		}
		unset($key);
	}
?>
	</select><br /><br />

	<span class="kop2"><?php echo $lang['general']['contents']; ?></span><br />
	<textarea class="tinymce" name="cont3" cols="70" rows="20"><?php echo $post_content; ?></textarea><br>

	<input type="submit" name="Submit" value="<?php echo $lang['general']['save']; ?>" />
	<input type="button" name="Cancel" value="<?php echo $lang['general']['cancel']; ?>" onclick="javascript: window.location='?module=blog';" />
</form>

<?php
	//If form is posted...
	if(isset($_POST['Submit'])) {

		//Sanitize variables
		$cont1 = sanitize($cont1);
		$cont2 = sanitize($cont2);
		$cont3 = sanitize($cont3, false);

		//Generate filename
		$newfile = strtolower($cont1);
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

		//Udate the post index file, if necessary
		if ($var1 != $newfile.'.php') {
			//Change old filename into new filename in post index
			if (file_exists('data/settings/modules/blog/post_index.dat')) {
				//Get post index
				$contents = file_get_contents('data/settings/modules/blog/post_index.dat');
					//Check if post index contains old filename, and change it into new filename
				if (ereg($var1."\n",$contents))
					$contents = str_replace($var1."\n", $newfile.'.php'."\n", $contents);
				elseif (ereg("\n".$var1,$contents))
					$contents = str_replace("\n".$var1, "\n".$newfile.'.php', $contents);
				elseif (ereg($var1,$contents))
					$contents = str_replace($var1, $newfile.'.php', $contents);

				//Save updated post index
				$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
				fputs($file, $contents);
				fclose($file);
				chmod('data/settings/modules/blog/post_index.dat', 0777);
			}

			//Check if the old post exists, then delete it
			if (file_exists('data/settings/modules/blog/posts/'.$var1)) {
				//Delete the post
				unlink('data/settings/modules/blog/posts/'.$var1);
			}

		//Set the new post file to current file
		$var = $newfile.'.php';
		}

		//Save information
		$file = fopen('data/settings/modules/blog/posts/'.$var1, 'w');
		fputs($file, '<?php'."\n"
		.'$post_title = \''.$cont1.'\';'."\n"
		.'$post_category = \''.$cont2.'\';'."\n"
		.'$post_content = \''.$cont3.'\';'."\n"
		.'$post_day = \''.$post_day.'\';'."\n"
		.'$post_month = \''.$post_month.'\';'."\n"
		.'$post_year = \''.$post_year.'\';'."\n"
		.'$post_time = \''.$post_time.'\';'."\n");

		//Check if there are reactions
		if(isset($post_reaction_title)) {
			foreach($post_reaction_title as $reaction_key => $value) {

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
		chmod('data/settings/modules/blog/posts/'.$var1, 0777);

		//Redirect user
		redirect('?module=blog', 0);
	}
}

//---------------
// Page: deletepost
//---------------
function blog_page_admin_deletepost() {
	global $var1;

	//First, update the post index
	if(file_exists('data/settings/modules/blog/post_index.dat')) {
		//Get post index
		$contents = file_get_contents('data/settings/modules/blog/post_index.dat');

		//Check if post index contains post we want to delete, and filter out the post
		if(ereg($var1."\n",$contents)) {
			$contents = str_replace($var1."\n",'',$contents);
		}
		elseif(ereg("\n".$var1,$contents)) {
			$contents = str_replace("\n".$var1,'',$contents);
		}
		elseif(ereg($var1,$contents)) {
			$contents = str_replace($var1,'',$contents);
		}

		//Save updated post index
		$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
		fputs($file,$contents);
		fclose($file);

		//Reload contents of post index in variable
		$contents = file_get_contents('data/settings/modules/blog/post_index.dat');
		//If variable/file is empty, delete post index
		if(empty($contents)) {
			unlink('data/settings/modules/blog/post_index.dat');
		}
	}

	//Check if post exists, then delete it
	if(file_exists('data/settings/modules/blog/posts/'.$var1)) {
		//Delete the post
		unlink('data/settings/modules/blog/posts/'.$var1);
	}

	//Redirect
	redirect('?module=blog', 0);
}


//---------------
// Page: newpost
//---------------
function blog_page_admin_newpost() {
	global $lang, $lang_page8, $lang_blog27, $lang_blog26, $lang_blog25, $var1, $cont1, $cont2, $cont3;
	?>

	<div class="rightmenu">
		<?php echo $lang_page8; ?><br />
		<?php
			//Generate the menu on the right
			read_imagesinpages('images');
		?>
	</div>

	<form method="post" action="">
		<span class="kop2"><?php echo $lang['general']['title']; ?></span><br />
		<input name="cont1" type="text" value=""><br /><br />

		<span class="kop2"><?php echo $lang_blog26; ?></span><br />
		<select name="cont2">
			<option value="<?php echo $lang_blog27; ?>" /> <?php echo $lang_blog25; ?>
	<?php
	//If there are categories
	if(file_exists('data/settings/modules/blog/categories.dat')) {
		//Load them
		$categories = file_get_contents('data/settings/modules/blog/categories.dat');

		//Then in an array
		$categories = split(',',$categories);

		//And show them
		foreach($categories as $key => $name)
			echo '<option value="'.$name.'" />'.$name;

		unset($key);
	}
	?>
		</select><br /><br />

		<span class="kop2"><?php echo $lang['general']['contents']; ?></span><br />
		<textarea class="tinymce" name="cont3" cols="70" rows="20"></textarea><br />

		<input type="submit" name="Submit" value="<?php echo $lang['general']['save']; ?>">
		<input type="button" name="Cancel" value="<?php echo $lang['general']['cancel']; ?>" onclick="javascript: window.location='?module=blog';">
	</form>

	<?php
	//If form is posted...
	if(isset($_POST['Submit'])) {

		//Sanitize variables
		$cont1 = sanitize($cont1);
		$cont2 = sanitize($cont2);
		$cont3 = sanitize($cont3, false);

		//Determine the date
		$day = date("d");
		$month = date("m");
		$year = date("Y");
		$time = date("H:i");

		//Check if 'posts' directory exists, if not; create it
		if(!file_exists('data/settings/modules/blog/posts')) {
			mkdir('data/settings/modules/blog/posts',0777);
			chmod('data/settings/modules/blog/posts',0777);
		}

		//Generate filename
		$newfile = strtolower($cont1);
		$newfile = str_replace('.','',$newfile);
		$newfile = str_replace(',','',$newfile);
		$newfile = str_replace('?','',$newfile);
		$newfile = str_replace(':','',$newfile);
		$newfile = str_replace('<','',$newfile);
		$newfile = str_replace('>','',$newfile);
		$newfile = str_replace('=','',$newfile);
		$newfile = str_replace('"','',$newfile);
		$newfile = str_replace('\'','',$newfile);
		$newfile = str_replace('/','',$newfile);
		$newfile = str_replace("\\",'',$newfile);
		$newfile = str_replace('  ','-',$newfile);
		$newfile = str_replace(' ','-',$newfile);

		//Make sure chosen filename doesn't exist
		if(file_exists('data/settings/modules/blog/posts/'.$newfile.'.php')) {
			$newfile = $newfile.'-new';
		}

		//Create/update the post_index.dat file
		if(file_exists('data/settings/modules/blog/post_index.dat')) {
			$contents = file_get_contents('data/settings/modules/blog/post_index.dat');
			$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
			if(!empty($contents)) {
				fputs($file,$newfile.'.php'."\n".$contents);
			}
			else {
				fputs($file,$newfile.'.php');
			}
		}
		else {
			$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
			fputs($file,$newfile.'.php');
		}
		fclose($file);
		unset($file);
		chmod('data/settings/modules/blog/post_index.dat', 0777);

		//Save information
		$file = fopen('data/settings/modules/blog/posts/'.$newfile.'.php', 'w');
		fputs($file, '<?php'."\n"
		.'$post_title = \''.$cont1.'\';'."\n"
		.'$post_category = \''.$cont2.'\';'."\n"
		.'$post_content = \''.$cont3.'\';'."\n"
		.'$post_day = \''.$day.'\';'."\n"
		.'$post_month = \''.$month.'\';'."\n"
		.'$post_year = \''.$year.'\';'."\n"
		.'$post_time = \''.$time.'\';'."\n"
		.'?>');
		fclose($file);
		chmod('data/settings/modules/blog/posts/'.$newfile.'.php',0777);

		//Redirect user
		redirect('?module=blog', 0);
	}
}
?>