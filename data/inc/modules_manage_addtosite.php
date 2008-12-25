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
	exit();
}

//Introduction text
?>
	<p>
		<strong><?php echo $lang_modules15; ?></strong>
	</p>
<?php

//Include info of theme (to see which positions we can use).
include_once ('data/themes/'.THEME.'/info.php');
//Include the existing module-settings for the theme.
if (file_exists('data/settings/themes/'.THEME.'/moduleconf.php'))
	include_once ('data/settings/themes/'.THEME.'/moduleconf.php');

//Start html-form.
?>
<form action="" method="post">
<?php
	foreach ($module_space as $index => $position) {
	?>
		<div class="menudiv" style="margin: 10px;">
		<table>
			<tr>
				<td>
					<img src="data/image/page.png" alt="" />
				</td>
				<td>
					<span style="font-size: 17pt; color: gray;"><?php echo $position; ?></span>
					<br />
					<strong><?php echo $lang_modules7; ?></strong>
					<table>
						<?php
							//Define path to the module-dir.
							$path = 'data/modules';

							//Open the folder.
							$dir_handle = @opendir($path) or die('Unable to open '.$path.'. Check if it\'s readable.');

							//First count how many modules we have, and exclude disabled modules.
							$number_modules = count(glob('data/modules/*'));
							while ($dir = readdir($dir_handle)) {
								if($dir != '.' && $dir != '..') {
									if (!module_is_compatible($dir))
										$number_modules--;
								}
							}
							closedir($dir_handle);

							//Loop through dirs, and display the modules.
							$dir_handle = @opendir($path) or die('Unable to open '.$path.'. Check if it\'s readable.');
							while ($dir = readdir($dir_handle)) {
								if ($dir != '.' && $dir != '..') {
									//Only show if module is compatible.
									if (module_is_compatible($dir)) {
										include('data/modules/'.$dir.'/module_info.php');
										?>
											<tr>
												<td><?php echo $module_name; ?></td>
												<td>
													<select name="<?php echo $position.'|'.$module_dir; ?>">
														<option value="0"><?php echo $lang_modules6; ?></option>
														<?php
															$counting_modules = 1;
															while ($counting_modules <= $number_modules) {
																//Check if this is the current setting.
																//...and select the html-option if needed.
																$currentsetting = $space [$position] [$module_dir];
																if ($currentsetting == $counting_modules)
																	echo '<option value="'.$counting_modules.'" selected="selected">'.$counting_modules.'</option>';

																//...if this is not the current setting, don't select the html-option.
																else
																	echo '<option value="'.$counting_modules.'">'.$counting_modules.'</option>';

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
							}
							closedir($dir_handle);
						?>
					</table>
				</td>
			</tr>
		</table>
		</div>
	<?php
	}

	//Show submit button etc.
	?>
	<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
	<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=managemodules';" />
</form>
<?php
//If the form has been posted, save the data.
//------------------------------------------
if (isset($_POST['Submit'])) {
	//Get POST-data.
	if (isset($_POST['moduledir']))
		$moduledir = $_POST['moduledir'];
	if (isset($_POST['position']))
		$position = $_POST['position'];

	//First, check if the settings/modules_inc dir exists.
	//If not, create the dir.
	if (!file_exists('data/settings/themes')) {
		mkdir('data/settings/themes', 0777);
		chmod('data/settings/themes', 0777);
	}

	//Then, check if a dir for the current theme is already available.
	//If not, create the appropriate dirs.
	if (!file_exists('data/settings/themes/'.THEME)) {
		mkdir('data/settings/themes/'.THEME, 0777);
		chmod('data/settings/themes/'.THEME, 0777);
	}

	//Then fopen it.
	$file = fopen('data/settings/themes/'.THEME.'/moduleconf.php', 'w');
	fputs($file, '<?php');

	//Call all POST-variables.
	foreach ($_POST as $variabletosplice => $display_order) {
		//Exclude the Submit-button variable, and the modules that we don't want to show.
		if ($variabletosplice != 'Submit' && $display_order != 0) {
			list($areaname, $modulename) = explode('|', $variabletosplice);
			//Save the data.
			fputs($file, "\n".'$space[\''.$areaname.'\'][\''.$modulename.'\'] = '.$display_order.';');
		}
	}

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
?>