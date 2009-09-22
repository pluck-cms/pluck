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
	exit;
}

//Get the filename.
$filename = get_page_filename($var1);

//If form is posted...
if (isset($_POST['save']) || isset($_POST['save_exit'])) {
	//Is it a sub-page, or do we want to make it one?
	if (strpos($var1, '/') !== false || !empty($cont5)) {
		$page_name = explode('/', $cont5);
		$count = count($page_name);
		unset($page_name[$count]);

		$dir = get_sub_page_dir($var1);
		$filename_array = str_replace($dir.'/', '', $filename);
		$filename_array = explode('.', $filename_array);

		$newfilename = implode('/', $page_name).'/'.$filename_array[0].'.'.seo_url($cont1);
		$newdir = get_sub_page_dir($newfilename);

		//We need to make sure that the dir exists, and if not, create it.
		if (!file_exists('data/settings/pages/'.$newdir)) {
			mkdir('data/settings/pages/'.$newdir, 0777);
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

			$newfilename = implode('/', $page_name).$number.'.'.seo_url($cont1);
		}
	}

	//If not, just create the new filename.
	else {
		$filename_array = explode('.', $filename);
		$newfilename = $filename_array[0].'.'.seo_url($cont1);
	}

	if (!isset($cont4))
		$cont4 = 'no';
	if (empty($description))
		$description = '';
	if (empty($keywords))
		$keywords = '';

	//Save the page.
	save_page($newfilename, $cont1, $cont2, $cont4, $description, $keywords, $cont3);

	//Check if the title is different from what we started with.
	if ($newfilename.'.php' != $filename) {
		//If there are sub-pages, rename the folder.
		if (file_exists('data/settings/pages/'.get_page_seoname($filename)))
			rename('data/settings/pages/'.get_page_seoname($filename), 'data/settings/pages/'.get_page_seoname($newfilename.'.php'));

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

		//Redirect to the new title only if it is a plain save.
		if (isset($_POST['save'])) {
			redirect('?action=editpage&var1='.get_page_seoname($newfilename.'.php'), 0);
			include_once ('data/inc/footer.php');
			exit;
		}
	}

	//Redirect the user. only if they are doing a save_exit.
	if (isset($_POST['save_exit'])) {
		redirect('?action=page', 0);
		include_once ('data/inc/footer.php');
		exit;
	}
}

//Include page information.
require_once ('data/settings/pages/'.$filename);

//Load module functions.
foreach ($module_list as $module) {
	if (file_exists('data/modules/'.$module.'/'.$module.'.site.php'))
		require_once ('data/modules/'.$module.'/'.$module.'.site.php');
}
unset($module);

//Generate the menu on the right.
?>
<div class="rightmenu">
<p><?php echo $lang['page']['items']; ?></p>
<?php
	read_pagesinpages();
	read_imagesinpages('images');
?>
</div>
<form name="page_form" method="post" action="">
	<p>
		<label class="kop2" for="cont1"><?php echo $lang['general']['title']; ?></label>
		<br />
		<input name="cont1" id="cont1" type="text" value="<?php echo $title; ?>" />
	</p>
	<span class="kop2"><?php echo $lang['general']['contents']; ?></span>
	<br />
	<textarea class="tinymce" name="cont2" cols="70" rows="20"><?php echo htmlspecialchars($content) ?></textarea>
	<div class="menudiv" style="width: 588px; margin-left: 0;">
		<p class="kop2"><?php echo $lang['modules']['title']; ?></p>
		<p class="kop4" style="margin-bottom: 5px;"><?php echo $lang['page']['modules']; ?></p>
		<table>
		<?php
			//First count how many modules we have, and exclude disabled modules.
			$number_modules = count($module_list);
			foreach ($module_list as $module) {
				if (!module_is_compatible($module) || !function_exists($module.'_theme_main'))
					$number_modules--;
			}
			unset($module);

			//Loop through modules, and display them.
			foreach ($module_list as $module) {
				//Only show if module is compatible.
				if (module_is_compatible($module) && function_exists($module.'_theme_main')) {
					$module_info = call_user_func($module.'_info');
					?>
						<tr>
							<td><?php echo $module_info['name']; ?></td>
							<td>
								<select name="cont3[<?php echo $module; ?>]">
									<option value="0"><?php echo $lang['general']['dont_display']; ?></option>
									<?php
										$counting_modules = 1;
										while ($counting_modules <= $number_modules) {
											//Check if this is the current setting.
											//...and select the html-option if needed
											if (isset($module_pageinc[$module]) && $module_pageinc[$module] == $counting_modules) {
											?>
												<option value="<?php echo $counting_modules; ?>" selected="selected"><?php echo $counting_modules; ?></option>
											<?php
											}

											//...if this is no the current setting, don't select the html-option.
											else {
											?>
												<option value="<?php echo $counting_modules; ?>"><?php echo $counting_modules; ?></option>
											<?php
											}

											//Higher counting_modules.
											$counting_modules++;
										}
									?>
								</select>
							</td>
						</tr>
					<?php
				}
			}
			unset($module);
		?>
		</table>
	</div>
	<div class="menudiv" style="width: 588px; margin-left: 0;">
		<p class="kop2"><?php echo $lang['general']['other_options']; ?></p>
		<p class="kop4" style="margin-bottom: 5px;"><?php echo $lang['page']['options']; ?></p>
		<input type="checkbox" name="cont4" id="cont4" <?php if ($hidden == 'no') echo'checked="checked"'; ?> value="no" />
		<label for="cont4"><?php echo $lang['page']['in_menu']; ?></label>
		<br />
		<label for="cont5"><?php echo $lang['page']['sub_page']; ?></label>
		<?php show_subpage_select('cont5', $var1); ?>
	</div>
	<?php show_common_submits('?action=page', true); ?>
</form>