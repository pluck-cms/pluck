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
if ((!ereg('index.php', $_SERVER ['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER ['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER ['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER ['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo 'access denied';
    //Block all other code
    exit();
}

//Generate the menu on the right
?>
<div class="rightmenu">
<?php echo $lang_page8; ?>
<br />
<?php
	read_imagesinpages('images');
	read_pagesinpages('data/settings/pages');
?>
</div>
<?php
//Form
?>
<form method="post" action="">
	<span class="kop2"><?php echo $lang_install17; ?></span>
	<br />
	<input name="kop" type="text" value="" />
	<br /><br />
	<span class="kop2"><?php echo $lang_install18; ?></span>
	<br />
	<textarea class="tinymce" name="tekst" cols="70" rows="20"></textarea>
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
						//Define path to the module-dir
						$path = 'data/modules';
						//Open the folder
						$dir_handle = @opendir($path) or die('Unable to open '.$path.'. Check if it\'s readable.');
						//First count how many modules we have, and exclude disabled modules
						$number_modules = count(glob('data/modules/*'));
						while ($dir = readdir($dir_handle)) {
							if ($dir != '.' && $dir != '..') {
								if (!module_is_compatible($dir)) {
									$number_modules = $number_modules - 1;
								}
							}
						}
						closedir($dir_handle);

						//Loop through dirs, and display the modules
						$dir_handle = @opendir($path) or die('Unable to open '.$path.'. Check if it\'s readable.');
						while ($dir = readdir($dir_handle)) {
							if ($dir != "." && $dir != "..") {
								//Only show if module is compatible
								if (module_is_compatible($dir)) {
									include ('data/modules/'.$dir.'/module_info.php');
									?>
									<tr>
										<td><?php echo $module_name; ?></td>
										<td>
											<select name="incmodule[<?php echo $module_dir; ?>]">
												<option value="0"><?php echo $lang_modules6; ?></option>
												<?php
													$counting_modules = 1;
													while ($counting_modules <= $number_modules) {
														//Check if this is the current setting
	//...and select the html-option if needed
	if (isset($module_pageinc))
		$currentsetting = $module_pageinc[$module_dir];
	if (isset($currentsetting) && $currentsetting == $counting_modules) {
														?>
															<option value="<?php echo $counting_modules; ?>" selected="selected"><?php echo $counting_modules; ?></option>
														<?php
														}

														//...if this is no the current setting, don't select the html-option
														else {
														?>
															<option value="<?php echo $counting_modules; ?>"><?php echo $counting_modules; ?></option>
														<?php
														}

														//Higher counting_modules
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
					<input type="checkbox" name="hidepage" value="no" checked="checked" /><?php echo $lang_pagehide1; ?>
					<br />
				</td>
			</tr>
		</table>
	</div>
	<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
	<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=page';" />
</form>
<?php
//TODO: Cleanup the rest.
//If form is posted...
if (isset($_POST['Submit'])) {
	//Check if we want to show the page in the menu.
	if ($hidepage != 'no') {
		$hidepage = 'yes';
	}

	//Now we have to check which filenames are already in use.
	if (file_exists('data/settings/pages/kop1.php')) {
		$i = 2;
		$o = 3;
		while ((file_exists('data/settings/pages/kop'.$i.'.php')) || (file_exists('data/settings/pages/kop'.$o.'.php'))) {
			$i = $i + 1;
			$o = $o + 1;
		}
		$newfile = 'data/settings/pages/kop'.$i.'.php';
	}
	else {
		$newfile = 'data/settings/pages/kop1.php';
	}

	$data = $newfile;

	//TODO: We should use the save_page function, but it can not save $module_pageinc.
	
	//Sanitize data
	sanitize($tekst);
	htmlspecialchars($kop);
	sanitize($kop);
	if (isset($keywords))
		sanitize($keywords);
	if (isset($description))
		sanitize($description);

$file = fopen($data, "w");
fputs($file, "<?php
\$title = \"$kop\";
\$content = \"$tekst\";
\$hidden = \"$hidepage\";");

	//Save the module information
	foreach ($incmodule as $modulename => $order) {
		fputs($file, "\n\$module_pageinc['$modulename'] = \"$order\";");
	}

	//Close the file
	fputs($file, "\n".'?>');
	fclose($file);
	//Give the file the right permissions
	chmod($data, 0777);

	//and redirect user
	redirect('?action=page', 0);
}
?>