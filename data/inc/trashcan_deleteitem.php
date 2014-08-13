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

//Page
if ($var2 == 'page' && file_exists('data/trash/pages/'.$var1.'.php'))
	unlink('data/trash/pages/'.$var1.'.php');

//Image
if ($var2 == 'image' && file_exists('data/trash/images/'.$var1))
	unlink('data/trash/images/'.$var1);

//File
if ($var2 == 'file' && file_exists('data/trash/files/'.$var1))
	unlink('data/trash/files/'.$var1);

//Redirect
show_error($lang['trashcan']['deleting'], 3);
redirect('?action=trashcan', 0);
?>