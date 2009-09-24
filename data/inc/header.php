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

//First set character encoding
header("Content-Type:text/html;charset=utf-8");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo LANG; ?>" lang="<?php echo LANG; ?>">
<head>
<title>pluck <?php echo PLUCK_VERSION.' '.$lang['general']['admin_center']; ?><?php if (isset($titelkop)) echo ' - '.$titelkop; ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<?php
//Check if we need rtl-direction
if (DIRECTION_RTL)
	echo '<link href="data/styleadmin-rtl.css" rel="stylesheet" type="text/css" media="screen" />';
else
	echo '<link href="data/styleadmin.css" rel="stylesheet" type="text/css" media="screen" />';

//Include TinyMCE.
require_once ('data/inc/tinymce_inc.php');
?>
<link rel="icon" type="image/vnd.microsoft.icon" href="data/image/favicon.ico" />
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

function confirmation(message) {
	return confirm(message);
}

function insert_page_link() {
	var id = document.getElementById('insert_pages');
	var page = id.selectedIndex;
	var file = id.options[page].value;
	var title = id.options[page].text;

	//Remove indent space.
	//@fixme Not the best way to do it, but it works.
	title = escape(title);
	title = title.replace(/%u2003/g, '');
	title = unescape(title);

	tinyMCE.execCommand('mceInsertContent', false, '<a href="index.php?file=' + file + '" title="' + title + '">' + title + '<\/a>');
}

function insert_image_link(dir) {
	var id = document.getElementById('insert_images');
	var image = id.selectedIndex;
	var file = id.options[image].text;

	tinyMCE.execCommand('mceInsertContent', false, '<img src="' + dir + '/' + file + '" alt="" \/>');
}
//-->
</script>
<?php run_hook('admin_header_main'); ?>
</head>
<body>
<div id="menuheader">
	<h1>pluck</h1>
	<?php run_hook('admin_menu_before'); ?>
	<?php
	$links = array(
		array(
			'href' => '?action=start',
			'img'  => 'data/image/menu/start.png',
			'text' => $lang['start']['title']
		),
		array(
			'href' => '?action=page',
			'img'  => 'data/image/menu/pages.png',
			'text' => $lang['page']['title']
		),
		array(
			'href' => '?action=modules',
			'img'  => 'data/image/menu/modules.png',
			'text' => $lang['modules']['title']
		),
		array(
			'href' => '?action=options',
			'img'  => 'data/image/menu/options.png',
			'text' => $lang['options']['title']
		),
		array(
			'href' => '?action=logout',
			'img'  => 'data/image/menu/logout.png',
			'text' => $lang['login']['log_out']
		)
	);
	run_hook('admin_menu', array(&$links));
	?>
	<ul id="menu">
		<?php
		foreach ($links as $link) {
			?>
				<li>
					<a href="<?php echo $link['href']; ?>" title="<?php echo $link['text']; ?>" <?php if (isset($link['target'])) echo 'target="'.$link['target'].'"'; ?>>
						<img src="<?php echo $link['img']; ?>" alt="" />
						<?php echo $link['text']; ?>
					</a>
				</li>
			<?php
		}
		?>
	</ul>
	<?php run_hook('admin_menu_after'); ?>
	<ul id="statusbox">
		<?php include_once ('data/inc/trashcan_applet.php'); ?>
		<?php include_once ('data/inc/update_applet.php'); ?>
	</ul>
</div>
<div id="text">
<?php if (isset($titelkop)): ?>
	<h2><?php echo $titelkop; ?></h2>
<?php endif; ?>