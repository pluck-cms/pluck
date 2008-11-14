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
if((!ereg('index.php', $_SERVER ['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER ['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER ['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER ['SCRIPT_FILENAME']))){
    //Give out an "access denied" error.
    echo 'access denied';
    //Block all other code.
    exit();
}

//Introduction text
?>
<p><strong><?php echo $lang_image1; ?></strong></p>

<div class="menudiv">
	<table>
		<tr>
			<td>
				<img src="data/image/image.png" alt="" />
			</td>
			<td>
				<span class="kop2"><?php echo $lang_image8; ?></span><br />
				<form name="form1" method="post" action="" enctype="multipart/form-data">
					<input type="file" name="imagefile" />
					<input type="submit" name="Submit" value="<?php echo $lang_image9; ?>" />
				</form>
			</td>
		</tr>
	</table>
</div>
<?php
if(isset($_POST ['Submit'])) {
	//Check if the file is JPG, PNG or GIF
	if (($_FILES ['imagefile'] ['type'] == 'image/pjpeg') || ($_FILES ['imagefile'] ['type'] == 'image/jpeg') || ($_FILES ['imagefile'] ['type'] == 'image/png') || ($_FILES ['imagefile'] ['type'] == 'image/gif')) {

	//Strip all the spaces from the filename
	$filename = $_FILES ['imagefile'] ['name'];
	$filename = str_replace (' ','', $filename);

	copy ($_FILES ['imagefile'] ['tmp_name'], 'images/'.$filename) or die ('<br />'.$lang_image2);
	chmod('images/'.$filename, 0666);
	?>
	<div class="menudiv">
		<strong><?php echo $lang_image3; ?></strong> <?php echo $filename; ?>
		<br />
		<strong><?php echo $lang_image4; ?></strong> <?php echo $_FILES ['imagefile'] ['size']; ?> bytes
		<br />
		<strong><?php echo $lang_image5; ?></strong> <?php echo $_FILES ['imagefile'] ['type']; ?>
		<br />
		<strong><?php echo $lang_image6; ?></strong>
	</div>
	<?php
	}
}

//Display list of uploaded pictures
?>
<span class="kop2"><?php echo $lang_image7; ?></span>
<br />
<?php
//Show the uploaded images
read_images('images');
?>
<p><a href="?action=page">&lt;&lt;&lt; <?php echo $lang_theme12; ?></a></p>