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
	exit();
}

//First, load the functions
require_once ('data/modules/albums/functions.php');

//Check if album exists
if (file_exists('data/settings/modules/albums/'.$var1)) {
	//Introduction text.
	?>
		<p>
			<strong><?php echo $lang_albums8; ?></strong>
		</p>
	<?php
	//Edit images
	?>
		<span class="kop2"><?php echo $lang_albums9; ?></span>
		<br />
	<?php
	read_albumimages('data/settings/modules/albums/'.$var1);

	//New images upload
	?>
		<p>
			<span class="kop2"><?php echo $lang_albums10; ?></span>
			<br />
			<span class="kop4"><?php echo $lang_albums13; ?></span>
		</p>
		<form name="form1" method="post" action="" enctype="multipart/form-data">
			<label class="kop2" for="cont1"><?php echo $lang_install17; ?></label>
			<br />
			<input name="cont1" id="cont1" type="text" />
			<br /><br />
			<label class="kop2" for="cont2"><?php echo $lang_albums11; ?></label>
			<br />
			<textarea cols="50" rows="5" name="cont2" id="cont2"></textarea>
			<br /><br />
			<input type="file" name="imagefile" id="imagefile" />
			<br />
			<label class="kop4" for="quality"><?php echo $lang_albums12; ?></label>
			<input name="quality" id="quality" type="text" size="3" value="85" />
			<br /><br />
			<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
		</form>
	<?php
	//Let's process the image...
	if (isset($_POST['Submit'])) {
		//Define some variables
		$imageme = $_FILES['imagefile']['name'];
		list($imageze, $ext) = explode('.', $imageme);
		$fullimage = 'data/settings/modules/albums/'.$var1.'/'.$imageme;
		$thumbimage = 'data/settings/modules/albums/'.$var1.'/thumb/'.$imageme;
		$tempimage = 'data/settings/modules/albums/'.$var1.'/temp.jpg';

		//First: Upload the image.
		//If file is pjpeg or jpeg: Accept.
		if ($_FILES['imagefile']['type'] == 'image/jpeg' || $_FILES['imagefile']['type'] == 'image/pjpeg') {
			copy($_FILES['imagefile']['tmp_name'], 'data/settings/modules/albums/'.$var1.'/'.$_FILES['imagefile']['name']) or die ('Error: Upload failed!');

			//If the extension is with capitals, we have to rename it...
			if ($ext == 'JPG') {
				rename($fullimage, 'data/settings/modules/albums/'.$var1.'/'.$imageze.'.jpg');
				$fullimage = 'data/settings/modules/albums/'.$var1.'/'.$imageze.'.jpg';
				$thumbimage = 'data/settings/modules/albums/'.$var1.'/thumb/'.$imageze.'.jpg';
			}

			//Define which filenames are already in use, and define what filename we should use.
			if (file_exists('data/settings/modules/albums/'.$var1.'/image1.jpg')) {
				$i = 2;
				$o = 3;
				while (file_exists('data/settings/modules/albums/'.$var1.'/image'.$i.'.jpg') || file_exists('data/settings/modules/albums/'.$var1.'/image'.$o.'.jpg')) {
					$i++;
					$o++;
				}
				$newfile = 'image'.$i;
			}
			else
				$newfile = 'image1';

			//Then rename the file and give it the right filename, also define new image-variables.
			rename($fullimage, 'data/settings/modules/albums/'.$var1.'/'.$newfile.'.jpg');
			$fullimage = 'data/settings/modules/albums/'.$var1.'/'.$newfile.'.jpg';
			$thumbimage = 'data/settings/modules/albums/'.$var1.'/thumb/'.$newfile.'.jpg';

			//FIXME: Need to CHMOD the full image.
		}

		//Block images other then JPG.
		else {
			echo '<p><span class="red">'.$lang_image2.'</span></p>';
			include_once ('data/inc/footer.php');
			exit;
		}

		//Copy the image to the thumbdir.
		copy ($fullimage, $thumbimage) or die ('Error: Thumb copying failed!');

		//Compress the big image.
		$ThumbWidth = 640;
		list($width, $height) = getimagesize($fullimage);
		$imgratio = $width / $height;

		if ($imgratio > 1) {
			$newwidth = $ThumbWidth;
			$newheight = $ThumbWidth / $imgratio;
		}

		else {
			$newheight = $ThumbWidth;
			$newwidth = $ThumbWidth * $imgratio;
		}

		$resized_img = imagecreatetruecolor($newwidth, $newheight);
		$new_img = imagecreatefromjpeg($fullimage);

		imagecopyresampled($resized_img, $new_img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
		ImageJpeg($resized_img, $tempimage, $quality);
		ImageDestroy($resized_img);
		ImageDestroy($new_img);
		unlink($fullimage);
		rename($tempimage, $fullimage);

		//Then make a thumb from the image.
		$ThumbWidth = 200;
		list($width, $height) = getimagesize($thumbimage);
		$imgratio = $width / $height;

		if ($imgratio > 1) {
			$newwidth = $ThumbWidth;
			$newheight = $ThumbWidth / $imgratio;
		}

		else {
			$newheight = $ThumbWidth;
			$newwidth = $ThumbWidth * $imgratio;
		}

		$resized_img = imagecreatetruecolor($newwidth, $newheight);
		$new_img = imagecreatefromjpeg($thumbimage);

		imagecopyresampled($resized_img, $new_img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
		ImageJpeg($resized_img, $tempimage);
		ImageDestroy($resized_img);
		ImageDestroy($new_img);
		unlink($thumbimage);
		rename($tempimage, $thumbimage);
		chmod($thumbimage, 0777);

		//Sanitize data.
		$cont1 = sanitize($cont1);
		$cont2 = sanitize($cont2);
		$cont2 = str_replace ("\n",'<br />', $cont2);

		//Then save the imageinformation.
		$data = 'data/settings/modules/albums/'.$var1.'/'.$newfile.'.php';
		$file = fopen($data, 'w');
		fputs($file, '<?php'."\n"
		.'$name = \''.$cont1.'\';'."\n"
		.'$info = \''.$cont2.'\';'."\n"
		.'?>');
		fclose($file);
		chmod($data, 0777);

		//Redirect.
		redirect('?module=albums&page=editalbum&var1='.$var1, 0);
	}
}
?>
<p>
	<a href="?module=albums">&lt;&lt;&lt; <?php echo $lang_theme12; ?></a>
</p>