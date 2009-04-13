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

//redirect for a cancel
if (isset($_POST['cancel']))
	redirect('?action=page', 0);

//Load module functions.
foreach ($module_list as $module) {
	if (file_exists('data/modules/'.$module.'/'.$module.'.site.php'))
		require_once ('data/modules/'.$module.'/'.$module.'.site.php');
}
unset($module);

//If form is posted...
if (isset($_POST['save']) || isset($_POST['save_exit'])) {
	$pages = read_dir_contents('data/settings/pages', 'files');

	if ($pages == false)
		$pages = 0;
	else
		$pages = count($pages);

	$pages++;
	$seo_title = seo_url($cont1);
	$newfile = $pages.'.'.$seo_title;

	//Save the page.
	save_page($newfile, $cont1, $cont2, $cont4, null, null, $cont3);

	//Redirect the user.
	if (isset($_POST['save'])){
		$filename = get_page_filename($newfile);
		redirect('?action=editpage&var1='.$seo_title, 0);
	}
	elseif (isset($_POST['save_exit']))
		redirect('?action=page', 0);

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
	<label class="kop2" for="cont1"><?php echo $lang['general']['title']; ?></label>
	<br />
	<input name="cont1" id="cont1" type="text" />
	<br /><br />
	<span class="kop2"><?php echo $lang['general']['contents']; ?></span>
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
					<span class="kop3"><?php echo $lang['modules']['title']; ?></span>
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
							unset($module);
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
					<span class="kop3"><?php echo $lang['general']['other_options']; ?></span>
					<br />
					<input type="checkbox" name="cont4" id="cont4" checked="checked" value="no" /><label for="cont4"><?php echo $lang_pagehide1; ?></label>
					<br />
				</td>
			</tr>
		</table>
	</div>
	<input class="save" type="submit" name="save" value="<?php echo $lang['general']['save']; ?>"/>
	<input type="submit" name="save_exit" value="<?php echo $lang['general']['save_exit']; ?>" title="<?php echo $lang['general']['save_exit']; ?>" />
	<input class="cancel" type="submit" name="cancel" title="<?php echo $lang['general']['cancel']; ?>" value="<?php echo $lang['general']['cancel']; ?>" />
</form>
