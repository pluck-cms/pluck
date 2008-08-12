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

//Introduction text
echo "<p><b>$lang_lang1</b></p>";

echo "<form action=\"\" method=\"post\">
<select name=\"cont\">
        <option selected value=\"0\">$lang_lang2";

//Function to read out the languagefiles
function read_dir($dir) {
   $path = opendir($dir);
   while (false !== ($file = readdir($path))) {
       if(($file !== ".") and ($file !== "..") and ($file !== "langpref.php")) {
           if(is_file($dir."/".$file))
               $files[]=$file;
           else
               $dirs[]=$dir."/".$file;           
       }
   }
   if($dirs) {
   }
   if($files) {
       natcasesort($files);
       foreach ($files as $file) {
       	  include ("lang/$file");
           echo "<option value=\"$file\">$lang"; }
   }
   closedir($path);
}

//Actually read out the dir
read_dir("data/inc/lang");
echo "</select><br>
<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\">
<input type=\"button\" name=\"Cancel\" value=\"$lang_install14\" onclick=\"javascript: window.location='?action=options';\">
</form>";

//Check if chosen language is valid, and then save data
if((isset($_POST['Submit'])) && ($cont != "0") && (file_exists("data/inc/lang/$cont"))) {
	$data = "data/settings/langpref.php";    
	$file = fopen($data, "w");  
	fputs($file, "<?php \$langpref = \"$cont\"; ?>");  
	fclose($file);
	chmod($data,0777);
	echo "$lang_lang3 <META HTTP-EQUIV=\"REFRESH\" CONTENT=\"1; URL=?action=options\">";
}
?>