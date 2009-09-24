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

//Include security-enhancements.
require_once ('data/inc/security.php');
//Include functions.
require_once ('data/inc/functions.modules.php');
require_once ('data/inc/functions.all.php');
require_once ('data/inc/functions.admin.php');
//Include Translation data.
require_once ('data/inc/variables.all.php');

//Check if we've installed pluck.
if (file_exists('data/settings/install.dat')) {
	$titelkop = $lang['install']['title'];
	include_once ('data/inc/header2.php');
	redirect('login.php', 3);
	show_error($lang['install']['already'], 2);
	include_once ('data/inc/footer.php');
}

//If we didn't:
else {
	if (!isset($action)) {
		$titelkop = $lang['install']['title'];
		include_once ('data/inc/header2.php');
		//Introduction text.
		?>
		<span class="kop2"><?php echo $lang['install']['title']; ?></span>
		<br />
		<p>
			<strong><?php echo $lang['install']['welcome']; ?></strong>
		</p>
		<?php
		//Show installation button.
		?>
		<a href="?action=install"><?php echo $lang['install']['start']; ?></a>
		<?php
		include_once ('data/inc/footer.php');
	}

	//Installation Step 1: CHMOD.
	elseif (isset($action) && $action == 'install') {
		$titelkop = $lang['install']['title'];
		include_once ('data/inc/header2.php');
		?>
		<span class="kop2"><?php echo $lang['install']['title']; ?> :: <?php echo $lang['install']['step_1']; ?></span>
		<br />
		<p>
			<strong><?php echo $lang['install']['writable']; ?></strong>
		</p>
		<?php
			//Writable checks.
			check_writable('images');
			check_writable('data/modules');
			check_writable('data/settings');
			check_writable('data/trash');
			check_writable('data/themes');
			check_writable('data/themes/default');
			check_writable('data/themes/oldstyle');
			check_writable('data/settings/langpref.php');
		?>
		<a href="javascript:refresh()"><?php echo $lang['install']['refresh']; ?></a>
		<br /><br />
		<a href="?action=install2">
			<strong><?php echo $lang['install']['proceed']; ?></strong>
		</a>
		<?php
		include_once ('data/inc/footer.php');
	}

	//Installation Step 2: General Info.
	elseif (isset($action) && $action == 'install2') {
		$titelkop = $lang['install']['title'];
		include_once ('data/inc/header2.php');

		if (isset($_POST['save'])) {
			//Check sitetitle.
			$trim_title = trim($cont1);
			if (empty($trim_title))
				$error['title'] = show_error($lang['settings']['fill_name'], 1, true);

			//Check the email.
			if (!filter_input(INPUT_POST, 'cont2', FILTER_VALIDATE_EMAIL))
				$error['email'] = show_error($lang['settings']['email_invalid'], 1, true);

			//Check the passwords.
			if ($cont4 != $cont5 || empty($cont4))
				$error['pass'] = show_error($lang['changepass']['different'], 1, true);

			if (!isset($error)) {
				//Save prefered language.
				save_language($cont3);

				//Save options.
				save_options($cont1, $cont2, false);

				//Save password.
				save_password($cont4);

				//Save theme.
				save_theme('default');

				//Make some dirs for the trashcan and modulesettings.
				mkdir('data/trash/pages', 0777);
				chmod('data/trash/pages', 0777);
				mkdir('data/trash/images', 0777);
				chmod('data/trash/images', 0777);
				mkdir('data/settings/modules', 0777);
				chmod('data/settings/modules', 0777);
				mkdir('data/settings/pages', 0777);
				chmod('data/settings/pages', 0777);

				redirect('?action=install4', 0);
				include_once ('data/inc/footer.php');
				exit;
			}
		}
		?>
		<span class="kop2"><?php echo $lang['install']['title']; ?> :: <?php echo $lang['install']['step_2']; ?></span>
		<br />
		<p>
			<strong><?php echo $lang['install']['general_info']; ?></strong>
		</p>
		<form method="post" action="">
			<p>
				<?php if (isset($error['title'])) echo $error['title'].'<br />'; ?>
				<label class="kop2" for="cont1"><?php echo $lang['general']['title'] ?></label>
				<br />
				<span class="kop4"><?php echo $lang['settings']['choose_title'] ?></span>
				<br />
				<input name="cont1" id="cont1" type="text" value="<?php if (isset($cont1)) echo htmlentities($cont1); ?>" style="width:30em;" />
				<br />
				<?php if (isset($error['email'])) echo $error['email'].'<br />'; ?>
				<label class="kop2" for="cont2"><?php echo $lang['settings']['email'] ?></label>
				<br />
				<span class="kop4"><?php echo $lang['settings']['email_descr'] ?></span>
				<br />
				<input name="cont2" id="cont2"type="text" value="<?php if (isset($cont2)) echo htmlentities($cont2); ?>" />
			</p>
			<p>
				<label class="kop2" for="cont3"><?php echo $lang['language']['title']; ?></label>
				<br />
				<select name="cont3" id="cont3">
					<option selected="selected" value="en.php">English</option>
					<?php read_lang_files('en.php'); ?>
				</select>
			</p>
			<p>
				<?php if (isset($error['pass'])) echo $error['pass'].'<br />'; ?>
				<label class="kop2" for="cont4"><?php echo $lang['login']['password']; ?></label>
				<br />
				<input name="cont4" id="cont4" type="password" />
				<br /><br />
				<label class="kop2" for="cont5"><?php echo $lang['changepass']['repeat']; ?></label>
				<br />
				<input name="cont5" id="cont5" type="password" />
			</p>
			<?php show_common_submits('?action=install'); ?>
		</form>
		<?php
		include_once ('data/inc/footer.php');
	}

	//Installation Step 3: Homepage.
	elseif (isset($action) && $action == 'install4') {
		$titelkop = $lang['install']['title'];
		include_once ('data/inc/header2.php');

		//Save the homepage.
		if (isset($_POST['save'])) {
			$pagename = seo_url($cont1);
			save_page('1.'.$pagename, $cont1, $cont2, 'no');
			redirect('?action=install5', 0);
			include_once ('data/inc/footer.php');
			exit;
		}
		?>
		<span class="kop2"><?php echo $lang['install']['title']; ?> :: <?php echo $lang['install']['step_3']; ?></span>
		<br />
		<p>
			<strong><?php echo $lang['install']['homepage']; ?></strong>
		</p>
		<form method="post" action="">
			<label class="kop2" for="cont1"><?php echo $lang['general']['title']; ?></label>
			<br />
			<input name="cont1" id="cont1" type="text" />
			<br /><br />
			<label class="kop2" for="cont2"><?php echo $lang['general']['contents']; ?></label>
			<br />
			<textarea name="cont2" id="cont2" class="tinymce" cols="70" rows="20"></textarea>
			<br />
			<?php show_common_submits('?action=install3'); ?>
		</form>
		<?php
		include_once ('data/inc/footer.php');
	}

	//Installation Step 3: Save Installation data.
	elseif (isset($action) && $action == "install5") {
		install_done();

		//Set pagetitle
		$titelkop = $lang['install']['title'];
		include_once ('data/inc/header2.php');
		?>
			<p>
				<strong><?php echo $lang['install']['success']; ?></strong>
			</p>
		<?php
		showmenudiv($lang['start']['website'], $lang['start']['result'], 'data/image/website.png', 'index.php');
		showmenudiv($lang['general']['admin_center'], $lang['install']['manage'], 'data/image/password.png', 'login.php');

		include_once ('data/inc/footer.php');
	}
}
?>