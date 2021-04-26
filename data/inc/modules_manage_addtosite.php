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

//Load module functions.
foreach ($module_list as $module) {
	if (file_exists('data/modules/'.$module.'/'.$module.'.site.php'))
		require_once ('data/modules/'.$module.'/'.$module.'.site.php');
}
unset($module);

//Introduction text
?>
	<p>
		<strong><?php echo $lang['modules_addtosite']['message']; ?></strong>
	</p>
<?php

//Include info of theme (to see which positions we can use).
include_once ('data/themes/'.THEME.'/info.php');
//Include the existing module-settings for the theme.
if (file_exists('data/settings/themes/'.THEME.'/moduleconf.php'))
	include_once ('data/settings/themes/'.THEME.'/moduleconf.php');

//If the form has been posted, save the data.
if (isset($_POST['save'])) {
	//Get POST-data.
	if (isset($_POST['moduledir']))
		$moduledir = $_POST['moduledir'];
	if (isset($_POST['position']))
		$position = $_POST['position'];

	//First, check if the settings/modules_inc dir exists.
	//If not, create the dir.
	if (!file_exists('data/settings/themes')) {
		mkdir('data/settings/themes');
		chmod('data/settings/themes', 0777);
	}

	//Then, check if a dir for the current theme is already available.
	//If not, create the appropriate dirs.
	if (!file_exists('data/settings/themes/'.THEME)) {
		mkdir('data/settings/themes/'.THEME);
		chmod('data/settings/themes/'.THEME, 0777);
	}

	//Then fopen it.
	$file = fopen('data/settings/themes/'.THEME.'/moduleconf.php', 'w');
	fputs($file, '<?php');

	//Call all POST-variables.
	foreach ($_POST as $variabletosplice => $display_order) {
		//Exclude the submit-button variable, and the modules that we don't want to show.
		if ($variabletosplice != 'submit' && $display_order != 0) {
			list($areaname, $modulename) = explode('|', $variabletosplice);
			//Save the data.
			fputs($file, "\n".'$space[\''.$areaname.'\'][\''.$modulename.'\'] = '.$display_order.';');
		}
	}
	unset($variabletosplice);

	//Close the file.
	fputs($file, "\n".'?>');
	fclose($file);
	chmod('data/settings/themes/'.THEME.'/moduleconf.php', 0777);

	//If there are no modules, just delete the file.
	if (!isset($areaname))
		unlink('data/settings/themes/'.THEME.'/moduleconf.php');

	//And redirect the user.
	redirect('?action=module_addtosite', 0);
}

//Start html-form.
?>
<form action="" method="post">
<?php
	foreach ($module_space as $index => $position) {
	?>
		<div class="menudiv margin10">
		<table>
			<tr>
				<td>
					<img src="data/image/page.png" alt="" />
				</td>
				<td>
					<span class="font17gray"><?php echo $position; ?></span>
					<br />
					<strong><?php echo $lang['modules_addtosite']['choose_order']; ?></strong>
					<table>
						<?php
							//First count how many modules we have, and exclude disabled modules.
							$number_modules = count($module_list);
							foreach ($module_list as $module) {
								if (!module_is_compatible($module) || !function_exists($module.'_theme_main'))
									$number_modules--;
							}
							unset($module);

							//Loop through dirs, and display the modules.
							foreach ($module_list as $module) {
								//Only show if module is compatible.
								if (module_is_compatible($module) && function_exists($module.'_theme_main')) {
									$module_info = call_user_func($module.'_info');
									?>
										<tr>
											<td><?php echo $module_info['name']; ?></td>
											<td>
												<select name="<?php echo $position.'|'.$module; ?>">
													<option value="0"><?php echo $lang['general']['dont_display']; ?></option>
													<?php
														$counting_modules = 1;
														while ($counting_modules <= $number_modules) {
															//Check if this is the current setting.
															//...and select the html-option if needed.
															if (isset($space[$position][$module]))
																$currentsetting = $space[$position][$module];
															if (isset($currentsetting) && $currentsetting == $counting_modules)
																echo '<option value="'.$counting_modules.'" selected="selected">'.$counting_modules.'</option>';

															//...if this is not the current setting, don't select the html-option.
															else
																echo '<option value="'.$counting_modules.'">'.$counting_modules.'</option>';

															//Higher counting_modules.
															$counting_modules++;
															unset($currentsetting);
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
				</td>
			</tr>
		</table>
		</div>
	<?php
	}
	unset($index);

	//Show submit button etc.
	show_common_submits('?action=managemodules');
	?>
</form>