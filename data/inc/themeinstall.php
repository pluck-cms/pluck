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
?>
	<p>
		<strong><?php echo $lang_theme6; ?></strong>
	</p>
<?php
	if (!isset($_POST ['Submit'])) {
	?>
		<div class="menudiv" style="width: 500px;">
			<span>
				<img src="data/image/install.png" alt="" />
			</span>
			<div style="display: inline-block;">
				<form method="post" action="" enctype="multipart/form-data">
					<input type="file" name="sendfile" />
					<input type="submit" name="Submit" value="<?php echo $lang_image9; ?>" />
				</form>
			</div>
		</div>
		<div class="menudiv" style="width: 500px;">
			<span>
				<img src="data/image/themes.png" alt="" />
			</span>
			<span>
				<span class="kop3">
					<a href="?action=theme">&lt;&lt;&lt; <?php echo $lang_theme12; ?></a>
				</span>
			</span>
		</div>
	<?php
}

if (isset($_POST['Submit'])) {
	//If no file has been sent.
	if (!$_FILES['sendfile'])
		echo $lang_image2;

	else {
		//Some data.
		$dir = 'data/themes'; //Where we will save and extract the file.
		$maxfilesize = 1000000; //Max size of file.
		$filename = $_FILES ['sendfile'] ['name']; //Determine filename.

		//Check if we're dealing with a file with tar.gz in filename.
		if (!strpos($filename, '.tar.gz'))
			echo $lang_theme15;

		else {
			//Check if file isn't too big.
			if ($_FILES ['sendfile'] ['size'] > $maxfilesize)
				echo $lang_theme8;

			else {
				//Save theme-file.
				copy($_FILES ['sendfile'] ['tmp_name'], $dir.'/'.$filename) or die ($lang_image2);

				//Then load the library for extracting the tar.gz-file.
				require_once ('data/inc/lib/tarlib.class.php');

				//Load the tarfile.
				$tar = new TarLib($dir.'/'.$filename);

				//And extract it.
				$tar->Extract(FULL_ARCHIVE, $dir);
				//After extraction: delete the tar.gz-file.
				unlink($dir.'/'.$filename);

				//Display successmessage.
				?>
					<div class="menudiv">
						<span>
							<img src="data/image/install.png" alt="" />
						</span>
						<span>
							<span class="kop3"><?php echo $lang_theme10; ?></span>
							<br />
							<?php echo $lang_theme11; ?>
						</span>
					</div>
				<?php
			}
		}
	}
}
?>