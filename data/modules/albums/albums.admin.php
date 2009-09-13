<?php
require_once 'data/modules/albums/functions.php';

require_once 'data/inc/lib/SmartImage.class.php';

function albums_page_admin_list() {
	global $lang_albums, $lang_albums5, $lang_albums6, $lang_albums15, $lang_kop13, $lang_updown5, $var1, $var2;

	if (isset($var1))
		include (MODULE_SETTINGS.'/'.$var1.'.php');
	else
		$album_name = '';

	if (isset($var2))
		include (MODULE_SETTINGS.'/'.$var1.'/'.albums_get_php_filename($var1, $var2));
	else
		$name = '';

	$module_page_admin[] = array(
		'func'  => 'albums',
		'title' => $lang_albums
	);
	$module_page_admin[] = array(
		'func'  => 'editalbum',
		'title' => $lang_albums6.' - '.$album_name
	);
	$module_page_admin[] = array(
		'func'  => 'deletealbum',
		'title' => $lang_albums5
	);
	$module_page_admin[] = array(
		'func'  => 'editimage',
		'title' => $lang_albums15.' - '.$album_name.' - '.$name
	);
	$module_page_admin[] = array(
		'func'  => 'deleteimage',
		'title' => $lang_kop13
	);
	$module_page_admin[] = array(
		'func'  => 'imageup',
		'title' => $lang_updown5
	);
	$module_page_admin[] = array(
		'func'  => 'imagedown',
		'title' => $lang_updown5
	);
	return $module_page_admin;
}

function albums_page_admin_albums() {
	global $cont1, $lang, $lang_albums1, $lang_albums2, $lang_albums3, $lang_albums4, $lang_albums19, $lang_albums16;
	?>
		<p>
			<strong><?php echo $lang_albums1; ?></strong>
		</p>
		<span class="kop2"><?php echo $lang_albums2; ?></span>
		<br />
		<?php
		read_albums(MODULE_SETTINGS);
		?>
			<br /><br />
			<label class="kop2" for="cont1"><?php echo $lang_albums3; ?></label>
			<br />
			<form method="post" action="">
				<span class="kop4"><?php echo $lang_albums4; ?></span>
				<br />
				<input name="cont1" id="cont1" type="text" />
				<input type="submit" name="submit" value="<?php echo $lang['general']['save']; ?>" />
			</form>
		<?php
		//When form is submitted.
		if (isset($_POST['submit'])) {
			if (!empty($cont1) && file_exists(MODULE_SETTINGS.'/'.seo_url($cont1)))
				show_error($lang_albums19, 1);

			elseif (!empty($cont1)) {
				//The pretty album name.
				$album_name = sanitize($cont1);

				//Make the album url safe to use.
				$cont1 = seo_url($cont1);

				//Create and chmod directories.
				mkdir(MODULE_SETTINGS.'/'.$cont1, 0777);
				mkdir(MODULE_SETTINGS.'/'.$cont1.'/thumb', 0777);

				//Create album file.
				save_file(MODULE_SETTINGS.'/'.$cont1.'.php','<?php $album_name = \''.$album_name.'\'; ?>');

				redirect('?module=albums', 0);
			}
		}
	?>
	<p>
		<a href="?action=modules">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
	</p>
<?php
}

