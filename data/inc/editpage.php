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
defined('IN_PLUCK') or exit('Access denied!');

//Include page information, if we're editing a page.
if (isset($_GET['page']) && file_exists('data/settings/pages/'.get_page_filename($_GET['page'])))
	require_once ('data/settings/pages/'.get_page_filename($_GET['page']));

//If form is posted...
if (isset($_POST['save']) || isset($_POST['save_exit'])) {
	if (!isset($_POST['hidden']))
		$_POST['hidden'] = 'yes';

	//Save the page, but only if a title has been entered.
	if (!empty($_POST['title'])) {
		//If we are editing an existing page, also save description, keywords and pass current seo-name.
		if (isset($_GET['page'])) {
			if (!isset($description))
				$description = false;
			if (!isset($keywords))
				$keywords = false;
			$seoname = save_page($_POST['title'], $_POST['content'], $_POST['hidden'], $_POST['sub_page'], $description, $keywords, $_GET['page']);
		}
		//If we are creating a new page, save without those variables.
		else
			$seoname = save_page($_POST['title'], $_POST['content'], $_POST['hidden'], $_POST['sub_page']);
	}
	//If no title has been chosen, set error.
	else
		$error = show_error($lang['page']['no_title'], 1, true);

	//Redirect to the new title only if it is a plain save.
	if (isset($_POST['save']) && !isset($error)) {
		redirect('?action=editpage&page='.$seoname, 0);
		include_once ('data/inc/footer.php');
		exit;
	}

	//Redirect the user. only if they are doing a save_exit.
	elseif (isset($_POST['save_exit']) && !isset($error)) {
		redirect('?action=page', 0);
		include_once ('data/inc/footer.php');
		exit;
	}
}
?>
<div class="rightmenu">
<p><?php echo $lang['page']['items']; ?></p>
<?php
	show_module_insert_box();
	show_link_insert_box();
	show_image_insert_box('images');
?>
</div>
<?php
if (isset($error))
	echo $error;
?>
<form name="page_form" method="post" action="">
	<p>
		<label class="kop2" for="title"><?php echo $lang['general']['title']; ?></label>
		<input name="title" id="title" type="text" value="<?php if (isset($_GET['page'])) echo $title; ?>" />
	</p>
	<label class="kop2" for="content-form"><?php echo $lang['general']['contents']; ?></label>
	<textarea class="<?php if(defined('WYSIWYG_TEXTAREA_CLASS')) echo WYSIWYG_TEXTAREA_CLASS; ?>" name="content" id="content-form" cols="70" rows="20"><?php if (isset($_GET['page'])) echo htmlspecialchars($content); ?></textarea>

	<div class="menudiv" style="width: 588px; margin-<?php if (DIRECTION_RTL) echo 'right'; else echo 'left'; ?>: 0;">
		<p class="kop2"><?php echo $lang['general']['other_options']; ?></p>
		<p class="kop4" style="margin-bottom: 5px;"><?php echo $lang['page']['options']; ?></p>

		<input type="checkbox" name="hidden" id="hidden" <?php if (!isset($_GET['page']) || $hidden == 'no') echo'checked="checked"'; ?> value="no" />
		<label for="hidden"><?php echo $lang['page']['in_menu']; ?></label><br />

		<label for="sub_page"><?php echo $lang['page']['sub_page']; ?></label>
		<?php
		if (isset($_GET['page']))
			show_subpage_select('sub_page', $_GET['page']);
		else
			show_subpage_select('sub_page');
		?>
	</div>
	<?php show_common_submits('?action=page', true); ?>
</form>