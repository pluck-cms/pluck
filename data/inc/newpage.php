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

//Load module functions.
foreach ($module_list as $module) {
	if (file_exists('data/modules/'.$module.'/'.$module.'.site.php'))
		require_once ('data/modules/'.$module.'/'.$module.'.site.php');
}

//Generate the menu on the right.
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
//Form.
?>
<form method="post" action="">
	<label class="kop2" for="cont1"><?php echo $lang['title']; ?></label>
	<br />
	<input name="cont1" id="cont1" type="text" />
	<br /><br />
	<span class="kop2"><?php echo $lang['contents']; ?></span>
	<br />
	<textarea class="tinymce" name="cont2" cols="70" rows="20"></textarea>
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
						//First count how many modules we have, and exclude disabled modules.
						$number_modules = count($module_list);
						foreach ($module_list as $module) {
							if (!module_is_compatible($module) || !function_exists($module.'_theme_main'))
								$number_modules--;
						}

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
												<option value="0"><?php echo $lang_modules6; ?></option>
												<?php
													$counting_modules = 1;
													while ($counting_modules <= $number_modules) {
														?>
															<option value="<?php echo $counting_modules; ?>"><?php echo $counting_modules; ?></option>
														<?php
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
					<span class="kop3"><?php echo $lang['other_options']; ?></span>
					<br />
					<input type="checkbox" name="cont4" id="cont4" checked="checked" value="no" /><label for="cont4"><?php echo $lang_pagehide1; ?></label>
					<br />
				</td>
			</tr>
		</table>
	</div>
	<input type="submit" name="Submit" value="<?php echo $lang['save']; ?>" />
	<input type="button" name="Cancel" value="<?php echo $lang['cancel']; ?>" onclick="javascript: window.location='?action=page';" />
</form>
<?php
//If form is posted...
if (isset($_POST['Submit'])) {
	//Check which filenames are already in use.
	if (file_exists('data/settings/pages/kop1.php')) {
		$i = 2;
		$o = 3;
		while (file_exists('data/settings/pages/kop'.$i.'.php') || file_exists('data/settings/pages/kop'.$o.'.php')) {
			$i++;
			$o++;
		}
		$newfile = 'kop'.$i;
	}
	else {
		$newfile = 'kop1';
	}

	//Save the page.
	save_page($newfile, $cont1, $cont2, $cont4, null, null, $cont3);

	//Redirect the user.
	redirect('?action=page', 0);
}
?>