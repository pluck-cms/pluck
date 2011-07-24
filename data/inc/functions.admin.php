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

//Function: show box for inserting images in pages
//------------
function show_image_insert_box($dir) {
	global $lang;

	$images = read_dir_contents($dir, 'files');
	if ($images) {
	?>
		<div class="menudiv">
			<span>
				<img src="data/image/image.png" alt="" />
			</span>
			<span>
				<select id="insert_images">
					<?php
					natcasesort($images);
					foreach ($images as $image) {
					?>
						<option><?php echo $image; ?></option>
					<?php
					}
					?>
				</select>
				<br />
				<a href="#" onclick="insert_image_link('<?php echo $dir; ?>');return false;"><?php echo $lang['general']['insert_image']; ?></a>
			</span>
		</div>
	<?php
	}
}


//Function: show box for inserting modules in pages
//------------
function show_module_insert_box() {
	global $lang, $module_list;

	//Load module info and site-functions.
	foreach ($module_list as $module) {
	if (file_exists('data/modules/'.$module.'/'.$module.'.site.php'))
		require_once ('data/modules/'.$module.'/'.$module.'.site.php');
	}
	unset($module);
	?>
	<div class="menudiv">
		<span>
			<img src="data/image/modules.png" alt="" />
		</span>
		<span>
			<select id="insert_modules">
				<?php
				foreach ($module_list as $module) {
					if (module_is_compatible($module) && function_exists($module.'_theme_main')) {
						echo '<option value="'.$module.'">'.$module.'</option>';
						//Check if we need to display categories for the module
						$module_info = call_user_func($module.'_info');
						if (isset($module_info['categories']) && is_array($module_info['categories'])) {
							foreach ($module_info['categories'] as $category)
								echo '<option value="'.$module.','.$category.'">&emsp;'.$category.'</option>';
						}
					}
				}
				?>
			</select>
			<br />
			<a href="#" onclick="insert_module();return false;"><?php echo $lang['general']['insert_module']; ?></a>
		</span>
	</div>
	<?php
}


//Function: show box for inserting links in pages
//------------
function show_link_insert_box() {
	global $lang;

	$files = get_pages();
	if ($files) {
	?>
		<div class="menudiv">
			<span>
				<img src="data/image/page.png" alt="" />
			</span>
			<span>
				<select id="insert_pages">
					<?php
					foreach ($files as $file) {
							require 'data/settings/pages/'.$file;
							$file = get_page_seoname($file);

							preg_match_all('|\/|', $file, $indent);
							$indent = count($indent[0]);

							if (!empty($indent))
								$indent = str_repeat('&emsp;', $indent);
							else
								$indent = null;
							?>
								<option value="<?php echo $file; ?>"><?php echo $indent.$title; ?></option>
							<?php
					}
					unset($file);
					?>
				</select>
				<br />
				<a href="#" onclick="insert_page_link(); return false;"><?php echo $lang['page']['insert_link']; ?></a>
			</span>
		</div>
	<?php
	}
}

function get_pages($patch = 'data/settings/pages', &$pages = null) {
	$files = read_dir_contents($patch, 'files');
	if ($files) {
		sort($files, SORT_NUMERIC);
		foreach ($files as $file) {
			$pages[] = $patch.'/'.$file;
			if (file_exists('data/settings/pages/'.get_page_seoname($patch.'/'.$file)))
				get_pages('data/settings/pages/'.get_page_seoname($patch.'/'.$file), $pages);
		}
		unset($file);

		foreach ($pages as $key => $page)
			$pages[$key] = str_replace('data/settings/pages/', '', $page);

		return $pages;
	}

	return false;
}

