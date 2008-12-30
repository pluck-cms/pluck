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
	exit();
}
?>
<p><b><?php echo $lang_blog20; ?></b></p>
<?php
//Set variable
if (isset($_GET['var']))
	$var = $_GET['var'];
//Include blog post, if it exists
if (file_exists('data/settings/modules/blog/posts/'.$var)) {
	include_once('data/settings/modules/blog/posts/'.$var);

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
					<b><?php echo $lang_install17; ?></b><br />
			  		<input name="title" type="text" value="<?php echo $post_reaction_title[$key]; ?>" /><br /><br />

					<textarea name="message" rows="5" cols="65"><?php echo $post_reaction_content[$key]; ?></textarea><br /><br />

					<input name="edit_key" type="hidden" value="<?php echo $key; ?>" />
					<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
				</form>
			</td>
			<td>
				<a href="?module=blog&page=deletereactions&post=<?php echo $var; ?>&key=<?php echo $key; ?>">
					<img src="data/image/delete_from_trash.png" border="0" title="<?php echo $lang_blog21; ?>" alt="<?php echo $lang_blog21; ?>" />
				</a>
			</td>
		</tr>
	</table>
</div>

<?php
		}
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
		$name = sanitize($post_reaction_name[$reaction_key]);
		$message = sanitize($_POST['message']);

		//Strip slashes from post itself too
		$post_title = sanitize($post_title);
		$post_category = sanitize($post_category);
		$post_content = sanitize($post_content, false);

		//Then, save existing post information
		$file = fopen('data/settings/modules/blog/posts/'.$var, 'w');
		fputs($file, '<?php'."\n"
		.'$post_title = \''.$post_title.'\';'."\n"
		.'$post_category = \''.$post_category.'\';'."\n"
		.'$post_content = \''.$post_content.'\';'."\n"
		.'$post_day = \''.$post_day.'\';'."\n"
		.'$post_month = \''.$post_month.'\';'."\n"
		.'$post_year = \''.$post_year.'\';'."\n"
		.'$post_time = \''.$post_time.'\';'."\n");

		//Check if there already are other reactions
		if(isset($post_reaction_title)) {
			foreach($post_reaction_title as $reaction_key => $value) {
				//If it's the post we want to edit
				if($reaction_key == $edit_key) {
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
		}
		fputs($file, '?>');
		fclose($file);
		chmod('data/settings/modules/blog/posts/'.$var, 0777);
		redirect('?module=blog&page=editreactions&var='.$var, 0);
	}
}
?>

<p>
	<a href="?module=blog"><<< <?php echo $lang_theme12; ?></a>
</p>