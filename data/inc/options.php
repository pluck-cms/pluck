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
?>
<p><strong><?php echo $lang_options1; ?></strong></p>
<?php
//Show the divs
showmenudiv($lang_settings,$lang_settings3,'page.png','?action=settings','false');
showmenudiv($lang_modules3,$lang_modules4,'modules.png','?action=managemodules','false');
showmenudiv($lang_kop16,$lang_options3,'themes.png','?action=theme','false');
showmenudiv($lang_kop14,$lang_options8,'language.png','?action=language','false');
showmenudiv($lang_kop10,$lang_options5,'password.png','?action=changepass','false');
?>