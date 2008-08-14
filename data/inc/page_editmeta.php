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

//Include the actual siteinfo
require ("data/settings/pages/$editmeta");

//Introduction text
echo "<p><b>$lang_meta2</b></p>

<form method=\"post\" action=\"\">

<span class=\"kop2\">$lang_albums11</span><br>
<textarea name=\"cont1\" rows=\"3\" cols=\"50\">$description</textarea><br><br>

<span class=\"kop2\">$lang_siteinfo4</span> ($lang_siteinfo5)<br>
<textarea name=\"cont2\" rows=\"5\" cols=\"50\">$keywords</textarea><br><br>

<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\">
<input type=\"button\" name=\"Cancel\" value=\"$lang_install14\" onclick=\"javascript: window.location='?action=page';\">
</form>";

if(isset($_POST['Submit'])) {
$data = "data/settings/pages/$editmeta";
include("data/inc/page_stripslashes.php");
  
$file = fopen($data, "w");  
fputs($file, "<?php 
\$title = \"$title\";
\$content = \"$content\";
\$hidden = \"$hidden\";\n");

//Only save other variables if they are set
if (!empty($cont1)) {
	fputs($file, "\$description = \"$cont1\";\n");
}
if (!empty($cont2)) {
	fputs($file, "\$keywords = \"$cont2\";\n");
}

//Save the module information
foreach ($module_pageinc as $modulename => $order) {
fputs($file, "\n\$module_pageinc['$modulename'] = \"$order\";");
}

fputs($file, "\n?>");
//Close file and chmod
fclose($file); 
chmod($data, 0777);

//Redirect user
echo $lang_meta4;
redirect('?action=page','0');} 
?>