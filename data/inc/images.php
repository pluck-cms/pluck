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

//Introduction text
?>
<p>
	<strong><?php echo $lang_image1; ?></strong>
</p>
<div class="menudiv">
	<table>
		<tr>
			<td>
				<img src="data/image/image.png" alt="" />
			</td>
			<td>
				<span class="kop2"><?php echo $lang_image8; ?></span>
				<br />
				<form name="form1" method="post" action="" enctype="multipart/form-data">
					<input type="file" name="imagefile" />
					<input type="submit" name="submit" value="<?php echo $lang['general']['upload']; ?>" />
				</form>
			</td>
		</tr>
	</table>
</div>
<?php
if (isset($_POST ['submit'])) {
	//Check if the file is JPG, PNG or GIF
	if ($_FILES ['imagefile'] ['type'] == 'image/pjpeg' || $_FILES ['imagefile'] ['type'] == 'image/jpeg' || $_FILES ['imagefile'] ['type'] == 'image/png' || $_FILES ['imagefile'] ['type'] == 'image/gif') {

	//Strip spaces and % from the filename
	$filename = $_FILES ['imagefile'] ['name'];
	$filename = str_replace (' ', '', $filename);
	$filename = str_replace ('%', '', $filename);

	copy ($_FILES ['imagefile'] ['tmp_name'], 'images/'.$filename) or die ('<br />'.$lang['general']['upload_failed']);
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
$images = read_dir_contents('images', 'files');
	if ($images) {
		natcasesort($images);
		foreach ($images as $image) {
		?>
			<div class="menudiv">
				<span>
					<img src="data/image/image.png" alt="" />
				</span>
				<span style="width: 350px">
					<span style="font-size: 17pt;"><?php echo $image; ?></span>
				</span>
				<span>
					<a href="images/<?php echo $image; ?>" target="_blank"><img src="data/image/view.png" alt="" /></a>
				</span>
				<span>
					<a href="?action=deleteimage&amp;var1=<?php echo $image; ?>"><img src="data/image/delete.png" title="<?php echo $lang_trash1; ?>" alt="<?php echo $lang_trash1; ?>" /></a>
				</span>
			</div>
			<?php
		}
		unset($images);
	}
	elseif (!$images) {
	?>
		<span class="kop4"><?php echo $lang_albums14; ?></span>
	<?php
	}
?>
<br /><br />
<p>
	<a href="?action=page">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
</p>