function albums_page_admin_editalbum() {
	global $cont1, $cont2, $cont3, $lang, $lang_albums8, $lang_albums9, $lang_albums10, $lang_albums11, $lang_albums12, $lang_albums13, $lang_albums17, $var1;

	//Let's process the image...
	if (isset($_POST['submit'])) {
		//If file is jpeg, pjpeg, png or gif: Accept.
		if (in_array($_FILES['imagefile']['type'], array('image/jpeg', 'image/pjpeg', 'image/png', 'image/gif'))) {
			//Define some variables
			list($imageze, $ext) = explode('.', $_FILES['imagefile']['name']);
			$imageze = seo_url($imageze);
			$fullimage = MODULE_SETTINGS.'/'.$var1.'/'.$imageze.'.'.$ext;
			$thumbimage = MODULE_SETTINGS.'/'.$var1.'/thumb/'.$imageze.'.'.$ext;

			//Check if the image name already exists.
			$images = read_dir_contents(MODULE_SETTINGS.'/'.$var1.'/thumb', 'files');
			if ($images) {
				foreach ($images as $image) {
					$parts = explode('.', $image);
					if ($parts[0] == $imageze) {
						$name_exist = true;
						break;
					}
				}
			}

			//Don't do anything, if the name already exists.
			//TODO: Translate, and maybe make it better.
			if (isset($name_exist))
				$error = show_error('There is already an image with that name.', 1, true);
				
			//If we somehow can't copy the image, show an error.
			elseif (!copy($_FILES['imagefile']['tmp_name'], $fullimage) || !copy ($_FILES['imagefile']['tmp_name'], $thumbimage)) {
				$error = show_error($lang['general']['upload_failed'], 1, true);
			}

			else {
				//If the extension is with capitals, we have to rename it...
				if ($ext != strtolower($ext)) {
					$ext = strtolower($ext);
					rename($fullimage, MODULE_SETTINGS.'/'.$var1.'/'.$imageze.'.'.$ext);
					rename($thumbimage, MODULE_SETTINGS.'/'.$var1.'/thumb/'.$imageze.'.'.$ext);
					$fullimage = MODULE_SETTINGS.'/'.$var1.'/'.$imageze.'.'.$ext;
					$thumbimage = MODULE_SETTINGS.'/'.$var1.'/thumb/'.$imageze.'.'.$ext;
				}

				//Compress the big image.
				$image_width = 640;
				$image = new SmartImage($fullimage);
				list($width, $height) = getimagesize($fullimage);
				$imgratio = $width / $height;

				if ($imgratio > 1) {
					$newwidth = $image_width;
					$newheight = $image_width / $imgratio;
				}

				else {
					$newheight = $image_width;
					$newwidth = $image_width * $imgratio;
				}

				$image->resize($newwidth, $newheight);
				$image->saveImage($fullimage, $cont3);
				$image->close();
				chmod($fullimage, 0777);

				//Then make a thumb from the image.
				$thumb_width = 200;
				$thumb = new SmartImage($thumbimage);
				list($width, $height) = getimagesize($thumbimage);
				$imgratio = $width / $height;

				if ($imgratio > 1) {
					$newwidth = $thumb_width;
					$newheight = $thumb_width / $imgratio;
				}

				else {
					$newheight = $thumb_width;
					$newwidth = $thumb_width * $imgratio;
				}

				$thumb->resize($newwidth, $newheight);
				$thumb->saveImage($thumbimage, $cont3);
				$thumb->close();
				chmod($thumbimage, 0777);

				//Find the number.
				$images = read_dir_contents(MODULE_SETTINGS.'/'.$var1.'/thumb', 'files');

				if ($images)
					$number = count($images);
				else
					$number = 1;

				//Sanitize data.
				$cont1 = sanitize($cont1);
				$cont2 = sanitize($cont2);
				$cont2 = str_replace ("\n",'<br />', $cont2);

				//Compose the data.
				$data = '<?php'."\n"
				.'$name = \''.$cont1.'\';'."\n"
				.'$info = \''.$cont2.'\';'."\n"
				.'?>';

				//Then save the image information.
				save_file(MODULE_SETTINGS.'/'.$var1.'/'.$number.'.'.$imageze.'.'.$ext.'.php', $data);
			}
		}

		//Block unknown image types.
		else {
			//FIXME: Maybe a better error message?
			$error = show_error($lang['general']['upload_failed'], 1, true);
		}
	}
	
	//Check if album exists
	if (file_exists(MODULE_SETTINGS.'/'.$var1)) {
		//Introduction text.
		?>
			<p>
				<strong><?php echo $lang_albums8; ?></strong>
			</p>
			<p>
				<span class="kop2"><?php echo $lang_albums10; ?></span>
				<br />
				<span class="kop4"><?php echo $lang_albums13; ?></span>
			</p>
			<?php
			if (isset($error))
				echo $error;
			?>
			<form method="post" action="" enctype="multipart/form-data">
				<p>
					<label class="kop2" for="cont1"><?php echo $lang['general']['title']; ?></label>
					<br />
					<input name="cont1" id="cont1" type="text" />
				</p>
				<p>
					<label class="kop2" for="cont2"><?php echo $lang_albums11; ?></label>
					<br />
					<textarea cols="50" rows="5" name="cont2" id="cont2"></textarea>
				</p>
				<p>
					<input type="file" name="imagefile" id="imagefile" />
					<br />
					<label class="kop4" for="cont3"><?php echo $lang_albums12; ?></label>
					<input name="cont3" id="cont3" type="text" size="3" value="85" />
				</p>
				<input type="submit" name="submit" value="<?php echo $lang['general']['save']; ?>" />
			</form>
			<br />
		<?php
		//Edit images
		?>
		<span class="kop2"><?php echo $lang_albums9; ?></span>
		<br />
		<?php
		read_albumimages(MODULE_SETTINGS.'/'.$var1);
	}
	?>
		<br />
		<p>
			<a href="?module=albums">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
		</p>
	<?php
}

