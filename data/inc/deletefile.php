<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) pluck team
 * http://www.pluck-cms.org

 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');

//Check if image exists.
if (file_exists('files/'.$var1)) {
	//First check if there isn't an item with the same name in the trashcan.
	if (!file_exists('data/trash/files/'.$var1)) {
		//Move the image to the trashcan.
		copy('files/'.$var1, 'data/trash/files/'.$var1);
		chmod('data/trash/files/'.$var1, 0777);
		unlink('files/'.$var1);

		//Redirect user.
		show_error($lang['trashcan']['moving_item'], 3);
		redirect('?action=files', 0);
	}

	//If there is an item with the same name in the trashcan: display error.
	else {
		show_error($lang['trashcan']['same_name'], 1);
		redirect('?action=files', 3);
	}
}
?>