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

//First set the charset: utf-8.
header('Content-Type:text/html;charset=utf-8');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>pluck 4.7 requirements check></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<link href="data/styleadmin.css" rel="stylesheet" type="text/css" media="screen" />
<link rel="icon" type="image/vnd.microsoft.icon" href="data/image/favicon.ico" />
</head>
<body>
<div id="menuheader">
	<h1>pluck</h1>
	<div id="menu2">
		<span class="menuitem2">requirements check</span>
	</div>
</div>
<div id="text">
	<?php
	//1: Check for the the required PHP version.
	if (version_compare(PHP_VERSION, '5.2.0') !== 1) {
		$messages[] = array(
			'text'  => 'pluck will only work with PHP <strong>5.2.0</strong> or higher.<br />Your version is: <strong>'.PHP_VERSION.'</strong>',
			'class' => 'error'
		);
		$error = true;
	}

	//2: Check for Zlib.
	if (!get_extension_funcs('zlib')) {
		$messages[] = array(
			'text'  => '<strong>Zlib</strong> is needed for installing themes and modules.<br />You can however do it manually with FTP.',
			'class' => 'error'
		);
		$error = true;
	}

	//3: Check for GD.
	if (!get_extension_funcs('gd')) {
		$messages[] = array(
			'text'  => '<strong>GD</strong> is needed for image manipulation.<br />You will not be able to use the albums module.',
			'class' => 'error'
		);
		$error = true;
	}

	//4: Check for CURL.
	if (!get_extension_funcs('curl')) {
		$messages[] = array(
			'text'  => '<strong>CURL</strong> is used to check for updates.<br />You will have to go to <a href="http://pluck-cms.org">http://pluck-cms.org</a> and check for updates.',
			'class' => 'error'
		);
		$error = true;
	}

	//5: Check for safe_mode.
	if (ini_get('safe_mode') === 1) {
		$messages[] = array(
			'text'  => '<strong>safe_mode</strong> is turned on.<br />pluck does not support Safe Mode, and we can\'t guarantee it will work.',
			'class' => 'notice'
		);
	}

	//6: Check for register_globals.
	if (ini_get('register_globals') === 1) {
		$messages[] = array(
			'text'  => '<strong>register_globals</strong> is turned on.<br />pluck works around it, but for safety and performance reasons, it should be off.',
			'class' => 'notice'
		);
	}

	//7: Check for magic_quotes_gpc.
	if (get_magic_quotes_gpc() === 1) {
		$messages[] = array(
			'text'  => '<strong>magic_quotes_gpc</strong> is turned on.<br />pluck does not use MySQL, so it should be turned off for performance reasons.',
			'class' => 'notice'
		);
	}

	//Are ther any messages?
	if (isset($messages)) {
		foreach ($messages as $message)
			echo '<span class="'.$message['class'].'">'.$message['text'].'</span><br />';

		//If there are just one error, pluck should not be installed.
		if (isset($error))
			echo '<br /><p><strong>All requirements are  NOT meet. You should NOT <a href="install.php">install pluck</a> untill the error(s) have been fixed.</strong></p>';
		
		//If there are only notices, pluck can be installed.
		else
			echo '<br /><p><strong>All requirements are meet, but you should try to fix the notice(s) before you <a href="install.php">install pluck</a>.</strong></p>';
	}

	//If everything is okay, pluck should be installed.
	else
		echo '<p><strong>All requirements and settings are meet. You can safely <a href="install.php">install pluck now</a>.</strong></p>';
	?>
	<div id="somp">pluck © 2005-2009 <a href="http://www.somp.nl" target="_blank">somp</a>. pluck is available under the terms of the GNU General Public License.</div>
</div>
</body>
</html>