function albums_page_admin_deletealbum() {
	global $var1;

	//Check if an album was defined, and if the album exists
	if (isset($var1) && file_exists(MODULE_SETTINGS.'/'.$var1)) {
		recursive_remove_directory(MODULE_SETTINGS.'/'.$var1);
		unlink(MODULE_SETTINGS.'/'.$var1.'.php');
	}

	redirect('?module=albums', 0);
}

function albums_page_admin_editimage() {
	global $cont1, $cont2, $lang, $lang_albums11, $var1, $var2;

	//Check if an image was defined, and if the image exists.
	if (isset($var2) && file_exists('data/settings/modules/albums/'.$var1.'/'.albums_get_php_filename($var1, $var2))) {
		//Include the image-information.
		include ('data/settings/modules/albums/'.$var1.'/'.albums_get_php_filename($var1, $var2));

		//Replace html-breaks by real ones.
		$info = str_replace('<br />', "\n", $info);
		?>
		<br />
		<form name="form1" method="post" action="">
			<p>
				<label class="kop2" for="cont1"><?php echo $lang['general']['title']; ?></label>
				<br />
				<input name="cont1" id="cont1" type="text" value="<?php echo $name; ?>" />
			</p>
			<p>
				<label class="kop2" for="cont2"><?php echo $lang_albums11; ?></label>
				<br />
				<textarea cols="50" rows="5" name="cont2" id="cont2"><?php echo $info; ?></textarea>
			</p>
			<?php show_common_submits('?module=albums&amp;page=editalbum&amp;var1='.$var1); ?>
		</form>
		<?php
		//When the information is posted:
		if (isset($_POST['save'])) {
			//Sanitize data.
			$cont1 = sanitize($cont1);
			$cont2 = sanitize($cont2);
			$cont2 = nl2br($cont2);

			//Then save the imageinformation
			$data = '<?php'."\n"
			.'$name = \''.$cont1.'\';'."\n"
			.'$info = \''.$cont2.'\';'."\n"
			.'?>';

			save_file(MODULE_SETTINGS.'/'.$var1.'/'.albums_get_php_filename($var1, $var2), $data);

			redirect('?module=albums&page=editalbum&var1='.$var1, 0);
		}
	}
}

function albums_page_admin_deleteimage() {
	global $var1, $var2;

	//Check if an image was defined, and if the image exists.
	if (isset($var1, $var2) && file_exists('data/settings/modules/albums/'.$var1.'/'.albums_get_php_filename($var1, $var2))) {
		//Find the extension of the image before we delete anything.
		$parts =  explode('.', albums_get_php_filename($var1, $var2));

		//First, delete the php file.
		unlink('data/settings/modules/albums/'.$var1.'/'.albums_get_php_filename($var1, $var2));

		//And then delete the image and the thumb.
		unlink('data/settings/modules/albums/'.$var1.'/'.$parts[1].'.'.$parts[2]);
		unlink('data/settings/modules/albums/'.$var1.'/thumb/'.$parts[1].'.'.$parts[2]);

		//Finally, reorder the images.
		albums_reorder_images($var1);
	}

	redirect('?module=albums&page=editalbum&var1='.$var1, 0);
}

