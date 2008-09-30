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
if((!ereg('index.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo 'access denied';
    //Block all other code
    exit();
}
?>
<p><b><?php echo $lang_trash5; ?></b></p>
<?php
//Define how much items we have in the trashcan
$trashcan_items = count_trashcan();

//Define which image we have to use: a full trashcan or an empty one
if ($trashcan_items == '0')
	$trash_image = 'trash-big.png';
else
	$trash_image = 'trash-full-big.png';
?>

<div style="background-color: #f4f4f4; border: 1px dotted gray; margin: 20px; margin-bottom: 10px;">
	<table>
		<tr>
			<td>
				<img src="data/image/<?php echo $trash_image; ?>" border="0">
			</td>
			<td>
				<?php echo $trashcan_items.' '.$lang_trash3; ?><br>
				<a href="?action=trashcan_empty" onclick="return confirm('<?php echo $lang_trash11; ?>');"><?php echo $lang_trash6; ?></a>
			</td>
		</tr>
	</table>
</div>

<span class="kop2"><?php echo $lang_kop2; ?></span><br>
<?php
//Read pages in array
$pages = read_dir_contents('data/trash/pages','files');
if(!isset($pages)) { ?>
	<span class="kop4"><?php echo $lang_albums14; ?></span>
<?php }
else {
	foreach ($pages as $page) { 
		include('data/trash/pages/'.$page); ?>

<div class="menudiv" style="margin: 20px;">
	<table>
		<tr>
			<td>
				<img src="data/image/page.png" alt="">
			</td>
			<td style="width: 350px;">
				<pan style="font-size: 17pt;"><?php echo $title; ?></span>
			</td>
			<td>
				<a href="?trash_viewitem=<?php echo $page; ?>&amp;cat=page"><img src="data/image/view.png" alt="<?php echo $lang_trash7; ?>" title="<?php echo $lang_trash7; ?>"></a>		
			</td>
			<td>
				<a href="?trash_restoreitem=<?php echo $page; ?>&amp;cat=page"><img src="data/image/restore.png" title="<?php echo $lang_trash10; ?>" alt="<?php echo $lang_trash10; ?>"></a>		
			</td>
			<td>
				<a href="?trash_deleteitem=<?php echo $page; ?>&amp;cat=page"><img src="data/image/delete_from_trash.png" title="<?php echo $lang_trash8; ?>" alt="<?php echo $lang_trash8; ?>"></a>
			</td>
		</tr>
	</table>
</div>

<?php
	}
}
?>
	<br /><br />
	<span class="kop2"><?php echo $lang_trash9; ?></span><br>
<?php
//Read images in array
$images = read_dir_contents('data/trash/images','files');
if(!isset($images)) { ?>
	<span class="kop4"><?php echo $lang_albums14; ?></span>
<?php }
else {
	foreach ($images as $image) { ?>

<div class="menudiv" style="margin: 20px;">
	<table>
		<tr>
			<td>
				<img src="data/image/image.png" alt="">
			</td>
			<td style="width: 350px;">
				<pan style="font-size: 17pt;"><?php echo $image; ?></span>
			</td>
			<td>
				<a href="?trash_viewitem=<?php echo $image; ?>&amp;cat=image"><img src="data/image/view.png" alt="<?php echo $lang_trash7; ?>" title="<?php echo $lang_trash7; ?>"></a>		
			</td>
			<td>
				<a href="?trash_restoreitem=<?php echo $image; ?>&amp;cat=image"><img src="data/image/restore.png" title="<?php echo $lang_trash10; ?>" alt="<?php echo $lang_trash10; ?>"></a>		
			</td>
			<td>
				<a href="?trash_deleteitem=<?php echo $image; ?>&amp;cat=image"><img src="data/image/delete_from_trash.png" title="<?php echo $lang_trash8; ?>" alt="<?php echo $lang_trash8; ?>"></a>
			</td>
		</tr>
	</table>
</div>

<?php
	}
}
?>