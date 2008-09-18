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

//Include security-enhancements
require('data/inc/security.php');
//Include functions
require('data/inc/functions.all.php');
//Include Translation data
require('data/inc/variables.all.php');

//Include POST/GET data
require('data/inc/post_get.php');

//Check if we've installed pluck
if (file_exists('data/settings/install.dat')) {
	$titelkop = $lang_install;
	include('data/inc/header2.php');
	redirect('login.php', '3');
	echo $lang_install1;
	include('data/inc/footer.php');
}

//If we didn't:
else {
	if (!isset($action)) {
		$titelkop = $lang_install;
		include('data/inc/header2.php');
		//Introduction text
		?>
		<span class="kop2"><?php echo $lang_install; ?></span><br />
		<p><strong><?php echo $lang_install2; ?></strong></p>
		<?php
		//Show installation button
		?>
		<a href="?action=install"><?php echo $lang_install3; ?></a>
		<?php
		include('data/inc/footer.php');
	}

	//Installation Step 1: CHMOD
	if ($action == 'install') {
		$titelkop = $lang_install;
		include ('data/inc/header2.php');
		?>
		<span class="kop2"><?php echo $lang_install; ?> :: <?php echo $lang_install4; ?></span><br />
		<p><strong><?php echo $lang_install7; ?></strong></p>
		<?php
		//Writable checks
		//-----------------

		//First define the function
		//---------------------------
		function check_writable($file) {
			//Include Translation data
			if (is_writable($file)) {
			?>
				<span>
					<img src="data/image/update-no.png" width="15" height="15" alt="<?php echo $lang_install8; ?>" />
				</span>
				<span>&nbsp;/<?php echo $file; ?></span>
				<br />
			<?php
			}
			else {
			?>
				<span>
					<img src="data/image/error.png" width="15" height="15" alt="<?php echo $lang_install9; ?>" />
				</span>
				<span>&nbsp;/<?php echo $file; ?></span>
				<br />
			<?php
			}
		}

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
		<a href="?action=install2"><strong><?php echo $lang_install11; ?></strong></a>
		<?php
		include ('data/inc/footer.php');
	}

	//Installation Step 2: General Info
	if ($action == 'install2') {
		$titelkop = $lang_install;
		include ('data/inc/header2.php');
		?>
		<span class="kop2"><?php echo $lang_install; ?> :: <?php echo $lang_install5; ?></span><br />
		<p><strong><?php echo $lang_install27 ?></strong></p>
		<form method="post" action="">
			<p>
				<span class="kop2"><?php echo $lang_install17 ?></span><br />
				<span class="kop4"><?php echo $lang_settings2 ?></span><br />
				<input name="cont" type="text" /><br />
				<span class="kop2"><?php echo $lang_install24 ?></span><br />
				<span class="kop4"><?php echo $lang_install25 ?></span><br />
				<input name="email" type="text" />
			</p>
			<p>
				<span class="kop2"><?php echo $lang_kop14 ?></span><br />
				<select name="chosen_lang">
					<option selected="selected" value="en.php">English</option>
					<?php 
						$files = read_files('data/inc/lang');
						
						//Read the available languages
						if($files) {
							natcasesort($files);
							foreach ($files as $file) {
								if ($file != 'en.php') {
									//FIXME: Big problem here! We need a list with the languages,
									//because the last languages file will be used in the rest of the instalation. (It's Thai ATM, look at the password fields.)
									include ('data/inc/lang/' . $file . '');
									?>
									<option value='<?php echo $file; ?>'><?php echo $lang; ?></option>
									<?php
								}
							}
						}
					?>
				</select>
			</p>
			<p>
				<span class="kop2"><?php echo $lang_login3 ?></span><br />
				<input name="password" type="password" /><br />
				<span class="kop2"><?php echo $lang_install26 ?></span><br />
				<input name="password2" type="password" />
			</p>
			<input type="submit" name="Submit" value="<?php echo $lang_install13 ?>" />
			<input type="button" name="Cancel" value="<?php echo $lang_install14 ?>" onclick="javascript: window.location='?action=install';" />
		</form>
		<?php
		if(isset($_POST['Submit'])) {
			//Check the passwords
			if (($password != $password2) || ($password == "")) {
				?>
				<br /><span class="red"><?php echo $lang_install28; ?></span>
				<?php
				include('data/inc/footer.php');
			exit;
			}

			//Save prefered language
			$data1 = 'data/settings/langpref.php';
			$file = fopen($data1, 'w');
			fputs($file, '<?php $langpref = "' . $chosen_lang . '"; ?>');
			fclose($file);

			//Save options
			if (!$cont) {
				echo $lang_install15;
				include('data/inc/footer.php');
				exit;
			}
			$cont = stripslashes($cont);
			$data2 = 'data/settings/options.php';
			$file = fopen($data2, 'w');
			fputs($file, '<?php'."\n"
			.'$sitetitle = "' . $cont . '";'."\n"
			.'$email = "' . $email . '";'."\n"
			.'$xhtmlruleset = "false";'."\n"
			.'?>');
			fclose($file);
			chmod($data2,0777);

			//MD5-hash password
			$password = md5($password);
			//Save password
			$data3 = 'data/settings/pass.php';
			$file = fopen($data3, 'w');
			fputs($file, '<?php $ww = "' . $password . '"; ?>');
			fclose($file);
			chmod($data3,0777);

			//Make some dirs for the trashcan and modulesettings
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

			redirect('?action=install4', '0');
		}
		include('data/inc/footer.php');
	}

	//Installation Step 4: Homepage
	if ($action == 'install4') {
		$titelkop = $lang_install;
		include('data/inc/header2.php');
		?>
		<span class="kop2"><?php echo $lang_install; ?> :: <?php echo $lang_install29; ?></span><br />
		<p><strong><?php echo $lang_install16; ?></strong></p>
		<form method="post" action="">
			<span class="kop2"><?php echo $lang_install17; ?></span><br />
			<input name="cont1" type="text" /><br /><br />
			<span class="kop2"><?php echo $lang_install18; ?></span><br />
			<textarea name="cont2" class="tinymce" cols="70" rows="20"></textarea><br />
			<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
			<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=install3';" />
		</form>
		<?php
		//Save the homepage
		if(isset($_POST['Submit'])) {
			$data = 'data/settings/pages/kop1.php';   
			$file = fopen($data, 'w');
			$cont1 = stripslashes($cont1);
			$cont1 = str_replace('"', '\"', $cont1);
			$cont2 = stripslashes($cont2);
			$cont2 = str_replace('"', '\"', $cont2);
			fputs($file, '<?php'."\n"
			.'$title = "' . $cont1 . '";'."\n"
			.'$content = "' . $cont2 . '";'."\n"
			.'$hidden = "no";'."\n"
			.'?>');
			fclose($file);
			chmod($data, 0777);

			redirect('?action=install5', '0');
		}
		include('data/inc/footer.php');
	}

	//Installation Step 5: Save Installation data
	if ($action == "install5") {
		$data2 = 'data/settings/install.dat';
		$file2 = fopen($data2, 'w');  
		fputs($file2, '');
		fclose($file2);
		chmod($data2,0777);

		//Display success message
		$titelkop = $lang_install;
		include ('data/inc/header2.php');
		?>
		<p><strong><?php echo $lang_install19; ?></strong>
		<div class="menudiv">
			<span>
				<img src="data/image/website.png" alt="" />
			</span>
			<span>
				<span><a href="index.php"><?php echo $lang_install20; ?></a></span><br />
				<?php echo $lang_install21; ?>
			</span>
		</div>
		<div class="menudiv">
			<span>
				<img src="data/image/password.png" alt="" />
			</span>
			<span>
				<span><a href="login.php"><?php echo $lang_install22; ?></a></span><br />
				<?php echo $lang_install23; ?>
			</span>
		</div>
		<?php
		include('data/inc/footer.php');
	}
}
?>