function albums_page_admin_imageup() {
global $lang, $lang_updown6, $var1, $var2;

//Check if images exist.
if (isset($var1, $var2) && file_exists('data/settings/modules/albums/'.$var1.'/'.albums_get_php_filename($var1, $var2))) {
	$current_parts =  explode('.', albums_get_php_filename($var1, $var2));

	//We can't higher the first image, so we have to check.
	if ($current_parts[0] == 1) {
		show_error($lang_updown6, 2);
		redirect('?module=albums&page=editalbum&var1='.$var1, 2);
		include_once ('data/inc/footer.php');
		exit;
	}

	$files = read_dir_contents(MODULE_SETTINGS.'/'.$var1, 'files');

	//Now we need to find the name of the other image, so we can switch numbers.
	foreach ($files as $file) {
		$file_parts = explode('.', $file);
		if ($current_parts[0] - 1 == $file_parts[0]) {
		$prior_parts = $file_parts;
		break;
		}
	}
	unset($file);

	//Switch the numbers.
	$current_image_new = $prior_parts[0].'.'.$current_parts[1].'.'.$current_parts[2].'.php';
	$prior_image_new = $current_parts[0].'.'.$prior_parts[1].'.'.$prior_parts[2].'.php';

	//And finaly, rename the files.
	rename(MODULE_SETTINGS.'/'.$var1.'/'.implode('.', $current_parts), MODULE_SETTINGS.'/'.$var1.'/'.$current_image_new);
	rename(MODULE_SETTINGS.'/'.$var1.'/'.implode('.', $prior_parts), MODULE_SETTINGS.'/'.$var1.'/'.$prior_image_new);

	//Show message.
	show_error($lang['general']['changing_rank'], 3);
}

//Redirect.
redirect('?module=albums&page=editalbum&var1='.$var1, 0);
}

function albums_page_admin_imagedown() {
	global $lang, $lang_updown7, $var1, $var2;

	//Check if images exist.
	if (isset($var1, $var2) && file_exists('data/settings/modules/albums/'.$var1.'/'.albums_get_php_filename($var1, $var2))) {
		$current_parts =  explode('.', albums_get_php_filename($var1, $var2));

		$files = read_dir_contents(MODULE_SETTINGS.'/'.$var1, 'files');
		$number_of_files = 0;

		//Count the number of PHP files.
		foreach ($files as $file) {
		    $file_parts = explode('.', $file);
		    if (isset($file_parts[3]))
			$number_of_files++;
		}

		//We can't lower the last image, so we have to check.
		if ($number_of_files == $current_parts[0]) {
			show_error($lang_updown7, 2);
			redirect('?module=albums&page=editalbum&var1='.$var1, 2);
			include_once ('data/inc/footer.php');
			exit;
		}

		//Now we need to find the name of the other image, so we can switch numbers.
		foreach ($files as $file) {
		    $file_parts = explode('.', $file);
		    if ($current_parts[0] + 1 == $file_parts[0]) {
			$next_parts = $file_parts;
			break;
		    }
		}
		unset($file);

		//Switch the numbers.
		$current_image_new = $next_parts[0].'.'.$current_parts[1].'.'.$current_parts[2].'.php';
		$next_image_new = $current_parts[0].'.'.$next_parts[1].'.'.$next_parts[2].'.php';

		//And finaly, rename the files.
		rename(MODULE_SETTINGS.'/'.$var1.'/'.implode('.', $current_parts), MODULE_SETTINGS.'/'.$var1.'/'.$current_image_new);
		rename(MODULE_SETTINGS.'/'.$var1.'/'.implode('.', $next_parts), MODULE_SETTINGS.'/'.$var1.'/'.$next_image_new);

		//Show message.
		show_error($lang['general']['changing_rank'], 3);
	}

	//Redirect.
	redirect('?module=albums&page=editalbum&var1='.$var1, 0);
}
?>