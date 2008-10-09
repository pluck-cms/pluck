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
if((!ereg('index.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo 'access denied';
    //Block all other code
    exit();
}
?>

<p><strong><?php echo $lang_theme6; ?></strong></p>

<?php
if(!isset($_POST['Submit'])) {
?>
<div class="menudiv">
	<table>
		<tr>
			<td>
				<img src="data/image/install.png" alt="" />
			</td>
			<td>
				<form method="post" action="" enctype="multipart/form-data">
					<input type="file" name="sendfile" />
					<input type="submit" name="Submit" value="<?php echo $lang_image9; ?>" />
				</form>
			</td>
		</tr>
	</table>
</div>

<div class="menudiv">
	<table>
		<tr>
			<td>
				<img src="data/image/themes.png" alt="" />
			</td>
			<td>
				<span class="kop3"><a href="?action=theme">&lt;&lt;&lt; <?php echo $lang_theme12; ?></a></span>
			</td>
		</tr>
	</table>
</div>

<?php
}

if(isset($_POST['Submit'])) {
	//If no file has been sent
	if (!$_FILES['sendfile'])
		echo $lang_image2;

	else {
		//Some data
		$dir = 'data/themes'; //where we will save and extract the file
		$maxfilesize = 1000000; //max size of file
		$filename = $_FILES['sendfile']['name']; //determine filename

		//Check if we're dealing with a file with tar.gz in filename
		if(!ereg('.tar.gz', $filename))
			echo $lang_theme15;

		else {
			//Check if file isn't too big
			if ($_FILES['sendfile']['size'] > $maxfilesize)
				echo $lang_theme8;

			else {
				//Save theme-file
				copy($_FILES['sendfile']['tmp_name'], $dir.'/'.$filename) or die ($lang_image2);

				//Then load the library for extracting the tar.gz-file
				require_once('data/inc/lib/tarlib.class.php');

				//Load the tarfile
				$tar = new TarLib($dir.'/'.$filename);

				//And extract it
				$tar->Extract(FULL_ARCHIVE, $dir);
				//After extraction: delete the tar.gz-file
				unlink($dir.'/'.$filename);

				//Display successmessage
				?>
				<div class="menudiv">
					<table>
							<tr>
								<td>
									<img src="data/image/install.png" alt="" />
								</td>
								<td>
									<span class="kop3"><?php echo $lang_theme10; ?></span><br />
									<?php echo $lang_theme11; ?>
								</td>
							</tr>
					</table>
				</div>
				<?php		
			}
		}
	}
}
?>