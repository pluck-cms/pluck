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

if (($cat == 'page') && (file_exists('data/trash/pages/'.$trash_viewitem))) {
	include('data/trash/pages/'.$trash_viewitem); ?>
	<div class="menudiv" style="padding:15px; margin: 25px;">
		<span class="kop2"><?php echo $title; ?></span><br>
		<?php echo $content; ?>
	</div>
<?php }

if (($cat == 'image') && (file_exists('data/trash/images/'.$trash_viewitem))) { ?>
		<img src="data/trash/images/<?php echo $trash_viewitem; ?>" alt="" />
<?php } ?>

<p><a href="?action=trashcan"><<< <?php echo $lang_theme12; ?></a></p>