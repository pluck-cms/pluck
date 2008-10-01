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

//Introduction text
?>
<p><strong><?php echo $lang_page1; ?></strong></p>
<?php
//New page button
showmenudiv($lang_page2, null, 'data/image/newpage.png', '?action=newpage', false);
//Manage images button
showmenudiv($lang_kop17, null, 'data/image/image.png', '?action=images', false);
//Show pages
read_pages();
?>