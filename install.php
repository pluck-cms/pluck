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
require_once ('data/inc/functions.all.php');
require_once ('data/inc/functions.admin.php');
//Include Translation data.
require_once ('data/inc/variables.all.php');

//Include POST/GET data.
require_once ('data/inc/post_get.php');

//Check if we've installed pluck
if (file_exists('data/settings/install.dat')) {
	$titelkop = $lang_install;
	include_once ('data/inc/header2.php');
	redirect('login.php', 3);
	echo $lang_install1;
	include_once ('data/inc/footer.php');
}

//If we didn't:
else {
	if (!isset($action)) {
		$titelkop = $lang_install;
		include_once ('data/inc/header2.php');
		//Introduction text.
		?>
		<span class="kop2"><?php echo $lang_install; ?></span>
		<br />
		<p>
			<strong><?php echo $lang_install2; ?></strong>
		</p>
		<?php
		//Show installation button.
		?>
		<a href="?action=install"><?php echo $lang_install3; ?></a>
		<?php
		include_once ('data/inc/footer.php');
	}

	//Installation Step 1: CHMOD.
	if ($action == 'install') {
		$titelkop = $lang_install;
		include_once ('data/inc/header2.php');
		?>
		<span class="kop2"><?php echo $lang_install; ?> :: <?php echo $lang_install4; ?></span>
		<br />
		<p>
			<strong><?php echo $lang_install7; ?></strong>
		</p>
		<?php
			//Writable checks.
			check_writable('images');
			check_writable('data/modules');
			check_writable('data/settings');
			check_writable('data/trash');
			check_writable('data/themes');
			check_writable('data/themes/default');
			check_writable('data/themes/green');
			check_writable('data/themes/oldstyle');
			check_writable('data/settings/langpref.php');
			check_writable('data/settings/themepref.php');
		?>
		<a href="javascript:refresh()"><?php echo $lang_install10; ?></a>
		<br /><br />
		<a href="?action=install2">
			<strong><?php echo $lang_install11; ?></strong>
		</a>
		<?php
		include_once ('data/inc/footer.php');
	}

	//Installation Step 2: General Info.
	if ($action == 'install2') {
		$titelkop = $lang_install;
		include_once ('data/inc/header2.php');
		?>
		<span class="kop2"><?php echo $lang_install; ?> :: <?php echo $lang_install5; ?></span>
		<br />
		<p>
			<strong><?php echo $lang_install27 ?></strong>
		</p>
		<form method="post" action="">
			<p>
				<label class="kop2" for="cont"><?php echo $lang_install17 ?></label>
				<br />
				<span class="kop4"><?php echo $lang_settings2 ?></span>
				<br />
				<input name="cont" id="cont" type="text" value="<?php echo htmlentities($_POST['cont']) ?>"/>
				<br />
				<label class="kop2" for="email"><?php echo $lang_install24 ?></label>
				<br />
				<span class="kop4"><?php echo $lang_install25 ?></span>
				<br />
				<input name="email" id="email"type="text" value="<?php echo htmlentities($_POST['email']) ?>" />
			</p>
			<p>
				<label class="kop2" for="chosen_lang"><?php echo $lang_kop14 ?></label>
				<br />
				<select name="chosen_lang" id="chosen_lang">
					<option selected="selected" value="en.php">English</option>
					<?php read_lang_files('en.php'); ?>
				</select>
			</p>
			<p>
				<label class="kop2" for="password"><?php echo $lang_login3 ?></label>
				<br />
				<input name="password" id="password" type="password" />
				<br /><br />
				<label class="kop2" for="password2"><?php echo $lang_install26 ?></label>
				<br />
				<input name="password2" id="password2" type="password" />
			</p>
			<input type="submit" name="Submit" value="<?php echo $lang_install13 ?>" />
			<input type="button" name="Cancel" value="<?php echo $lang_install14 ?>" onclick="javascript: window.location='?action=install';" />
		</form>
		<?php
		if (isset($_POST['Submit'])) {
			//Check the passwords.
			if (($password != $password2) || ($password == '')) {
				?>
				<br />
				<span class="red"><?php echo $lang_install28; ?></span>
				<?php
				include_once ('data/inc/footer.php');
				exit;
			}
			
			//Check sitetitle.
			if (!$cont) {
				?>
				<br />
				<span class="red"><?php echo $lang_install15; ?></span>
				<?php
				include_once ('data/inc/footer.php');
				exit;
			}
			
			//Save prefered language.
			save_language($chosen_lang);
			
			//Save options.
			save_options($cont, $email, false);
			
			//Save password.
			save_password($password);
			
			//Make some dirs for the trashcan and modulesettings.
			mkdir('data/trash/pages', 0777);
			chmod('data/trash/pages', 0777);
			mkdir('data/trash/images', 0777);
			chmod('data/trash/images', 0777);
			mkdir('data/settings/modules', 0777);
			chmod('data/settings/modules', 0777);
			mkdir('data/settings/pages', 0777);
			chmod('data/settings/pages', 0777);
			mkdir('data/settings/modules/albums', 0777);
			chmod('data/settings/modules/albums', 0777);
			mkdir('data/settings/modules/blog', 0777);
			chmod('data/settings/modules/blog', 0777);

			redirect('?action=install4', 0);
		}
		include_once ('data/inc/footer.php');
	}

	//Installation Step 4: Homepage.
	if ($action == 'install4') {
		$titelkop = $lang_install;
		include_once ('data/inc/header2.php');
		?>
		<span class="kop2"><?php echo $lang_install; ?> :: <?php echo $lang_install29; ?></span>
		<br />
		<p>
			<strong><?php echo $lang_install16; ?></strong>
		</p>
		<form method="post" action="">
			<label class="kop2" for="cont1"><?php echo $lang_install17; ?></label>
			<br />
			<input name="cont1" id="cont1" type="text" />
			<br /><br />
			<label class="kop2" for="cont2"><?php echo $lang_install18; ?></label>
			<br />
			<textarea name="cont2" id="cont2" class="tinymce" cols="70" rows="20"></textarea>
			<br />
			<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
			<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=install3';" />
		</form>
		<?php
		//Save the homepage.
		if (isset($_POST['Submit'])) {
			save_page('kop1', $cont1, $cont2, false);
			redirect('?action=install5', 0);
		}
		include_once ('data/inc/footer.php');
	}

	//Installation Step 5: Save Installation data.
	if ($action == "install5") {
		install_done();

		//Display success message.
		$titelkop = $lang_install;
		include_once ('data/inc/header2.php');
		
		showmenudiv($lang_install20, $lang_install21, 'data/image/website.png', 'index.php', false, null);
		showmenudiv($lang_install22, $lang_install23, 'data/image/password.png', 'login.php', false, null);
		
		include_once ('data/inc/footer.php');
	}
}
?>