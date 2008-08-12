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

//Include functions
include("data/modules/blog/functions.php");
?>

<p><b><?php $lang_blog24; ?></b></p>

<div style="background-color: #f4f4f4; border: 1px dotted gray; margin: 10px; margin-bottom: 25px;">
<table>
	<tr>
		<td>
			<img src="data/image/newpage.png" border="0" alt="">
		</td>
		<td>
			<span style="font-size: 17pt;"><a href="?module=blog&page=newpost"><?php echo $lang_blog10; ?></a></span>
		</td>
	</tr>
</table>
</div>

<span class="kop2"><?php echo $lang_blog9; ?></span><br>

<span class="kop2"><?php echo $lang_blog3; ?></span><br>

<?php
//If there already are categories
if(file_exists("data/settings/modules/blog/categories.dat")) {
	//Load them
	$categories = file_get_contents("data/settings/modules/blog/categories.dat");
	//Then in an array
	$categories = split(",",$categories);
	
	//And show them
	//start table first
	echo "<table>";
	foreach($categories as $key => $name) {
		echo "<tr><td>$name &nbsp;"; 
		echo "<td><a href=\"?module=blog&page=deletecategory&var=$name\"><img src=\"data/image/trash_small.png\" width=\"16\" height=\"16\" alt=\"$lang_blog6\" title=\"$lang_blog6\" style=\"border:0px;\"></a></td></tr>";
	}
	echo "</table>";
}

//If no categories exist, show a message
else {
	echo "<span class=\"kop4\">$lang_albums14</span>";
}


//New category
?>
<br><span class="kop2"><?php echo $lang_blog4; ?></span><br>

<form method="post">
	<span class="kop4"><?php echo $lang_blog5; ?></span><br>
	<input name="cont1" type="text" value="">
	<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>">
</form>

<?php
//When form is submitted
if(isset($_POST['Submit'])) {
	if($cont1) {
		//Filter category name from inappropriate characters
		$cont1 = stripslashes($cont1);
		$cont1 = str_replace ("\"","", $cont1);
		$cont1 = str_replace ("'","", $cont1);
		$cont1 = str_replace (".","", $cont1);
		$cont1 = str_replace ("/","", $cont1);
		
		//Read out existing categories, if they exist
		if(file_exists("data/settings/modules/blog/categories.dat")) {
			$categories = file_get_contents("data/settings/modules/blog/categories.dat");
		}
		
		//Make sure category doesn't already exist
		if((!ereg($cont1,$categories)) || (!isset($categories))) {
			
			//If there are already existing categories...
			if(file_exists("data/settings/modules/blog/categories.dat")) {
				//Load existing categories in array
				$categories = split(",",$categories);

				//Determine the array number for our new category
				$num = 0; 
				while(isset($categories[$num])) {
					$num++;					
				}
				//Add new category to array
				$categories[$num] = $cont1;
			}

			//If there are no categories yet, just set new category in array
			else {
				$categories[0] = $cont1;
			}
				
			//Now, sort the array
			natsort($categories);
			//Reset keys of array
			$categories = array_merge(array(),$categories); 

			//Open config file to save categories
			$file = fopen("data/settings/modules/blog/categories.dat", "w");
			
			foreach($categories as $number => $name) {			
				$number_next = $number + 1;
				if(isset($categories[$number_next])) {
					fputs($file,"$name,");
				}
				else {
					fputs($file,"$name");
				}
			}
			//Close file, and chmod it
			fclose($file);
			chmod("data/settings/modules/blog/categories.dat", 0777);
		}
		//Redirect user
		redirect("?module=blog","0");
	}
}
?>

<p><a href="?action=modules"><<< <?php echo $lang_theme12; ?></a></p>