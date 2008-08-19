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

//Include functions
include("data/modules/blog/functions.php");

//Include the postinformation
if(file_exists('data/settings/modules/blog/posts/'.$var)) {
	include('data/settings/modules/blog/posts/'.$var);
}
else {
	exit;
}
?>

<div class="rightmenu">
<?php echo $lang_page8; ?><br />
<?php
//Generate the menu on the right
read_imagesinpages("images");
?>
</div>

<form method="post" action="">
	<span class="kop2"><?php echo $lang_install17; ?></span><br>
	<input name="cont1" type="text" value="<?php echo $post_title; ?>"><br><br>

	<span class="kop2"><?php echo $lang_blog26; ?></span><br>
	<select name="cont2">
		<option value="<?php echo $lang_blog27; ?>" /> <?php echo $lang_blog25; ?>
<?php
//If there are categories
if(file_exists("data/settings/modules/blog/categories.dat")) {
	//Load them
	$categories = file_get_contents("data/settings/modules/blog/categories.dat");
	
	//Then in an array
	$categories = split(',',$categories);
	
	//And show them
	//start table first
	foreach($categories as $key => $name) {
		if($post_category == $name) {
			echo '<option value="'.$name.'" selected />'.$name;
		}
		else {
			echo '<option value="'.$name.'" />'.$name;
		} 
	}
}
?>
	</select><br /><br />

	<span class="kop2"><?php echo $lang_install18; ?></span><br />
	<textarea class="tinymce" name="cont3" cols="70" rows="20"><?php echo $post_content; ?></textarea><br>

	<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>">
	<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?module=blog';">
</form>

<?php
//If form is posted...
if(isset($_POST['Submit'])) {

	//Strip slashes
	include("data/inc/page_stripslashes.php");

	//Save information
	$file = fopen('data/settings/modules/blog/posts/'.$var, 'w');
	fputs($file, '<?php'."\n"
	.'$post_title = "'.$cont1.'";'."\n"
	.'$post_category = "'.$cont2.'";'."\n"
	.'$post_content = "'.$cont3.'";'."\n"
	.'$post_day = "'.$post_day.'";'."\n"
	.'$post_month = "'.$post_month.'";'."\n"
	.'$post_year = "'.$post_year.'";'."\n"
	.'$post_time = "'.$post_time.'";'."\n"
	.'?>');
	fclose($file);
	chmod('data/settings/modules/blog/posts/'.$var,0777);

	//Redirect user
	redirect("?module=blog","0");
}
?>