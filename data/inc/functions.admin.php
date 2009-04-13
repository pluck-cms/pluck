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
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'update.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit;
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
					<option value='<?php echo $file; ?>'><?php echo $language; ?></option>
				<?php
			}
		}
		unset($file);
	}
}

//Function: read out the images to let them include in pages
//------------
function read_imagesinpages($dir) {
	global $lang;
	$files = read_dir_contents($dir, 'files');
	if ($files) {
		natcasesort($files);
		foreach ($files as $file) {
		?>
			<div class="menudiv">
				<span>
					<img src="data/image/image_small.png" alt="" />
				</span>
				<span>
					<span>
						<a  style="font-size: 16px !important;" href="images/<?php echo $file; ?>" target="_blank"><?php echo $file; ?></a>
					</span>
					<br />
					<a style="font-size: 14px;" href="#" onclick="tinyMCE.execCommand('mceInsertContent',false,'&lt;img src=\'images/<?php echo $file; ?>\' alt=\'\' />');return false;"><?php echo $lang['general']['insert']; ?></a>
				</span>
			</div>
		<?php
		}
		unset($file);
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
				$file = get_page_seoname($file);
				?>
					<div class="menudiv">
						<span>
							<img src="data/image/page_small.png" alt="" />
						</span>
						<span style="font-size: 14px;">
							<span style="font-size: 16px; color: gray;"><?php echo $title; ?></span>
							<br />
							<?php $escaped_title = str_replace('\'', '\\\'', $title); ?>
							<a href="#" onclick="tinyMCE.execCommand('mceInsertContent',false,'&lt;a href=\'index.php?file=<?php echo $file; ?>\' title=\'<?php echo $escaped_title ?>\'><?php echo $escaped_title ?>&lt;/a>');return false;"><?php echo $lang_page9; ?></a>
						</span>
					</div>
				<?php
			}
		}
		unset($file);
	}
}

function get_pages($patch = 'data/settings/pages') {
	global $pages;
	$files = read_dir_contents($patch, 'files');
	if ($files) {
		sort($files, SORT_NUMERIC);
		foreach ($files as $file) {
			$pages[] = $patch.'/'.$file;
			if (file_exists('data/settings/pages/'.get_page_seoname($patch.'/'.$file)))
				get_pages('data/settings/pages/'.get_page_seoname($patch.'/'.$file));
		}
		unset($file);
	}

	foreach ($pages as $key => $page)
		$pages[$key] = str_replace('data/settings/pages/', '', $page);

	return $pages;
}

function show_page_box($file) {
	global $lang, $lang_page3, $lang_meta1, $lang_updown1;

	include_once ('data/settings/pages/'.$file);
	$file = get_page_seoname($file);

	//Find the margin.
	preg_match_all('|\/|', $file, $margin);
	if (!empty($margin[0]))
		$margin = count($margin[0]) * 20 + 10;
	else
		$margin = 0;
	?>
		<div class="menudiv" <?php if ($margin != 0) echo 'style="margin-left: '.$margin.'px;"'; ?>>
			<span>
				<img src="data/image/page.png" alt="" />
			</span>
			<span class="title-page"><?php echo $title; ?></span>
			<?php run_hook('admin_page_list_before'); ?>
			<span>
				<a href="?action=editpage&amp;var1=<?php echo $file; ?>">
					<img src="data/image/edit.png" title="<?php echo $lang_page3; ?>" alt="<?php echo $lang_page3; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=editmeta&amp;var1=<?php echo $file; ?>">
					<img src="data/image/siteinformation.png" title="<?php echo $lang_meta1; ?>" alt="<?php echo $lang_meta1; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=pageup&amp;var1=<?php echo $file; ?>">
					<img src="data/image/up.png" title="<?php echo $lang_updown1; ?>" alt="<?php echo $lang_updown1; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=pagedown&amp;var1=<?php echo $file; ?>">
					<img src="data/image/down.png" title="<?php echo $lang_updown1; ?>" alt="<?php echo $lang_updown1; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=deletepage&amp;var1=<?php echo $file; ?>">
					<img src="data/image/delete.png" title="<?php echo $lang['trashcan']['move_to_trash']; ?>" alt="<?php echo $lang['trashcan']['move_to_trash']; ?>" />
				</a>
			</span>
			<?php run_hook('admin_page_list_after'); ?>
		</div>
	<?php
}

