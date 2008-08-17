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
if((!ereg("index.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("admin.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("install.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("login.php", $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo "access denied";
    //Block all other code
    exit();
}
?>

<p><b><?php echo $lang_modules20; ?></b></p>

<?php
if(!isset($_POST['Submit'])) { ?>

<div style="background-color: #f4f4f4; padding: 5px; width: 500px; margin-top: 15px; border: 1px dotted gray;">
	<table>
		<tr>
			<td>
				<img src="data/image/install.png" border="0" alt="" />
			</td>
			<td>
				<form method="post" action="" enctype="multipart/form-data">
					<input type="file" name="sendfile">
					<input type="submit" name="Submit" value="<?php echo $lang_image9; ?>">
				</form>
			</td>
		</tr>
	</table>
</div>

<div style="background-color: #f4f4f4; padding: 5px; width: 500px; margin-top: 15px; border: 1px dotted gray;">
	<table>
		<tr>
			<td>
				<img src="data/image/modules.png" border="0" alt="" />
			</td>
			<td>
				<span class="kop3"><a href="?action=managemodules"><<< <?php echo $lang_theme12; ?></a></span>
			</td>
		</tr>
	</table>
</div>

<?php
}

if(isset($_POST['Submit'])) {	//If no file has been sent
	if (!$_FILES['sendfile'])  
		echo $lang_image2; 
	
	else {
		//Some data
		$dir = "data/modules"; //where we will save and extract the file 
		$maxfilesize = "2000000"; //max size of file
		$filename = $_FILES['sendfile']['name']; //determine filename

		//Check if we're dealing with a file with tar.gz in filename
		if(!ereg(".tar.gz", $filename))
			echo $lang_theme15;
		else {

			//Check if file isn't too big
			if ($_FILES['sendfile']['size'] > $maxfilesize)  
				echo $lang_modules24;
			else {  
				//Save theme-file 
				copy ($_FILES['sendfile']['tmp_name'], "$dir/$filename")
				or die ("$lang_image2");

				//Then load the library for extracting the tar.gz-file
				require("data/inc/lib/tarlib.class.php");

				//Load the tarfile
				$tar = new TarLib("$dir/$filename");

				//And extract it
				$tar->Extract(FULL_ARCHIVE, $dir);
				//After extraction: delete the tar.gz-file
				unlink("$dir/$filename");
				
				//Make directory for module settings (if it doesn't exist)
				$dirtocreate = str_replace(".tar.gz","",$filename);
				if (!file_exists("data/settings/modules/$dirtocreate")) {
					mkdir("data/settings/modules/$dirtocreate",0777);
					chmod("data/settings/modules/$dirtocreate",0777);
				}

				//Display successmessage
				echo "<div style=\"background-color: #f4f4f4; padding: 5px; width: 300px; margin-top: 15px; border: 1px dotted gray;\">
							<table>
									<tr>
									<td>
										<img src=\"data/image/install.png\" border=\"0\" alt=\"\">
									</td>
									<td>
										<span class=\"kop3\">$lang_modules25</span><br />
										$lang_modules26
									</td>
									</tr>
							</table>
						</div>";
			}
		}
	}
}
?>