function show_page_box($file) {
	global $lang;

	include_once ('data/settings/pages/'.$file);
	$file = get_page_seoname($file);

	//Find the margin.
	preg_match_all('|\/|', $file, $margin);
	if (!empty($margin[0]))
		$margin = count($margin[0]) * 20 + 10;
	else
		$margin = 0;

	//We have to check for RTL.
	if (DIRECTION_RTL)
		$direction = 'right';
	else
		$direction = 'left'
	?>
		<div class="menudiv" <?php if ($margin != 0) echo 'style="margin-'.$direction.': '.$margin.'px;"'; ?>>
			<span>
				<img src="data/image/page.png" alt="" />
			</span>
			<span class="title-page"><?php echo $title; ?></span>
			<?php run_hook('admin_page_list_before', array(&$file)); ?>
			<span>
				<a href="?action=editpage&amp;page=<?php echo $file; ?>">
					<img src="data/image/edit.png" title="<?php echo $lang['page']['edit']; ?>" alt="<?php echo $lang['page']['edit']; ?>" />
				</a>
			</span>
			 <?php run_hook('admin_page_list_inside', array(&$file)); ?>
			<span>
				<a href="?action=pageup&amp;var1=<?php echo $file; ?>">
					<img src="data/image/up.png" title="<?php echo $lang['page']['change_order']; ?>" alt="<?php echo $lang['page']['change_order']; ?>" />
				</a>
			</span>
			<span>
				<a href="?action=pagedown&amp;var1=<?php echo $file; ?>">
					<img src="data/image/down.png" title="<?php echo $lang['page']['change_order']; ?>" alt="<?php echo $lang['page']['change_order']; ?>" />
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
	global $lang;

	$pages = get_pages();
	echo '<select name="'.$name.'" id="'.$name.'">';
	echo '<option value="">'.$lang['general']['none'].'</option>';

	if ($pages) {
		foreach ($pages as $page) {
			//You should not be able to add a page as a sub-page of itself.
			if (is_null($current_page) || (strpos($page, get_page_filename($current_page)) === false && strpos(get_page_seoname($page), $current_page.'/') === false)) {
				include ('data/settings/pages/'.$page);

				preg_match_all('|\/|', $page, $indent);
				$indent = count($indent[0]);

				if (!empty($indent))
					$indent = str_repeat('&emsp;', $indent);
				else
					$indent = null;

				if (get_page_seoname($page) == get_sub_page_dir($current_page) || (isset($_POST[$name]) && $_POST[$name] == get_page_seoname($page).'/'))
					$selected = ' selected="selected"';
				else
					$selected = null;

				echo '<option value="'.get_page_seoname($page).'/"'.$selected.'>'.$indent.$title.'</option>';
			}
		}
		unset($page);
	}
	echo '</select>';
}

function reorder_pages($patch) {
	$pages = read_dir_contents($patch, 'files');

	//Only reorder if there are any files.
	if ($pages) {
		sort($pages, SORT_NUMERIC);

		$number = 1;
		foreach ($pages as $page) {
			$parts = explode('.', $page);
			rename($patch.'/'.$page, $patch.'/'.$number.'.'.$parts[1].'.'.$parts[2]);
			$number++;
		}
	}
}

function show_common_submits($url, $exit = false) {
	global $lang;
	?>
		<input <?php if ($exit) echo 'class="save"'; ?> type="submit" name="save" value="<?php echo $lang['general']['save']; ?>" title="<?php echo $lang['general']['save']; ?>" />
		<?php if ($exit): ?>
		<input type="submit" name="save_exit" value="<?php echo $lang['general']['save_exit']; ?>" title="<?php echo $lang['general']['save_exit']; ?>" />
		<?php endif; ?>
		<a class="cancel" href="<?php echo $url; ?>" title="<?php echo $lang['general']['cancel']; ?>"><?php echo $lang['general']['cancel']; ?></a>
	<?php
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
	save_file('data/settings/install.dat', '');
}

/*SAVE FUNCTIONS*/

//Function: save the login password.
//-------------------
function save_password($password) {
	//MD5-hash password
	$password = hash('sha512', $password);
	//Save password
	save_file('data/settings/pass.php', array('ww' => $password));
}

//Function: save the options.
//-------------------
function save_options($title, $email) {
	$title = sanitize($title);
	$email = sanitize($email);
	$data = '<?php'."\n"
	.'$sitetitle = \''.$title.'\';'."\n"
	.'$email = \''.$email.'\';'."\n"
	.'?>';
	save_file('data/settings/options.php', $data);
}

//Function: save the prefered language.
//-------------------
function save_language($language) {
	save_file('data/settings/langpref.php', array('langpref' => $language), FALSE);
}

//Function: save theme.
//-------------------
function save_theme($theme) {
	save_file('data/settings/themepref.php', array('themepref' => $theme));
}

/**
 * Save a page with a lot of options.
 *
 * @param string $title The title.
 * @param string $content The content.
 * @param string $hidden Should it be hidden (yes or no)?
 * @param string $subpage The sub-page.
 * @param string $description The description.
 * @param string $keywords The keywords.
 * @param array $module_additional_data Additional data supplied by modules.
 * @param string $current_seoname If we want to edit a page.
 */
