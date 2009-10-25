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

//Introduction text.
?>
<p>
	<strong><?php echo $lang['images']['message']; ?></strong>
</p>
<div class="menudiv">
	<span>
		<img src="data/image/image.png" alt="" />
	</span>
	<div style="display: inline-block;">
		<form name="form1" method="post" action="" enctype="multipart/form-data">
			<input type="file" name="imagefile" />
			<input type="submit" name="submit" value="<?php echo $lang['general']['upload']; ?>" />
		</form>
	</div>
</div>
<?php
if (isset($_POST['submit'])) {
	//Check if the file is JPG, PNG or GIF.
	if (in_array($_FILES['imagefile']['type'], array('image/pjpeg', 'image/jpeg','image/png', 'image/gif'))) {
		if (!copy($_FILES['imagefile']['tmp_name'], 'images/'.$_FILES['imagefile']['name']))
			show_error($lang['general']['upload_failed'], 1);

		else {
			chmod('images/'.$_FILES['imagefile']['name'], 0666);
			?>
				<div class="menudiv">
					<strong><?php echo $lang['images']['name']; ?></strong> <?php echo $_FILES['imagefile']['name']; ?>
					<br />
					<strong><?php echo $lang['images']['size']; ?></strong> <?php echo $_FILES['imagefile']['size'].' '.$lang['images']['bytes']; ?>
					<br />
					<strong><?php echo $lang['images']['type']; ?></strong> <?php echo $_FILES['imagefile']['type']; ?>
					<br />
					<strong><?php echo $lang['images']['success']; ?></strong>
				</div>
			<?php
		}
	}
}

//Display list of uploaded pictures.
?>
<span class="kop2"><?php echo $lang['images']['uploaded']; ?></span>
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
				<span class="title-page"><?php echo $image; ?></span>
				<span>
					<a href="images/<?php echo $image; ?>" target="_blank">
						<img src="data/image/view.png" alt="" />
					</a>
				</span>
				<span>
					<a href="?action=deleteimage&amp;var1=<?php echo $image; ?>">
						<img src="data/image/delete.png" title="<?php echo $lang['trashcan']['move_to_trash']; ?>" alt="<?php echo $lang['trashcan']['move_to_trash']; ?>" />
					</a>
				</span>
			</div>
			<?php
		}
		unset($images);
	}
	else
		echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span>';
?>
<p>
	<a href="?action=page">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
</p>