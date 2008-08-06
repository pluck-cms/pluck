<?php
//This is a module for pluck, an opensource content management system
//Website: http://www.pluck-cms.org

//MODULE NAME: blog
//DESCRIPTION: this module lets the user create an own blog
//LICENSE: GPLv3
//This module is included with pluck

//Include language-items
include ("data/settings/langpref.php");
include ("data/inc/lang/en.php");
include ("data/inc/lang/$langpref");

//Module name
$module_name = $lang_blog;

//Short module introduction
$module_intro = $lang_blog1;

//Module dir
$module_dir = "blog";

//Filename of the module-icon
$module_icon = "images/blog.png";

//Version of the module
$module_version = "0.1";

//Author of the module
$module_author = "Sander Thijsen";

//Website of the module
$module_website = "http://www.pluck-cms.org";

//We need TinyMCE!
$tinymce = "yes";

//Compatibility
$module_compatibility = "4.6";

?>