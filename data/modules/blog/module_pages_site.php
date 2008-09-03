<?php
//This is a module for pluck, an opensource content management system
//Website: http://www.pluck-cms.org

//MODULE NAME: albums
//DESCRIPTION: this module lets the user create albums with JPEG-pictures to display on the website
//LICENSE: GPLv3
//This module is included with pluck


$includepage = 'blog_include.php';
//Only set 'view post'-page if a post has been specified
if (isset($_GET['post'])) {
	$module_page['viewpost'] = $_GET['post'];
}

?>