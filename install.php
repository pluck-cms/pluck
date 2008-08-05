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

//Include security-enhancements
require("data/inc/security.php");
//Include pluck version information
include("data/inc/pluck_info.php");
//Include functions
include("data/inc/functions.all.php");
//Include Translation data
include("data/inc/variables.all.php");

//Include POST/GET data
include ("data/inc/post_get.php");

//Check if we've installed pluck
require ("data/settings/install.dat");
//If we did:
if ($install == "yes") {
$titelkop = $lang_install;
include ("data/inc/header2.php");
echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"3; URL=login.php\">
$lang_install1"; 
include ("data/inc/footer.php"); }

//If we didn't:
elseif ($install=="no") {

if (!isset($action)) {
$titelkop = $lang_install;
include ("data/inc/header2.php");
//Introduction text
echo "<span class=\"kop2\">$lang_install</span><br>";
echo "<p><b>$lang_install2</b></p>";
//Show installation button
echo "<a href=\"?action=install\">$lang_install3</a>";
include ("data/inc/footer.php"); }

//Installation Step 1: CHMOD
if ($action == "install") {
$titelkop = $lang_install;
include ("data/inc/header2.php");
echo "<span class=\"kop2\">$lang_install :: $lang_install4</span><br>";
echo "<p><b>$lang_install7</b></p>";

//Writable checks
//-----------------

//First define the function
//---------------------------
function check_writable($file) {
	//Include Translation data
	include("data/inc/variables.all.php");
	if (is_writable($file)) {
		echo "<tr><td>/$file &nbsp;"; 
		echo "<td><img src=\"data/image/update-no.png\" width=\"15\" height=\"15\" alt=\"$lang_install8\"></td></tr>";
	}
	else {
		echo "<tr><td>/$file &nbsp;"; 
		echo "<td><img src=\"data/image/error.png\" width=\"15\" height=\"15\" alt=\"$lang_install9\"></td></tr>"; 
	}
}

echo "<table>";

check_writable("images");
check_writable("data/modules");
check_writable("data/settings");
check_writable("data/trash");
check_writable("data/themes");
check_writable("data/themes/default");
check_writable("data/themes/green");
check_writable("data/themes/oldstyle");
check_writable("data/settings/install.dat");
check_writable("data/settings/langpref.php");
check_writable("data/settings/themepref.php");

echo "</table><a href=\"javascript:refresh()\">$lang_install10</a><br><br>";
echo "<a href=\"?action=install2\"><b>$lang_install11</b></a>";
include ("data/inc/footer.php");
}

//Installation Step 2: General Info
if ($action == "install2") {

$titelkop = $lang_install;
include ("data/inc/header2.php");
echo "<span class=\"kop2\">$lang_install :: $lang_install5</span><br>";

echo "<p><b>$lang_install27</b></p>
<form method=\"post\" action=\"\">
<p><span class=\"kop2\">$lang_install17</span><br>
<span class=\"kop4\">$lang_settings2</span><br>
<input name=\"cont\" type=\"text\" value=\"\"><br>
<span class=\"kop2\">$lang_install24</span><br>
<span class=\"kop4\">$lang_install25</span><br>
<input name=\"email\" type=\"text\" value=\"\"></p>

<p><span class=\"kop2\">$lang_kop14</span><br>
<select name=\"chosen_lang\">
<option selected value=\"en.php\">English";

function read_dir($dir) {
$path = opendir($dir);
while (false !== ($file = readdir($path))) {
if(($file !== ".") and ($file !== "..") and ($file !== "en.php")) {
   if(is_file($dir."/".$file))
        $files[]=$file;
   else
        $dirs[]=$dir."/".$file;           
   }
}
if($dirs) {
echo "";
}
   if($files) {
       natcasesort($files);
       foreach ($files as $file) {
       	  include ("data/inc/lang/$file");
           echo "<option value=\"$file\">$lang"; }
   }
   closedir($path);
}
$path = "data/inc/lang";
read_dir($path);

echo "</select></p>
<p><span class=\"kop2\">$lang_login3</span><br>
<input name=\"password\" type=\"password\" value=\"\"><br>
<span class=\"kop2\">$lang_install26</span><br>
<input name=\"password2\" type=\"password\" value=\"\"></p>
<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\">
<input type=\"button\" name=\"Cancel\" value=\"$lang_install14\" onclick=\"javascript: window.location='?action=install';\">
</form>";

if(isset($_POST['Submit'])) {
//Check the passwords
if (($password != $password2) || ($password == "")) {
echo "<br><span style=\"color: red\">$lang_install28</span>";
include ("data/inc/footer.php");
exit; }

if ($chosen_lang == $lang_lang2 ) {
include ("data/inc/footer.php");
exit; }

//Save prefered language
$data1 = "data/settings/langpref.php";
$file = fopen($data1, "w");
fputs($file, "<?php \$langpref = \"$chosen_lang\"; ?>");
fclose($file);

//Save options
if (!$cont) {
echo $lang_install15;
include("data/inc/footer.php");
exit; }
$cont = stripslashes($cont);
$data2 = "data/settings/options.php";
$file = fopen($data2, "w");
fputs($file, "<?php
\$sitetitle = \"$cont\";
\$email = \"$email\";
\$xhtmlruleset = \"false\";
?>");
fclose($file);

//MD5-hash password
$password = md5($password);
//Save password
$data3 = "data/settings/pass.php";
$file = fopen($data3, "w");
fputs($file, "<?php \$ww = \"$password\"; ?>");
fclose($file);

//Make some dirs for the trashcan and modulesettings
mkdir("data/trash/pages", 0777);
chmod("data/trash/pages", 0777);
mkdir("data/trash/images", 0777);
chmod("data/trash/images", 0777);
mkdir("data/settings/modules", 0777);
chmod("data/settings/modules", 0777);
mkdir("data/settings/pages", 0777);
chmod("data/settings/pages", 0777);
mkdir("data/settings/modules/albums", 0777);
chmod("data/settings/modules/albums", 0777);
mkdir("data/settings/modules/blog", 0777);
chmod("data/settings/modules/blog", 0777);

echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"0; URL=?action=install4\">";
}
include ("data/inc/footer.php");
}

//Installation Step 4: Homepage
if ($action == "install4") {
$titelkop = $lang_install;
include ("data/inc/header2.php");
echo "<span class=\"kop2\">$lang_install :: $lang_install29</span><br>";
echo "<p><b>$lang_install16</b></p>";
echo "<form method=\"post\" action=\"\">
<span class=\"kop2\">$lang_install17</span><br>
<input name=\"cont1\" type=\"text\" value=\"\"><br><br>
<span class=\"kop2\">$lang_install18</span><br>
<textarea name=\"cont2\" class=\"tinymce\" cols=\"70\" rows=\"20\"></textarea><br>
<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\"><input type=\"button\" name=\"Cancel\" value=\"$lang_install14\" onclick=\"javascript: window.location='?action=install3';\">
</form>";
//Save the homepage
if(isset($_POST['Submit'])) {
$data = "data/settings/pages/kop1.php";   
$file = fopen($data, "w");
$cont1 = stripslashes($cont1);
$cont1 = str_replace("\"", "\\\"", $cont1);
$cont2 = stripslashes($cont2);
$cont2 = str_replace("\"", "\\\"", $cont2);
fputs($file, "<?php
\$title = \"$cont1\";
\$content = \"$cont2\";
\$hidden = \"no\";
?>");
fclose($file);
chmod($data, 0777);
echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"0; URL=?action=install5\">"; }
include ("data/inc/footer.php");
 }

//Installation Step 5: Save Installation data
if ($action == "install5") {
$data2 = "data/settings/install.dat";
$file2 = fopen($data2, "w");  
fputs($file2, "<?php
\$install = \"yes\";
?>");  
fclose($file2);

//Display success message
$titelkop = $lang_install;
include ("data/inc/header2.php");
echo "<p><b>$lang_install19</b>";

//Delete temporary packaging-files
unlink ("images/delete_me");
unlink ("data/trash/delete_me");

echo "<div style=\"background-color: #f4f4f4; border: 1px dotted gray; margin: 10px;\">
	<table>
		<tr>
			<td>
				<img src=\"data/image/website.png\" border=\"0\" alt=\"\">
			</td>
			<td>
				<span style=\"font-size: 17pt;\"><a href=\"index.php\">$lang_install20</a></span><br>
				$lang_install21
			</td>
		</tr>
	</table>
</div>
<div style=\"background-color: #f4f4f4; border: 1px dotted gray; margin: 10px;\">
	<table>
		<tr>
			<td>
				<img src=\"data/image/password.png\" border=\"0\" alt=\"\">
			</td>
			<td>
				<span style=\"font-size: 17pt;\"><a href=\"login.php\">$lang_install22</a></span><br>
				$lang_install23
			</td>
		</tr>
	</table>
</div>";
include ("data/inc/footer.php");
}
}
?>