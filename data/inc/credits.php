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

//Make sure the file isn't accessed directly
if((!ereg('index.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo 'access denied';
    //Block all other code
    exit();
}
?>

<p>
	<strong><?php echo $lang_credits2; ?></strong>
</p>

<p>
	<span class="kop2"><?php echo $lang_credits3; ?></span><br />
	Sander Thijsen
</p>

<p>
	<span class="kop2"><?php echo $lang_credits6; ?></span><br />
	Sander Thijsen<br />
	Anders Jørgensen
</p>

<p>
	<span class="kop2"><?php echo $lang_credits7; ?></span><br />
	Kristaps Ancāns<br />
	Callan Barrett<br />
	Dennis Sewbarath
</p>

<?php
//Translation
//-----------
//First seek out who's the translator
if ($langpref == "nl.php") {
$translator = "Sander Thijsen"; }
if ($langpref == "da.php") {
$translator = "Lone Hansen"; }
if ($langpref == "de.php") {
$translator = "Max Effenberger, Dennis Sewberath, stoffal"; }
if ($langpref == "es.php") {
$translator = "Cesc"; }
if ($langpref == "ct.php") {
$translator = "Cesc"; }
if ($langpref == "fr.php") {
$translator = "zigzagbe, Dominique Heimler"; }
if ($langpref == "he.php") {
$translator = "Erez Wolf"; }
if ($langpref == "hu.php") {
$translator = "Wix"; }
if ($langpref == "lt.php") {
$translator = "Mindaugas Salamachinas"; }
if ($langpref == "no.php") {
$translator = "John Erik Kristensen"; }
if ($langpref == "pt.php") {
$translator = "Marco Paulo Ferreira, Hélio Carrasqueira"; }
if ($langpref == "pt_br.php") {
$translator = "Gilnei Moraes, sarkioja"; }
if ($langpref == "ru.php") {
$translator = "Tkachev Vasily"; }
if ($langpref == "sv.php") {
$translator = "Carl Jansson"; }
if ($langpref == "bg.php") {
$translator = "smartx"; }
if ($langpref == "th.php") {
$translator = "meandev"; }
if ($langpref == "lv.php") {
$translator = "Munky"; }
if ($langpref == "it.php") {
$translator = "Skc"; }
if ($langpref == "hr.php") {
$translator = "atghoust"; }
if ($langpref == "pl.php") {
$translator = "Leszek Soltys"; }
if ($langpref == "fa.php") {
$translator = "heam"; }
if ($langpref == "fi.php") {
$translator = "maxtuska"; }
if ($langpref == "sl.php") {
$translator = "Evelina"; }
if ($langpref == "sk.php") {
$translator = "greppi"; }

//Then display, if language is not English
if ($langpref != "en.php") {
?>
<p>
	<span class="kop2"><?php echo $lang_credits4.' ('.$lang.')'; ?></span><br />
	<?php echo $translator; ?>
</p>
<?php
}
?>

<p>
	<span class="kop2"><?php echo $lang_credits5; ?></span><br />
	<a href="http://tinymce.moxiecode.com" target="_blank">MoxieCode</a>, for making the excellent TinyMCE-editor used in pluck<br />
	<a href="http://www.phpconcept.net" target="_blank">PhpConcept</a>, for making PclTar, used in the automatic theme-installer<br />
	<a href="http://www.huddletogether.com/projects/lightbox2" target="_blank">Lokesh</a>, for developing LightBox2, used in pluck to serve the images in your albums with flair<br />
	<a href="http://www.justinbarkhuff.com/lab/lightbox_slideshow/" target="_blank">Justin Barkhuff</a>, for providing the slideshow feature for LightBox2<br />
	<a href="http://tango.freedesktop.org" target="_blank">The Tango Desktop Project</a>, for designing the wonderful icons used in the pluck administration center<br />
	<a href="http://www.famfamfam.com/lab/icons/silk/" target="_blank">Mark James</a>, for designing the "Silk" icons, also used in pluck 
</p>