function show_subpage_select($name, $current_page = null) {
	$pages = get_pages();
	echo '<select name="'.$name.'" id="'.$name.'">';
	echo '<option value="none">None</option>';

	foreach ($pages as $page) {
		include ('data/settings/pages/'.$page);

		preg_match_all('|\/|', $page, $indent);
		$indent = count($indent[0]);

		if (!empty($indent))
			$indent = str_repeat('&emsp;', $indent);
		else
			$indent = null;

		echo '<option value="'.$page.'">'.$indent.$title.'</option>';
	}
	echo '</select>';
}

function reorder_pages($patch) {
	$pages = read_dir_contents($patch, 'files');
	sort($pages, SORT_NUMERIC);

	$number = 1;
	foreach ($pages as $page) {
		$parts = explode('.', $page);
		rename($patch.'/'.$page, $patch.'/'.$number.'.'.$parts[1].'.'.$parts[2]);
		$number++;
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
	if (isset($count_pages_array) && !empty($count_pages_array))
		$count_pages = count($count_pages_array);
	else
		$count_pages = null;

	//Images
	$count_images_array = glob('data/trash/images/*.*');
	if (isset($count_images_array) && !empty($count_images_array))
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
	global $lang;
	if (is_writable($file)) {
	?>
		<span>
			<img src="data/image/update-no.png" width="15" height="15" alt="<?php echo $lang['install']['good']; ?>" />
		</span>
		<span>&nbsp;/<?php echo $file; ?></span>
		<br />
	<?php
	}
	else {
	?>
		<span>
			<img src="data/image/error.png" width="15" height="15" alt="<?php echo $lang['install']['false']; ?>" />
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
	fputs($file, '<?php $ww = \''.$password.'\'; ?>');
	fclose($file);
	chmod($data, 0777);
}

//Function: save the options.
//-------------------
function save_options($title, $email, $xhtml) {
	$title = sanitize($title);
	$email = sanitize($email);
	$data = 'data/settings/options.php';
	$file = fopen($data, 'w');
	fputs($file, '<?php'."\n"
	.'$sitetitle = \''.$title.'\';'."\n"
	.'$email = \''.$email.'\';'."\n"
	.'$xhtmlruleset = \''.$xhtml.'\';'."\n"
	.'?>');
	fclose($file);
	chmod($data, 0777);
}

//Function: save the prefered language.
//-------------------
function save_language($language) {
	$data = 'data/settings/langpref.php';
	$file = fopen($data, 'w');
	fputs($file, '<?php $langpref = \''.$language.'\'; ?>');
	chmod($data, 0777);
	fclose($file);
}

//Function: save theme.
//-------------------
function save_theme($theme) {
	$data = 'data/settings/themepref.php';
	$file = fopen($data, 'w');
	fputs($file, '<?php $themepref = \''.$theme.'\'; ?>');
	chmod($data, 0777);
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
	//Run a few hooks.
	run_hook('admin_save_page', array(&$name, &$title, &$content));
	run_hook('admin_save_page_meta', array(&$description, &$keywords));

	//Sanitize the inputs.
	$title = sanitize($title);
	$content = sanitize($content, false);
	$description = sanitize($description);
	$keywords = sanitize($keywords);

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
	if ($description != null)
		fputs($file, "\n".'$description = \''.$description.'\';');
	if ($keywords != null)
		fputs($file, "\n".'$keywords = \''.$keywords.'\';');

	//Check if there are modules we want to save.
	if (is_array($modules)) {
		foreach ($modules as $modulename => $order) {
			//Only save it if we want to display the module.
			if ($order != 0)
				fputs($file, "\n".'$module_pageinc[\''.$modulename.'\'] = '.$order.';');
		}
		unset($modulename);
	}

	fputs($file, "\n".'?>');
	fclose($file);
	chmod($data, 0777);
}
?>