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
//TODO: This is a faster version with strpos, and should replace the old one with ereg in all the other files.
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit();
}

//First set character encoding
header("Content-Type:text/html;charset=utf-8");

//And include the user-specified settings
require_once ("data/settings/options.php");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo LANG; ?>" lang="<?php echo LANG; ?>">
<head>
<title>pluck <?php echo PLUCK_VERSION.' '.$lang_install22.' - '.$titelkop; ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<?php
//Check if we need rtl-direction
if (isset($direction) && $direction == "rtl")
	echo '<link href="data/styleadmin-rtl.css" rel="stylesheet" type="text/css" media="screen" />';
else
	echo '<link href="data/styleadmin.css" rel="stylesheet" type="text/css" media="screen" />';

//Include TinyMCE, if needed
if (isset($tinymce) && $tinymce == 'yes') {
	include_once ('data/inc/tinymce_inc.php');
}
?>
<meta name="robots" content="noindex" />
<script type="text/javascript">
<!--
function kadabra(zap) {
	if (document.getElementById) {
		var abra = document.getElementById(zap).style;
		if (abra.display == 'block')
			abra.display = 'none';
		else
			abra.display = 'block';
		return false;
	}
	else
		return true;
}
//-->
</script>
<script type="text/javascript">
<!--
function confirmation(message) {
	return confirm(message);
}
//-->
</script>
<?php run_hook('admin_header_main'); ?>
</head>
<body>
<div id="menuheader">
	<div id="statusbox">
		<?php include('data/inc/trashcan_applet.php'); ?>
		<?php include('data/inc/update_applet.php'); ?>
	</div>
	<h1>pluck</h1>
	<?php run_hook('admin_menu_outside_before'); ?>
	<div id="menu">
		<?php run_hook('admin_menu_inside_before'); ?>
		<div class="menuitem">
			<span>
				<img src="data/image/menu/start.png" alt="" />
				<a href="?action=start" title="<?php echo $lang_kop1; ?>"><?php echo $lang_kop1; ?></a>
			</span>
		</div>
		<div class="menuitem">
			<span>
				<img src="data/image/menu/pages.png" alt="" />
				<a href="?action=page" title="<?php echo $lang_kop2; ?>"><?php echo $lang_kop2; ?></a>
			</span>
		</div>
		<div class="menuitem">
			<span>
				<img src="data/image/menu/modules.png" alt="" />
				<a href="?action=modules" title="<?php echo $lang_modules; ?>"><?php echo $lang_modules; ?></a>
			</span>
		</div>
		<div class="menuitem">
			<span>
				<img src="data/image/menu/options.png" alt="" />
				<a href="?action=options" title="<?php echo $lang_kop4; ?>"><?php echo $lang_kop4; ?></a>
			</span>
		</div>
		<div class="menuitem">
			<span>
				<img src="data/image/menu/logout.png" alt="" />
				<a href="?action=logout" title="<?php echo $lang_kop5; ?>"><?php echo $lang_kop5; ?></a>
			</span>
		</div>
		<?php run_hook('admin_menu_inside_after'); ?>
	</div>
	<?php run_hook('admin_menu_outside_after'); ?>
</div>
<div id="text">
<h2><?php echo $titelkop; ?></h2>