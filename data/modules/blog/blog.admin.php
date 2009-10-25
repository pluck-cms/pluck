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

require_once ('data/modules/blog/functions.php');

function blog_page_admin_list() {
	global $lang;
	$module_page_admin[] = array(
		'func'  => 'blog',
		'title' => $lang['blog']['title']
	);
	$module_page_admin[] = array(
		'func'  => 'deletecategory',
		'title' => $lang['blog']['delete_cat']
	);
	$module_page_admin[] = array(
		'func'  => 'editreactions',
		'title' => $lang['blog']['edit_reactions']
	);
	$module_page_admin[] = array(
		'func'  => 'deletereactions',
		'title' => $lang['blog']['delete_reaction']
	);
	$module_page_admin[] = array(
		'func'  => 'editpost',
		'title' => $lang['blog']['edit_post']
	);
	$module_page_admin[] = array(
		'func'  => 'deletepost',
		'title' => $lang['blog']['delete_post']
	);
	$module_page_admin[] = array(
		'func'  => 'newpost',
		'title' => $lang['blog']['new_post']
	);
	return $module_page_admin;
}

//---------------
// Page: admin
//---------------
function blog_page_admin_blog() {
	global $cont1, $lang;
	?>
	<p>
		<strong><?php echo $lang['blog']['message']; ?></strong>
	</p>
	<?php showmenudiv($lang['blog']['new_post'], false, 'data/image/newpage.png', '?module=blog&amp;page=newpost', false); ?>
	<span class="kop2"><?php echo $lang['blog']['posts']; ?></span>
	<br />
	<?php
	//Display existing posts.
	if (blog_get_posts()) {
		//Load posts in array.
		$posts = blog_get_posts();

		foreach ($posts as $post) {
		?>
			<div class="menudiv">
				<span>
					<img src="data/modules/blog/images/blog.png" alt="" />
				</span>
				<span class="title-page"><?php echo $post['title']; ?></span>
				<span>
					<a href="?module=blog&amp;page=editpost&amp;var1=<?php echo $post['seoname']; ?>">
						<img src="data/image/edit.png" title="<?php echo $lang['blog']['edit_post']; ?>" alt="<?php echo $lang['blog']['edit_post']; ?>" />
					</a>
				</span>
				<?php
				if (blog_get_reactions($post['seoname'])):
				?>
					<span>
						<a href="?module=blog&amp;page=editreactions&amp;var1=<?php echo $post['seoname']; ?>">
							<img src="data/modules/blog/images/reactions.png" title="<?php echo $lang['blog']['edit_reactions']; ?>" alt="<?php echo $lang['blog']['edit_reactions']; ?>" />
						</a>
					</span>
				<?php
				endif;
				?>
				<span>
					<a href="?module=blog&amp;page=deletepost&amp;var1=<?php echo $post['seoname']; ?>">
						<img src="data/image/delete_from_trash.png" title="<?php echo $lang['blog']['delete_post']; ?>" alt="<?php echo $lang['blog']['delete_post']; ?>" />
					</a>
				</span>
				<br />
				<span>
					<span style="font-size: 12px; font-style: italic">
						<?php
						//Show post date and category.
						echo $post['date'].' '.$lang['blog']['at'].' '.$post['time'];
						if (isset($post['category']) && !empty($post['category']))
							echo ' '.$lang['blog']['in'].' '.$post['category'];
						?>
					</span>
				</span>
			</div>
		<?php
		}
	}

	//If no posts exist, display message.
	else
		echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span><br /><br />';
	?>
	<span class="kop2"><?php echo $lang['blog']['categories']; ?></span>
	<br />
	<?php
	//If there already are categories.
	if (blog_get_categories()) {
		//Get categories.
		$categories = blog_get_categories();

		//And show them.
		echo '<div>';
		foreach ($categories as $category) {
		?>
			<div class="menudiv">
				<span>
					<img src="data/image/page.png" alt="" />
				</span>
				<span class="title-page"><?php echo $category['title']; ?> &nbsp;</span>
				<span>
					<a href="?module=blog&amp;page=deletecategory&amp;var1=<?php echo $category['seoname']; ?>">
						<img src="data/image/delete_from_trash.png" alt="<?php echo $lang['blog']['delete_cat']; ?>" title="<?php echo $lang['blog']['delete_cat']; ?>" />
					</a>
				</span>
			</div>
		<?php
		}
		unset($category);
		echo '</div>';
	}

	//If no categories exist, show a message.
	else
		echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span><br /><br />';

	//New category.
	?>
		<form method="post" action="">
			<label class="kop2" for="cont1"><?php echo $lang['blog']['new_cat']; ?></label>
			<br />
			<span class="kop4"><?php echo $lang['blog']['new_cat_message']; ?></span>
			<br />
			<input name="cont1" id="cont1" type="text" />
			<input type="submit" name="Submit" value="<?php echo $lang['general']['save']; ?>" />
		</form>
	<?php
	//When form is submitted.
	if (isset($cont1) && !empty($cont1)) {
		blog_create_category($cont1);
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

	//Check if config file exists.
	if (file_exists(BLOG_CATEGORIES_DIR.'/'.$var1.'.php'))
		unlink(BLOG_CATEGORIES_DIR.'/'.$var1.'.php');

	redirect('?module=blog', 0);
}

//---------------
// Page: editreactions
//---------------
function blog_page_admin_editreactions() {
	global $lang, $var1, $page;
	?>
		<p>
			<strong><?php echo $lang['blog']['edit_reactions_message']; ?></strong>
		</p>
	<?php
	//Include blog post, if it exists
	if (file_exists('data/settings/modules/blog/posts/'.$var1)) {
		include_once('data/settings/modules/blog/posts/'.$var1);

		//Display reactions
		if(isset($post_reaction_title)) {
			foreach($post_reaction_title as $key => $value) {
				$post_reaction_content[$key] = str_replace('<br />',"\n",$post_reaction_content[$key]);
				//TODO: The rest of this function is one big mess. Clean it up somehow!
				?>
					<div class="menudiv">
						<table>
							<tr>
								<td>
									<img src="data/modules/blog/images/reactions.png" alt="" border="0">
								</td>
								<td style="width: 600px;">
									<form method="post" action="">
										<b><?php echo $lang['general']['title']; ?></b><br />
										<input name="title" type="text" value="<?php echo $post_reaction_title[$key]; ?>" />
										<br /><br />
										<textarea name="message" rows="5" cols="65"><?php echo $post_reaction_content[$key]; ?></textarea>
										<br /><br />
										<input name="edit_key" type="hidden" value="<?php echo $key; ?>" />
										<input type="submit" name="Submit" value="<?php echo $lang['general']['save']; ?>" />
									</form>
								</td>
								<td>
									<a href="?module=blog&page=deletereactions&post=<?php echo $var1; ?>&key=<?php echo $key; ?>">
										<img src="data/image/delete_from_trash.png" border="0" title="<?php echo $lang['blog']['delete_reaction']; ?>" alt="<?php echo $lang['blog']['delete_reaction']; ?>" />
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
	//Check if everything has been filled in.
	if(!isset($_POST['title']) || !isset($_POST['message'])) { ?>
		<span style="color: red;"><?php echo $lang['contactform']['fields']; ?></span>
		<?php exit;
	}

	else {
		//Include functions.
		require_once('data/modules/blog/functions.php');
		//Get information of blog post.
		include('data/settings/modules/blog/posts/'.$var1);
		//Save reaction.
		blog_save_reaction($var1, $_POST['title'], $post_reaction_name[$_POST['edit_key']], $_POST['message'], $_POST['edit_key']);
		//Redirect.
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
	//TODO: Make it work!
	redirect('?module=blog&page=editreactions&var1='.$post, 0);
}

//---------------
// Page: editpost
//---------------
function blog_page_admin_editpost() {
	global $lang, $var1, $cont1, $cont2, $cont3;

	//If form is posted...
	if (isset($_POST['save']) || isset($_POST['save_exit'])) {
		//Save blogpost.
		$seoname = blog_save_post($cont1, $cont2, $cont3, $var1);

		//Redirect user.
		if (isset($_POST['save']))
			redirect('?module=blog&page=editpost&var1='.$seoname, 0);
		else
			redirect('?module=blog', 0);
	}

	$post = blog_get_post($var1);
	?>
		<div class="rightmenu">
			<p><?php echo $lang['page']['items']; ?></p>
			<?php read_imagesinpages('images'); ?>
		</div>
		<form method="post" action="">
			<p>
				<label class="kop2" for="cont1"><?php echo $lang['general']['title']; ?></label>
				<br />
				<input name="cont1" id="cont1" type="text" value="<?php echo $post['title']; ?>" />
			</p>
			<p>
			<label class="kop2" for="cont2"><?php echo $lang['blog']['category']; ?></label>
			<br />
			<select name="cont2" id="cont2">
				<option value=""><?php echo $lang['blog']['choose_cat']; ?></option>
				<?php
				//If there are categories.
				if (blog_get_categories()) {
					$categories = blog_get_categories();

					foreach($categories as $category) {
						if ($post['category'] == $category['seoname'])
							echo '<option value="'.$category['seoname'].'" selected="selected">'.$category['title'].'</option>';
						else
							echo '<option value="'.$category['seoname'].'">'.$category['title'].'</option>';
					}
					unset($key);
				}
				?>
			</select>
			</p>
			<p>
				<label class="kop2" for="cont3"><?php echo $lang['general']['contents']; ?></label>
				<br />
				<textarea class="tinymce" name="cont3" id="cont3" cols="70" rows="20"><?php echo htmlspecialchars($post['content']); ?></textarea>
			</p>
			<?php show_common_submits('?module=blog', true); ?>
		</form>
	<?php
}

//---------------
// Page: deletepost
//---------------
function blog_page_admin_deletepost() {
	global $var1;

	//TODO: Make it work!

	//Redirect.
	redirect('?module=blog', 0);
}


//---------------
// Page: newpost
//---------------
function blog_page_admin_newpost() {
	global $lang, $var1, $cont1, $cont2, $cont3;

	//If form is posted...
	if (isset($_POST['save']) || isset($_POST['save_exit'])) {
		//Check if 'posts' directory exists, if not; create it.
		if (!is_dir(BLOG_POSTS_DIR)) {
			mkdir(BLOG_POSTS_DIR);
			chmod(BLOG_POSTS_DIR, 0777);
		}

		//Save blogpost.
		$seoname = blog_save_post($cont1, $cont2, $cont3);

		//Redirect user.
		if (isset($_POST['save']))
			redirect('?module=blog&page=editpost&var1='.$seoname, 0);
		else
			redirect('?module=blog', 0);
	}
	?>
		<div class="rightmenu">
			<p><?php echo $lang['page']['items']; ?></p>
			<?php read_imagesinpages('images'); ?>
		</div>
		<form method="post" action="">
			<p>
				<label class="kop2" for="cont1"><?php echo $lang['general']['title']; ?></label>
				<br />
				<input name="cont1" id="cont1" type="text" />
			</p>
			<p>
			<label class="kop2" for="cont2"><?php echo $lang['blog']['category']; ?></label>
			<br />
			<select name="cont2" id="cont2">
				<option value=""><?php echo $lang['blog']['choose_cat']; ?></option>
				<?php
				//If there are categories.
				if (blog_get_categories()) {
					$categories = blog_get_categories();

					foreach($categories as $category)
						echo '<option value="'.$category['seoname'].'">'.$category['title'].'</option>';
					unset($key);
				}
				?>
			</select>
			</p>
			<p>
				<label class="kop2" for="cont3"><?php echo $lang['general']['contents']; ?></label>
				<br />
				<textarea class="tinymce" name="cont3" id="cont3" cols="70" rows="20"></textarea>
			</p>
			<?php show_common_submits('?module=blog', true); ?>
		</form>
	<?php
}
?>