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

//If form is posted...
if (isset($_POST['save']) || isset($_POST['save_exit'])) {
	if (!isset($cont4))
		$cont4 = 'yes';
	if (empty($description))
		$description = '';
	if (empty($keywords))
		$keywords = '';

	//Save the page.
	$seoname = save_page($cont1, $cont2, $cont4, $cont5, $description, $keywords, $cont3, $var1);

	//Redirect to the new title only if it is a plain save.
	if (isset($_POST['save'])) {
		redirect('?action=editpage&var1='.$seoname, 0);
		include_once ('data/inc/footer.php');
		exit;
	}

	//Redirect the user. only if they are doing a save_exit.
	elseif (isset($_POST['save_exit'])) {
		redirect('?action=page', 0);
		include_once ('data/inc/footer.php');
		exit;
	}
}

//Include page information.
require_once ('data/settings/pages/'.get_page_filename($var1));

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
	<div class="menudiv" style="width: 588px; margin-<?php if (DIRECTION_RTL) echo 'right'; else echo 'left'; ?>: 0;">
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
	<div class="menudiv" style="width: 588px; margin-<?php if (DIRECTION_RTL) echo 'right'; else echo 'left'; ?>: 0;">
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