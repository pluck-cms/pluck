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
?>

<p>
	<strong><?php echo $lang['modules_settings']['message']; ?></strong>
</p>

<form method="post" action="">
	<?php run_hook('admin_module_settings_beforepost'); ?>
	<?php show_common_submits('?action=options'); ?>
</form>

<?php
if (isset($_POST['save'])) {
	//Run hook and fetch errors (if any).
	$errors = run_hook('admin_module_settings_afterpost');

	//Show errors (if any).
	if (isset($errors)) {
		foreach ($errors as $error)
			echo $error;
	}
	else {
		show_error($lang['settings']['changing_settings'], 3);
		redirect('?action=options', 0);
	}
}
?>