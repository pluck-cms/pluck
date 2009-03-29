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

//Make sure the file isn't accessed directly.
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit;
}

if ($var2 == 'page' && file_exists('data/trash/pages/'.$var1.'.php')) {
	include ('data/trash/pages/'.$var1.'.php');
	?>
		<div class="menudiv" style="padding: 15px; margin: 25px;">
			<span class="kop2"><?php echo $title; ?></span>
			<br />
			<?php echo $content; ?>
		</div>
	<?php
}

if ($var2 == 'image' && file_exists('data/trash/images/'.$var1)) {
?>
	<img src="data/trash/images/<?php echo $var1; ?>" alt="" />
<?php
}
?>
<p>
	<a href="?action=trashcan">&lt;&lt;&lt; <?php echo $lang['general']['back']; ?></a>
</p>