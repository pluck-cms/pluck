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

//Include functions.
require_once ('data/modules/blog/functions.php');
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
		</span>
	<?php
	}
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
	<input name="cont1" id="cont1" type="text" />
	<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
</form>

<?php
//When form is submitted.
if (isset($_POST['Submit'])) {
	if ($cont1) {
		//Filter category name from inappropriate characters.
		$cont1 = str_replace ("\"","", $cont1);
		$cont1 = str_replace ("'","", $cont1);
		$cont1 = str_replace (",","", $cont1);
		$cont1 = str_replace (".","", $cont1);
		$cont1 = str_replace ("/","", $cont1);
		$cont1 = str_replace ("\\","", $cont1);

		//Read out existing categories, if they exist.
		if (file_exists('data/settings/modules/blog/categories.dat'))
			$categories = file_get_contents('data/settings/modules/blog/categories.dat');

		//Make sure category doesn't already exist.
		//FIXME: Replace ereg with strpos.
		if (!ereg($cont1.',',$categories) || !ereg(','.$cont1,$categories) || !isset($categories)) {

			//If there are already existing categories...
			if (file_exists('data/settings/modules/blog/categories.dat')) {
				//Load existing categories in array
				$categories = split(',',$categories);

				//Determine the array number for our new category.
				$num = 0;
				while (isset($categories[$num]))
					$num++;

				//Add new category to array.
				$categories[$num] = $cont1;
			}

			//If there are no categories yet, just set new category in array.
			else
				$categories[0] = $cont1;

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
			//Close file, and chmod it.
			fclose($file);
			chmod('data/settings/modules/blog/categories.dat', 0777);
		}
		//Redirect user.
		redirect('?module=blog', 0);
	}
}
?>
<p>
	<a href="?action=modules">&lt;&lt;&lt; <?php echo $lang_theme12; ?></a>
</p>