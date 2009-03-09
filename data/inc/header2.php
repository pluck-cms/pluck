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

//Then set character encoding.
header('Content-Type:text/html;charset=utf-8');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo LANG; ?>" lang="<?php echo LANG; ?>">
<head>
<title>pluck <?php echo PLUCK_VERSION; ?> - <?php echo $titelkop; ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<?php
if (isset($direction) && $direction == 'rtl') {
	echo '<link href="data/styleadmin-rtl.css" rel="stylesheet" type="text/css" media="screen" />';
}
else {
	echo '<link href="data/styleadmin.css" rel="stylesheet" type="text/css" media="screen" />';
}

//Include TinyMCE, but not on the login page.
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'login.php'))
	require_once ('data/inc/tinymce_inc.php');
?>
<link rel="icon" type="image/vnd.microsoft.icon" href="data/image/favicon.ico" />
<meta name="robots" content="noindex" />
<script language="javascript" type="text/javascript">
<!--
function refresh() {
	window.location.reload(false);
}
//-->
</script>
<script language="javascript" type="text/javascript">
<!--
//Enter-listener.
if (document.layers)
	document.captureEvents(Event.KEYDOWN);
	document.onkeydown =
	function (evt) {
		var keyCode = evt ? (evt.which ? evt.which : evt.keyCode) : event.keyCode;
		if (keyCode == 13)   //13 = the code for pressing ENTER.
			document.form.submit();
	}
//-->
</script>
</head>

<body>
<div id="menuheader">
<h1>pluck</h1>
<div id="menu2">
	<span class="menuitem2"><?php echo $titelkop; ?></span>
</div>
</div>
<div id="text">