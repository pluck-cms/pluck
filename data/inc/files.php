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
	<strong><?php echo $lang['files']['message']; ?></strong>
</p>
<?php run_hook('admin_images_before'); ?>
<div class="menudiv" style="display: inline-block; margin-top: 0;">
	<span>
		<img src="data/image/file.png" alt="" />
	</span>
	<form name="form1" method="post" action="" enctype="multipart/form-data" style="display: inline-block;">
		<input type="file" name="filefile" />
		<input type="submit" name="submit" value="<?php echo $lang['general']['upload']; ?>" />
	</form>
</div>
<?php
if (isset($_POST['submit'])) {
		if (!copy($_FILES['filefile']['tmp_name'], 'files/'.latinOnlyInput(latinOnlyInput($_FILES['filefile']['name']))))
			show_error($lang['general']['upload_failed'], 1);
		else {
			$filenamestr = strtolower(latinOnlyInput($_FILES['filefile']['name']));
			$lastfour = substr($filenamestr, -4);
			$lastfive = substr($filenamestr, -5);
			$blockedExtentions = array('.php','php3','php4','php5','php6','php7','phtml','.phtm','.pht','.ph3','.ph4','.ph5','.asp','.cgi');
			if (in_array($lastfour, $blockedExtentions) or in_array($lastfive, $blockedExtentions)  or (strpos($filenamestr, '.htaccess') > 0) ){
				if (!rename('files/'.latinOnlyInput($_FILES['filefile']['name']), 'files/'.latinOnlyInput($_FILES['filefile']['name']).'.txt')){
					show_error($lang['general']['upload_failed'], 1);
				}
				chmod('files/'.latinOnlyInput($_FILES['filefile']['name']).'.txt', 0775);
			}else{
				chmod('files/'.latinOnlyInput($_FILES['filefile']['name']), 0775);
			}
			?>
				<div class="menudiv">
					<strong><?php echo $lang['files']['name']; ?></strong> <?php echo latinOnlyInput($_FILES['filefile']['name']); ?>
					<br />
					<strong><?php echo $lang['files']['size']; ?></strong> <?php echo latinOnlyInput($_FILES['filefile']['size']).' '.$lang['images']['bytes']; ?>
					<br />
					<strong><?php echo $lang['files']['type']; ?></strong> <?php echo latinOnlyInput($_FILES['filefile']['type']); ?>
					<br />
					<strong><?php echo $lang['files']['success']; //TODO: Need to show this message another place, and with show_error(). ?></strong>
				</div>
			<?php
		}
}

//Display list of uploaded pictures.
?>
<span class="kop2"><?php echo $lang['files']['uploaded']; ?></span>
<?php
//Show the uploaded images
$files = read_dir_contents('files', 'files');
	if ($files) {
		natcasesort($files);
		foreach ($files as $file) {
			if (!($file == '.htaccess')){
		?>
			<div class="menudiv">
				<span>
					<img src="data/image/file.png" alt="" />
				</span>
				<span class="title-page"><?php echo $file; ?></span>
				<span>
					<a href="files/<?php echo $file; ?>" target="_blank">
						<img src="data/image/view.png" alt="" />
					</a>
				</span>
				<span>
					<a href="?action=deletefile&amp;var1=<?php echo $file; ?>">
						<img src="data/image/delete.png" title="<?php echo $lang['trashcan']['move_to_trash']; ?>" alt="<?php echo $lang['trashcan']['move_to_trash']; ?>" />
					</a>
				</span>
			</div>
			<?php
			}
		}
		unset($files);
	}
	else
		echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span>';
?>
<p style="margin-top: 10px;">
	<a href="?action=page">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
</p>