function save_page($title, $content, $hidden, $subpage, $description = null, $keywords = null, $module_additional_data = null, $current_seoname = null) {
	//Run a few hooks.
	run_hook('admin_save_page', array(&$title, &$content));
	run_hook('admin_save_page_meta', array(&$description, &$keywords));
	run_hook('admin_save_page_module_additional_data', array(&$module_additional_data));

	//Do we want to create a new page?
	if (!isset($current_seoname)) {
		//Check if a page already exists with the name.
		if (get_page_filename($subpage.seo_url($title)) != false)
			return false;

		//Check if we want a sub-page.
		if (!empty($subpage)) {
			//We need to make sure that the dir exists, and if not, create it.
			if (!file_exists('data/settings/pages/'.rtrim($subpage, '/'))) {
				mkdir('data/settings/pages/'.rtrim($subpage, '/'));
				chmod('data/settings/pages/'.rtrim($subpage, '/'), 0777);
			}
			$pages = read_dir_contents('data/settings/pages/'.rtrim($subpage, '/'), 'files');
		}

		else
			$pages = read_dir_contents('data/settings/pages', 'files');

		//Are there any pages?
		if ($pages == false)
			$number = 1;
		else
			$number = count($pages) + 1;

		$seo_title = seo_url($title);
		$newfile = $subpage.$number.'.'.$seo_title;
	}

	//Then we want to edit a page.
	else {
		$filename = get_page_filename($current_seoname);

		//Is it a sub-page, or do we want to make it one?
		if (strpos($current_seoname, '/') !== false || !empty($subpage)) {
			$page_name = explode('/', $subpage);
			$count = count($page_name);
			unset($page_name[$count]);

			$dir = get_sub_page_dir($current_seoname);
			$filename_array = str_replace($dir.'/', '', $filename);
			$filename_array = explode('.', $filename_array);

			$newfilename = implode('/', $page_name).'/'.$filename_array[0].'.'.seo_url($title);
			$newdir = get_sub_page_dir($newfilename);

			//We need to make sure that the dir exists, and if not, create it.
			if (!file_exists('data/settings/pages/'.$newdir)) {
				mkdir('data/settings/pages/'.$newdir);
				chmod('data/settings/pages/'.$newdir, 0777);
			}

			//If the name isn't the same as before, we have to find the correct number.
			if ($newfilename.'.php' != $filename) {
				//If the sub-folder is the same, use the same number as before.
				if ($dir.'/' == $newdir)
					$number = $filename_array[0];

				else {
					$pages = read_dir_contents('data/settings/pages/'.$newdir, 'files');

					if ($pages) {
						$count = count($pages);
						$number = $count + 1;
					}
					else
						$number = 1;
				}

				$newfile = implode('/', $page_name).$number.'.'.seo_url($title);
			}
		}

		//If not, just create the new filename.
		else {
			$filename_array = explode('.', $filename);
			$newfile = $filename_array[0].'.'.seo_url($title);
		}
	}

	//Save the title, content and hidden status.
	$data = '<?php'."\n"
	.'$title = \''.sanitize($title).'\';'."\n"
	.'$content = \''.sanitize($content, false).'\';'."\n"
	.'$hidden = \''.$hidden.'\';';

	//Save the description and keywords, if any.
	if ($description != null)
		$data .= "\n".'$description = \''.sanitize($description).'\';';
	if ($keywords != null)
		$data .= "\n".'$keywords = \''.sanitize($keywords).'\';';

	//If modules have supplied additional data, save it.
	if ($module_additional_data != null && is_array($module_additional_data)) {
		foreach ($module_additional_data as $var => $value) {
			$data .= "\n".'$'.$var.' = \''.$value.'\';';
		}
	}

	$data .= "\n".'?>';

	//Save the file.
	save_file('data/settings/pages/'.$newfile.'.php', $data);

	//Do a little cleanup if we are editing a page.
	if (isset($current_seoname)) {
		//Check if the title is different from what we started with.
		if ($newfile.'.php' != $filename) {
			//If there are sub-pages, rename the folder.
			if (file_exists('data/settings/pages/'.get_page_seoname($filename)))
				rename('data/settings/pages/'.get_page_seoname($filename), 'data/settings/pages/'.get_page_seoname($newfile.'.php'));

			//Remove the old file.
			unlink('data/settings/pages/'.$filename);

			//If there are no files in the old dir, delete it.
			if (isset($dir) && read_dir_contents('data/settings/pages/'.$dir, 'files') == false)
				rmdir('data/settings/pages/'.$dir);

			//If there are pages, we need to reorder them.
			elseif (isset($dir))
				reorder_pages('data/settings/pages/'.$dir);
			else
				reorder_pages('data/settings/pages');
		}
	}

	//Return the seoname.
	return get_page_seoname($newfile.'.php');
}
?>