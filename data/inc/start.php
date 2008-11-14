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
<p>
	<strong><?php echo $lang_start1; ?></strong>
	<br />
	<?php echo $lang_start9; ?>
</p>

<span class="kop2"><?php echo $lang_start10; ?></span>
<?php
//Show the divs
showmenudiv($lang_install20, $lang_install21, 'data/image/website.png', 'index.php', true);
showmenudiv($lang_credits, $lang_credits1, 'data/image/credits.png', '?action=credits');
showmenudiv($lang_start3, $lang_start4, 'data/image/hulp.png', 'http://www.pluck-cms.org/help.php', true);
?>