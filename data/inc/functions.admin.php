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
if((!ereg('index.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('update.php', $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error.
    echo 'access denied';
    //Block all other code.
    exit();
}

//Function: read the available languages.
//-------------------
function read_lang_files($not_this_file) {
	$files = read_dir_contents('data/inc/lang', 'files');
	if ($files) {
		natcasesort($files);
			foreach ($files as $file) {
			if ($file != $not_this_file) {
				include ('data/inc/lang/'.$file);
				?>
				<option value='<?php echo $file; ?>'><?php echo $lang; ?></option>
				<?php
			}
		}
	}
}

//Function: read out the pages.
//------------
function read_pages() {
	//Translation data.
	global $lang_page3, $lang_meta1, $lang_updown1, $lang_trash1;
	$files = read_dir_contents('data/settings/pages','files');
	if ($files) {
		natcasesort($files);
		foreach ($files as $file) {
			include ('data/settings/pages/'.$file);
			?>
			<div class="menudiv">
				<span>
					<img src="data/image/page.png" alt="" />
				</span>
				<span class="title-page"><?php echo $title; ?></span>
				<span>
					<a href="?editpage=<?php echo $file; ?>"><img src="data/image/edit.png" title="<?php echo $lang_page3; ?>" alt="<?php echo $lang_page3; ?>" /></a>
				</span>
				<span>
					<a href="?editmeta=<?php echo $file; ?>"><img src="data/image/siteinformation.png" title="<?php echo $lang_meta1; ?>" alt="<?php echo $lang_meta1; ?>" /></a>
				</span>
				<span>
					<a href="?pageup=<?php echo $file; ?>"><img src="data/image/up.png" title="<?php echo $lang_updown1; ?>" alt="<?php echo $lang_updown1; ?>" /></a>
				</span>
				<span>
					<a href="?pagedown=<?php echo $file; ?>"><img src="data/image/down.png" title="<?php echo $lang_updown1; ?>" alt="<?php echo $lang_updown1; ?>" /></a>
				</span>
				<span>
					<a href="?deletepage=<?php echo $file; ?>"><img src="data/image/delete.png" title="<?php echo $lang_trash1; ?>" alt="<?php echo $lang_trash1; ?>" /></a>
				</span>
			</div>
			<?php
		}
   }
}


//Function: readout the images
//------------
function read_images($dir) {
	global $lang_trash1;
	$files = read_dir_contents($dir, 'files');
	if (!$files)
		echo '<span class="kop4">'.$lang_albums14.'</span>';

	if ($files) {
		natcasesort($files);
		foreach ($files as $file) {
		?>
			<div class="menudiv">
				<span>
					<img src="data/image/image.png" alt="">
				</span>
				<span style="width: 350px">
					<span style="font-size: 17pt;"><?php echo $file; ?></span>
				</span>
				<span>
					<a href="images/<?php echo $file; ?>" target="_blank"><img src="data/image/view.png" alt="" /></a>
				</span>
				<span>
					<a href="?deleteimage=<?php echo $file; ?>"><img src="data/image/delete.png" title="<?php echo $lang_trash1; ?>" alt="<?php echo $lang_trash1; ?>" /></a>
				</span>
			</div>
		<?php
		}
	}
}

//Function: read out the images to let them include in pages
//------------
function read_imagesinpages($dir) {
	global $lang_page7;
	$files = read_dir_contents($dir, 'files');
	if ($files) {
		natcasesort($files);
		foreach ($files as $file) {
		?>
			<div class="menudiv" style="width: 200px;">
				<span>
					<img src="data/image/image_small.png" alt="" />
				</span>
				<span>
					<span><a  style="font-size: 16px !important;" href="images/<?php echo $file; ?>" target="_blank"><?php echo $file; ?></a></span>
					<br />
					<a style="font-size: 14px;" href="#" onclick="tinyMCE.execCommand('mceInsertContent',false,'&lt;img src=\'images/<?php echo $file; ?>\' alt=\'\' />');return false;"><?php echo $lang_page7; ?></a>
				</span>
			</div>
		<?php
		}
   }
}

//Function: read out the pages to let them be included in pages as link
//------------
function read_pagesinpages($dir, $current_page = null) {
	global $lang_page9;
	$files = read_dir_contents($dir, 'files');
	if ($files) {
		natcasesort($files);
		foreach ($files as $file) {
			if ($current_page != $file) {
				require 'data/settings/pages/'.$file;
				?>
					<div class="menudiv" style="width: 200px;">
						<span>
							<img src="data/image/page_small.png" alt="" />
						</span>
						<span style="font-size: 14px;">
							<span style="font-size: 16px; color: gray;"><?php echo $title; ?></span>
							<br />
							<?php $escaped_title = str_replace('&#039;', '\&#039;', $title); ?>
							<a href="#" onclick="tinyMCE.execCommand('mceInsertContent',false,'&lt;a href=\'index.php?file=<?php echo $file; ?>\' title=\'<?php echo $escaped_title ?>\'><?php echo $escaped_title ?>&lt;/a>');return false;"><?php echo $lang_page9; ?></a>
						</span>
					</div>
				<?php
			}
		}
	}
}

//Function: display a menudiv.
//-------------------
function showmenudiv($title, $text, $image, $url, $blank = false, $more = null) {
?>
	<div class="menudiv">
		<span>
			<a href="<?php echo $url; ?>" <?php if ($blank == true) echo ' target="_blank"'; ?>><img src="<?php echo $image; ?>" alt="" /></a>
		</span>
		<span>
			<span>
				<a href="<?php echo $url; ?>" <?php if ($blank == true) echo 'target="_blank"'; ?>><?php echo $title; ?></a>
			</span>
			<?php if($more != null): ?>
				<span class="more"><?php echo $more; ?></span>
			<?php endif; ?>
			<br />
			<?php if($text != null) echo $text; ?>
		</span>
	</div>
<?php
}

function count_trashcan() {
	//Pages
	$count_pages_array = glob('data/trash/pages/*.*');
	if ((isset($count_pages_array)) && (!empty($count_pages_array)))
		$count_pages = count($count_pages_array);
	else
		$count_pages = null;

	//Images
	$count_images_array = glob('data/trash/images/*.*');
	if ((isset($count_images_array)) && (!empty($count_images_array)))
		$count_images = count($count_images_array);
	else
		$count_images = null;

	//Combine all numbers...;
	return $count_pages + $count_images;
}

/*INSTALL FUNCTIONS*/

//Function: check if files are writable.
//-------------------
function check_writable($file) {
	//Translation data.
	global $lang_install8, $lang_install9;
	if (is_writable($file)) {
	?>
		<span>
			<img src="data/image/update-no.png" width="15" height="15" alt="<?php echo $lang_install8; ?>" />
		</span>
		<span>&nbsp;/<?php echo $file; ?></span>
		<br />
	<?php
	}
	else {
	?>
		<span>
			<img src="data/image/error.png" width="15" height="15" alt="<?php echo $lang_install9; ?>" />
		</span>
		<span>&nbsp;/<?php echo $file; ?></span>
		<br />
	<?php
	}
}

//Function: write the install file.
//-------------------
function install_done() {
	$data = 'data/settings/install.dat';
	$file = fopen($data, 'w');
	fputs($file, '');
	fclose($file);
	chmod($data, 0777);
}

/*SAVE FUNCTIONS*/

//Function: save the login password.
//-------------------
function save_password($password) {
	//MD5-hash password
	$password = md5($password);
	//Save password
	$data = 'data/settings/pass.php';
	$file = fopen($data, 'w');
	fputs($file, '<?php $ww = "'.$password.'"; ?>');
	fclose($file);
	chmod($data, 0777);
}

//Function: save the options.
//-------------------
function save_options($title, $email, $xhtml) {
	$title = stripslashes($title);
	$title = htmlspecialchars($title);
	$email = htmlspecialchars($email);
	$data = 'data/settings/options.php';
	$file = fopen($data, 'w');
	fputs($file, '<?php'."\n"
	.'$sitetitle = "'.$title.'";'."\n"
	.'$email = "'.$email.'";'."\n"
	.'$xhtmlruleset = "'.$xhtml.'";'."\n"
	.'?>');
	fclose($file);
	chmod($data, 0777);
}

//Function: save the prefered language.
//-------------------
function save_language($language) {
	$data = 'data/settings/langpref.php';
	$file = fopen($data, 'w');
	fputs($file, '<?php $langpref = "'.$language.'"; ?>');
	fclose($file);
}

//Function: save theme.
//-------------------
function save_theme($theme) {
	$data = 'data/settings/themepref.php';
	$file = fopen($data, 'w');
	fputs($file, '<?php $themepref = "'.$theme.'"; ?>');
	fclose($file);
}

/**
 * Save a page with a lot of options.
 *
 * @param string $name The filename of the page (without .php).
 * @param string $title The title.
 * @param string $content The content.
 * @param string $hidden Should it be hidden (yes or no)?
 * @param string $description The description.
 * @param string $keywords The keywords.
 * @param array $modules If there are any modules on the page.
 */
function save_page($name, $title, $content, $hidden = 'no', $description = null, $keywords = null, $modules = null) {
	//Sanitize the inputs.
	$title = sanitize($title, true);
	$content = sanitize($content);
	$description = sanitize($description, true);
	$keywords = sanitize($keywords, true);

	//Check hidden status.
	if ($hidden != 'no')
		$hidden = 'yes';

	//Open the file.
	$data = 'data/settings/pages/'.$name.'.php';
	$file = fopen($data, 'w');

	//Save the title, content and hidden status.
	fputs($file, '<?php'."\n"
	.'$title = \''.$title.'\';'."\n"
	.'$content = \''.$content.'\';'."\n"
	.'$hidden = \''.$hidden.'\';');

	//Save the description and keywords, if any.
	if (!empty($description) && $description != null)
		fputs($file, "\n".'$description = \''.$description.'\';');
	if (!empty($keywords) && $keywords != null)
		fputs($file, "\n".'$keywords = \''.$keywords.'\';');

	//Check if there are modules we want to save.
	if (is_array($modules)) {
		foreach ($modules as $modulename => $order) {
			//Only save it if we want to display the module.
			if ($order != 0)
				fputs($file, "\n".'$module_pageinc[\''.$modulename.'\'] = '.$order.';');
		}
	}

	fputs($file, "\n".'?>');
	fclose($file);
	chmod($data, 0777);
}
?>