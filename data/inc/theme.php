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

<div class="rightmenu">
<div style="background-color: #f4f4f4; padding: 5px; width: 300px; margin-top: 15px; border: 1px dotted gray;">
	<table>
		<tr>
			<td>
				<img src="data/image/install.png" border="0" alt="">
			</td>
			<td>
			<?php
				// if zlib is installed
				if (get_extension_funcs('zlib')) {
					echo '<span style="font-size: 17pt;"><a href="?action=themeinstall">'.$lang_theme5.'</a></span>';
				}
				// if zlib is not installed
				elseif (!get_extension_funcs('zlib')) {
					echo '<span class="kop3">'.$lang_theme5.'</span><br>';
					echo '<span style="color: red;">'.$lang_theme14.'</span>';
				}
			?></td>
		</tr>
	</table>
</div>
</div>
<p><b><?php echo $lang_theme1; ?></b></p>

<form action="" method="post">
	<select name="cont">
		<option value="0"><?php echo $lang_lang2; ?>

<?php
//Function to read out the themes
function read_dir($dir) {
	//Include theme information
	include('data/settings/themepref.php');

   $path = opendir($dir);
   while (false !== ($file = readdir($path))) {
       if(($file !== ".") and ($file !== "..") and ($file !== "themepref.php") and ($file !== "predefined_variables.php")) {
           if(is_file($dir."/".$file))
               $files[]=$file;
           else
               $dirs[]=$dir."/".$file;
       }
 	}
   if($dirs) {

       foreach ($dirs as $dir) {
			if (file_exists("$dir/info.php")) {
				include ("$dir/info.php");
				//If theme is current theme, select it
				if($themepref == $themedir) {
					echo '<option value="'.$themedir.'" selected>'.$themename."\n";
				}
				//Otherwise, don't select it
				else {
					echo '<option value="'.$themedir.'">'.$themename."\n";
				}
			}
       }
   }
   if($files) {

   }
   closedir($path);
}

//Actually read out the dir
read_dir("data/themes");
?>

	</select><br />
	<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>">
	<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=options';">
</form>

<?php
//Save the theme-data
if((isset($_POST['Submit'])) && ($cont != "0") && (file_exists("data/themes/$cont"))) {
	save_theme($cont);

	//Redirect user
	echo $lang_theme3;
	redirect('?action=options','2');
}
?>