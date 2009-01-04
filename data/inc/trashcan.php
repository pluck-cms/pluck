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
	exit();
}
?>
	<p>
		<strong><?php echo $lang_trash5; ?></strong>
	</p>
<?php
//Define how much items we have in the trashcan
$trashcan_items = count_trashcan();

//Define which image we have to use: a full trashcan or an empty one
if ($trashcan_items == '0')
	$trash_image = 'trash-big.png';
else
	$trash_image = 'trash-full-big.png';
?>
<div class="menudiv">
	<span>
		<img src="data/image/<?php echo $trash_image; ?>" alt="" />
	</span>
	<span>
		<?php echo $trashcan_items.' '.$lang_trash3; ?>
		<br />
		<a href="?action=trashcan_empty" onclick="return confirm('<?php echo $lang_trash11; ?>');"><?php echo $lang_trash6; ?></a>
	</span>
</div>
<span class="kop2"><?php echo $lang_kop2; ?></span>
<br />
<?php
//Read pages in array
$pages = read_dir_contents('data/trash/pages', 'files');
if ($pages == false) {
?>
	<span class="kop4"><?php echo $lang_albums14; ?></span>
<?php
}
else {
	foreach ($pages as $page) {
		include_once ('data/trash/pages/'.$page);
		?>
		<div class="menudiv">
			<span>
				<img src="data/image/page.png" alt="" />
			</span>
			<span style="width: 350px;">
				<span style="font-size: 17pt;"><?php echo $title; ?></span>
			</span>
			<span>
				<a href="?action=trash_viewitem&amp;var=<?php echo $page; ?>&amp;cat=page">
					<img src="data/image/view.png" alt="<?php echo $lang_trash7; ?>" title="<?php echo $lang_trash7; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=trash_restoreitem&amp;var=<?php echo $page; ?>&amp;cat=page">
					<img src="data/image/restore.png" title="<?php echo $lang_trash10; ?>" alt="<?php echo $lang_trash10; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=trash_deleteitem&amp;var=<?php echo $page; ?>&amp;cat=page">
					<img src="data/image/delete_from_trash.png" title="<?php echo $lang_trash8; ?>" alt="<?php echo $lang_trash8; ?>" />
				</a>
			</span>
		</div>
	<?php
	}
}
?>
<br />
<span class="kop2"><?php echo $lang_trash9; ?></span>
<br />
<?php
//Read images in array
$images = read_dir_contents('data/trash/images', 'files');
if ($images == false) {
?>
	<span class="kop4"><?php echo $lang_albums14; ?></span>
<?php
}
else {
	foreach ($images as $image) {
	?>
	<div class="menudiv">
		<span>
			<img src="data/image/image.png" alt="" />
		</span>
		<span style="width: 350px;">
			<span style="font-size: 17pt;"><?php echo $image; ?></span>
		</span>
		<span>
			<a href="?action=trash_viewitem&amp;var=<?php echo $image; ?>&amp;cat=image">
				<img src="data/image/view.png" alt="<?php echo $lang_trash7; ?>" title="<?php echo $lang_trash7; ?>" />
			</a>
		</span>
		<span>
			<a href="?action=trash_restoreitem&amp;var=<?php echo $image; ?>&amp;cat=image">
				<img src="data/image/restore.png" title="<?php echo $lang_trash10; ?>" alt="<?php echo $lang_trash10; ?>" />
			</a>
		</span>
		<span>
			<a href="?action=trash_deleteitem&amp;var=<?php echo $image; ?>&amp;cat=image">
				<img src="data/image/delete_from_trash.png" title="<?php echo $lang_trash8; ?>" alt="<?php echo $lang_trash8; ?>" />
			</a>
		</span>
	</div>
	<?php
	}
}
?>