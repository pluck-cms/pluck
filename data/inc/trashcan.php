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
?>
	<p>
		<strong><?php echo $lang['trashcan']['message']; ?></strong>
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
			<?php echo $trashcan_items.' '.$lang['trashcan']['items_in_trash']; ?>
			<br />
			<a href="?action=trashcan_empty" onclick="return confirm('<?php echo $lang['trashcan']['empty_confirm']; ?>');"><?php echo $lang['trashcan']['empty']; ?></a>
		</span>
	</div>
	<span class="kop2"><?php echo $lang['page']['title']; ?></span>
<?php
//Read pages in array
$pages = read_dir_contents('data/trash/pages', 'files');

if ($pages == false)
	echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span>';

else {
	natsort($pages);
	foreach ($pages as $page) {
		include_once ('data/trash/pages/'.$page);
		$page = str_replace('.php', '', $page);
		?>
			<div class="menudiv">
				<span>
					<img src="data/image/page.png" alt="" />
				</span>
				<span style="width: 350px;">
					<span style="font-size: 17pt;"><?php echo $title; ?></span>
				</span>
				<span>
					<a href="?action=trash_viewitem&amp;var1=<?php echo $page; ?>&amp;var2=page">
						<img src="data/image/view.png" alt="<?php echo $lang['trashcan']['view_item']; ?>" title="<?php echo $lang['trashcan']['view_item']; ?>" />
					</a>
				</span>
				<span>
					<a href="?action=trash_restoreitem&amp;var1=<?php echo $page; ?>&amp;var2=page">
						<img src="data/image/restore.png" title="<?php echo $lang['trashcan']['restore_item']; ?>" alt="<?php echo $lang['trashcan']['restore_item']; ?>" />
					</a>
				</span>
				<span>
					<a href="?action=trash_deleteitem&amp;var1=<?php echo $page; ?>&amp;var2=page">
						<img src="data/image/delete_from_trash.png" title="<?php echo $lang['trashcan']['delete_item']; ?>" alt="<?php echo $lang['trashcan']['delete_item']; ?>" />
					</a>
				</span>
			</div>
		<?php
	}
	unset($page);
}
?>
	<br /><br />
	<span class="kop2"><?php echo $lang['general']['images']; ?></span>
<?php
//Read images in array
$images = read_dir_contents('data/trash/images', 'files');

if ($images == false)
	echo '<span class="kop4">'.$lang['general']['nothing_yet'].'</span>';

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
				<a href="?action=trash_viewitem&amp;var1=<?php echo $image; ?>&amp;var2=image">
					<img src="data/image/view.png" alt="<?php echo $lang['trashcan']['view_item']; ?>" title="<?php echo $lang['trashcan']['view_item']; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=trash_restoreitem&amp;var1=<?php echo $image; ?>&amp;var2=image">
					<img src="data/image/restore.png" title="<?php echo $lang['trashcan']['restore_item']; ?>" alt="<?php echo $lang['trashcan']['restore_item']; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=trash_deleteitem&amp;var1=<?php echo $image; ?>&amp;var2=image">
					<img src="data/image/delete_from_trash.png" title="<?php echo $lang['trashcan']['delete_item']; ?>" alt="<?php echo $lang['trashcan']['delete_item']; ?>" />
				</a>
			</span>
		</div>
	<?php
	}
	unset($image);
}
?>