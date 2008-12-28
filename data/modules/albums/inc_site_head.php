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
	exit();
}

global $module;

//Only insert LightBox when we're viewing an album
if (isset($module) && $module == 'albums') {
?>
	<link href="data/inc/lightbox/lightbox.css" rel="stylesheet" type="text/css" media="screen" />
	<script src="data/inc/lightbox/prototype.js" type="text/javascript"></script>
	<script src="data/inc/lightbox/scriptaculous.js?load=effects" type="text/javascript"></script>
	<script src="data/inc/lightbox/lightbox.js" type="text/javascript"></script>
<?php
}
?>