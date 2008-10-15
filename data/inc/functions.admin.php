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

//Function: read the available languages.
//-------------------
function read_lang_files($not_this_file) {
	$files = read_dir_contents('data/inc/lang','files');
	if($files) {
		natcasesort($files);
			foreach($files as $file) {
			if($file != $not_this_file) {
				include('data/inc/lang/'.$file);
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
	$files = read_dir_contents('data/settings/pages','files');
	if($files) {
		natcasesort($files);
		//Translation data.
		global $lang_page3, $lang_meta1, $lang_updown1, $lang_trash1;
		foreach ($files as $file) {
			include('data/settings/pages/'.$file);
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

//Function: display a menudiv.
//-------------------
function showmenudiv($title, $text, $image, $url, $blank, $more = null) {
?>
	<div class="menudiv">
		<span>
		<a href="<?php echo $url; ?>" <?php if ($blank == 'true') echo ' target="_blank"'; ?>><img src="<?php echo $image; ?>" alt="" /></a>
		</span>
		<span>
			<span>
				<a href="<?php echo $url; ?>" <?php if ($blank == 'true') echo 'target="_blank"'; ?>><?php echo $title; ?></a>
			</span>
			<?php if($more != null) { ?>
			<span class="more"><?php echo $more; ?></span>
			<?php } ?>
			<br />
			<?php if($text != null) echo $text; ?>
		</span>
	</div>
	<?php
}

function count_trashcan() {
	//Pages
	$count_pages_array = glob('data/trash/pages/*.*');
	if((isset($count_pages_array)) && (!empty($count_pages_array)))
		$count_pages = count($count_pages_array);

	//Images
	$count_images_array = glob('data/trash/images/*.*');
	if((isset($count_images_array)) && (!empty($count_images_array)))
		$count_images = count($count_images_array);

	//Combine all numbers...
	$trashcan_items = $count_pages + $count_images;
	return $trashcan_items;
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
	chmod($data,0777);
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

//Function: save a page.
//-------------------
function save_page($name, $title, $content, $hidden = false) {
	//Check if the file should be hidden.
	if ($hidden == true)
		$hidden = 'yes';
	else
		$hidden = 'no';

	$data = 'data/settings/pages/'.$name.'.php';
	$file = fopen($data, 'w');
	$title = stripslashes($title);
	$title = str_replace('"', '\"', $title);
	$cont = stripslashes($content);
	$cont = str_replace('"', '\"', $content);
	fputs($file, '<?php'."\n"
	.'$title = "'.$title.'";'."\n"
	.'$content = "'.$cont.'";'."\n"
	.'$hidden = "'.$hidden.'";'."\n"
	.'?>');
	fclose($file);
	chmod($data, 0777);
}
?>