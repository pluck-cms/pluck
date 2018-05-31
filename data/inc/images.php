<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) pluck team
 * http://www.pluck-cms.org

 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');

//Introduction text.
?>
<p>
	<strong><?php echo $lang['images']['message']; ?></strong>
</p>
<?php run_hook('admin_images_before'); ?>
<div class="menudiv" style="display: inline-block; margin-top: 0;">
	<span>
		<img src="data/image/image.png" alt="" />
	</span>
	<form name="form1" method="post" action="" enctype="multipart/form-data" style="display: inline-block;">
		<input type="file" name="imagefile" />
		<input type="submit" name="submit" value="<?php echo $lang['general']['upload']; ?>" />
	</form>
</div>
<?php
if (isset($_POST['submit'])) {
	//Check if the file is JPG, PNG or GIF.
	if (in_array($_FILES['imagefile']['type'], array('image/pjpeg', 'image/jpeg','image/png', 'image/gif'))) {
		/* fix issue 44. Thanks to Klaus.  */
        $imagewhitelist = array('jfif', '.png', '.jpg', '.gif', 'jpeg');  
        if (!in_array(strtolower(substr($_FILES['imagefile']['name'], -4)), $imagewhitelist)){
			show_error($lang['general']['upload_failed'], 1);
			/* end of fix issue 44. Thanks to Klaus.  */
			if (!copy($_FILES['imagefile']['tmp_name'], 'images/'.latinOnlyInput($_FILES['imagefile']['name'])))
				show_error($lang['general']['upload_failed'], 1);
			else {
				chmod('images/'.$_FILES['imagefile']['name'], 0666);
				?>
					<div class="menudiv">
						<strong><?php echo $lang['images']['name']; ?></strong> <?php echo latinOnlyInput($_FILES['imagefile']['name']); ?>
						<br />
						<strong><?php echo $lang['images']['size']; ?></strong> <?php echo latinOnlyInput($_FILES['imagefile']['size']).' '.$lang['images']['bytes']; ?>
						<br />
						<strong><?php echo $lang['images']['type']; ?></strong> <?php echo latinOnlyInput($_FILES['imagefile']['type']); ?>
						<br />
						<strong><?php echo $lang['images']['success']; //TODO: Need to show this message another place, and with show_error(). ?></strong>
					</div>
				<?php
			}
		}
	}
}

//Display list of uploaded pictures.
?>
<span class="kop2"><?php echo $lang['images']['uploaded']; ?></span>
<?php
//Show the uploaded images
$images = read_dir_contents('images', 'files');
	if ($images) {
		natcasesort($images);
		foreach ($images as $image) {
			if (!($image == '.htaccess')){
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
		}
		unset($images);
	}
	else
		echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span>';
?>
<p style="margin-top: 10px;">
	<a href="?action=page">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
</p>