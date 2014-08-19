<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) pluck team
 * http://www.pluck-cms.org

 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');
?>
<p>
	<strong><?php echo $lang['modules_install']['message']; ?></strong>
</p>
<div class="menudiv" style="display: inline-block; margin-top: 0;">
	<span>
		<img src="data/image/install.png" alt="" />
	</span>
	<form method="post" action="" enctype="multipart/form-data" style="display: inline-block;">
		<input type="file" name="sendfile" />
		<input type="submit" name="submit" value="<?php echo $lang['general']['upload']; ?>" />
	</form>
</div>
<p>
	<a href="?action=managemodules">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
</p>
<?php
if (isset($_POST ['submit'])) {
	//If no file has been sent.
	if (!$_FILES ['sendfile'])
		echo $lang['general']['upload_failed'];

	else {
		//Some data
		$dir = 'data/modules'; //Where we will save and extract the file.
		$maxfilesize = 2000000; //Max size of file.
		$filename = $_FILES ['sendfile'] ['name']; //Determine filename.

		//Check if we're dealing with a file with tar.gz in filename.
		if (!strpos($filename, '.tar.gz') && !strpos($filename, '.zip'))
			show_error($lang['general']['not_valid_file'], 1);

		else {
			//Check if file isn't too big.
			if ($_FILES['sendfile']['size'] > $maxfilesize)
				show_error($lang['modules_install']['too_big'], 1);

			else {
				//Save module-file.
				copy($_FILES['sendfile']['tmp_name'], $dir.'/'.$filename) or die ($lang['general']['upload_failed']);

				if (strpos($filename, '.tar.gz')) {
					//Then load the library for extracting the tar.gz-file.
					require_once ('data/inc/lib/tarlib.class.php');

					//Load the tarfile.
					$tar = new TarLib($dir.'/'.$filename);

					//And extract it.
					$tar->Extract(FULL_ARCHIVE, $dir);
					//After extraction: delete the tar.gz-file.
					unlink($dir.'/'.$filename);
					$dirtocreate = str_replace('.tar.gz', '', $filename);
				}
				else { //if not tar.gz then this file must be zip
					//Then load the library for extracting the zip-file.
					require_once ('data/inc/lib/unzip.class.php');

					//Load the zipfile.
					$zip=new UnZIP($dir.'/'.$filename);
					//And extract it.
					$zip->extract();

					//After extraction: delete the zip-file.
					unlink($dir.'/'.$filename);

					$dirtocreate = str_replace('.zip', '', $filename);
					//If there is a subdirectory like: module/module
					if (is_dir('data/modules/'.$dirtocreate.'/'.$dirtocreate) && !read_dir_contents('data/modules/'.$dirtocreate, 'files')) {
						rename('data/modules/'.$dirtocreate, 'data/modules/_157#93_');
						rename('data/modules/_157#93_/'.$dirtocreate, 'data/modules/'.$dirtocreate);
						rmdir('data/modules/_157#93_');
					}
				}
				//Make directory for module settings (if it doesn't exist).
				if (!file_exists('data/settings/modules/'.$dirtocreate)) {
					mkdir('data/settings/modules/'.$dirtocreate);
					chmod('data/settings/modules/'.$dirtocreate, 0777);
				}

				//Display success message.
				show_error($lang['modules_install']['success'], 3);
				redirect('?action=managemodules', 2);
			}
		}
	}
}
?>