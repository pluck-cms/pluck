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

//Include page information.
	require_once ('data/settings/pages/'.$var1);

//Generate the menu on the right.
?>
<div class="rightmenu">
<?php
	//We don't want to show the message, if there aren't any pages or images.
	if (read_imagesinpages('images') != false && read_pagesinpages('data/settings/pages', $var1) != false)
		echo $lang_page8;
?>
<br />
<?php
	read_imagesinpages('images');
	read_pagesinpages('data/settings/pages', $var1);
?>
</div>
<?php
//Form.
?>
<form method="post" action="">
	<label class="kop2" for="cont1"><?php echo $lang_install17; ?></label>
	<br />
	<input name="cont1" id="cont1" type="text" value="<?php echo $title; ?>" />
	<br /><br />
	<span class="kop2"><?php echo $lang_install18; ?></span>
	<br />
	<textarea class="tinymce" name="cont2" cols="70" rows="20"><?php echo htmlspecialchars($content) ?></textarea>
	<br />
	<div class="menudiv" style="width: 585px; margin-left: 0px;">
		<table>
			<tr>
				<td>
					<img src="data/image/modules.png" alt="" />
				</td>
				<td>
					<span class="kop3"><?php echo $lang_modules; ?></span>
					<br />
					<strong><?php echo $lang_modules16; ?></strong>
					<br />
					<table>
					<?php
						//Define path to the module-dir.
						$path = 'data/modules';
						//Open the folder.
						$dir_handle = @opendir($path) or die('Unable to open '.$path.'. Check if it\'s readable.');
						//First count how many modules we have, and exclude disabled modules.
						$number_modules = count(glob('data/modules/*'));
						while ($dir = readdir($dir_handle)) {
							if ($dir != '.' && $dir != '..') {
								if (!module_is_compatible($dir))
									$number_modules--;
							}
						}
						closedir($dir_handle);

						//Loop through dirs, and display the modules.
						$dir_handle = @opendir($path) or die('Unable to open '.$path.'. Check if it\'s readable.');
						while ($dir = readdir($dir_handle)) {
							if ($dir != "." && $dir != "..") {
								//Only show if module is compatible.
								if (module_is_compatible($dir)) {
									include ('data/modules/'.$dir.'/module_info.php');
									?>
										<tr>
											<td><?php echo $module_name; ?></td>
											<td>
												<select name="cont3[<?php echo $module_dir; ?>]">
													<option value="0"><?php echo $lang_modules6; ?></option>
													<?php
														$counting_modules = 1;
														while ($counting_modules <= $number_modules) {
															//Check if this is the current setting.
															//...and select the html-option if needed
															if (isset($module_pageinc[$module_dir]) && $module_pageinc[$module_dir] == $counting_modules) {
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
							}
							closedir($dir_handle);
						?>
					</table>
				</td>
			</tr>
		</table>
	</div>
	<div class="menudiv" style="width: 585px; margin-left: 0px;">
		<table>
			<tr>
				<td>
					<img src="data/image/options.png" alt="" />
				</td>
				<td>
					<span class="kop3"><?php echo $lang_contact2; ?></span>
					<br />
					<input type="checkbox" name="cont4" id="cont4" <?php if ($hidden == 'no') echo'checked="checked"'; ?> value="no" /><label for="cont4"><?php echo $lang_pagehide1; ?></label>
					<br />
				</td>
			</tr>
		</table>
	</div>
	<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
	<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=page';" />
</form>
<?php
//If form is posted...
if (isset($_POST['Submit'])) {
	//Remove .php from the filename. We add it again in save_page.
	$var1 = preg_replace('/.php$/', '', $var1);

	//Save the page.
	save_page($var1, $cont1, $cont2, $cont4, $description, $keywords, $cont3);

	//Redirect the user.
	redirect('?action=page', 0);
}
?>