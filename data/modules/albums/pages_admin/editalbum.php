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
if((!ereg("index.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("admin.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("install.php", $_SERVER['SCRIPT_FILENAME'])) && (!ereg("login.php", $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo "access denied";
    //Block all other code
    exit();
}

//First, load the functions
include("data/modules/albums/functions.php");

//Check if album exists
if (file_exists("data/settings/modules/albums/$var")) {

//Introduction text
echo "<p><b>$lang_albums8</b></p>";

//Edit images
echo "<span class=\"kop2\">$lang_albums9</span><br>";
read_albumimages("data/settings/modules/albums/$var");

//New images upload
echo "<p><span class=\"kop2\">$lang_albums10</span><br>
<span class=\"kop4\">$lang_albums13</span></p>
<form name=\"form1\" method=\"post\" action=\"\" enctype=\"multipart/form-data\">

<b>$lang_install17</b><br>
<input name=\"cont1\" type=\"text\" value=\"\"><br>
<b>$lang_albums11</b><br>
<textarea cols=\"50\" rows=\"5\" name=\"cont2\"></textarea><br>

<br><input type=\"file\" name=\"imagefile\">
<br><b>$lang_albums12</b> <input name=\"quality\" type=\"text\" size=\"3\" value=\"85\"><br>
<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\"></form>";

//Let's process the image...
if(isset($_POST['Submit'])) {

//Define some variables
$imageme = $_FILES['imagefile']['name'];
list($imageze, $ext) = explode(".", $imageme);
$fullimage = "data/settings/modules/albums/$var/$imageme";
$thumbimage = "data/settings/modules/albums/$var/thumb/$imageme";
$tempimage = "data/settings/modules/albums/$var/temp.jpg";

//First: upload the image
//If file is pjpeg or jpeg: accept
if (($_FILES['imagefile']['type'] == "image/jpeg") || ($_FILES['imagefile']['type'] == "image/pjpeg")) {
copy ($_FILES['imagefile']['tmp_name'], "data/settings/modules/albums/$var/".$_FILES['imagefile']['name'])
	or die ("error: upload failed");

//If the extension is with capitals, we have to rename it...
if ($ext == "JPG") {
rename($fullimage, "data/settings/modules/albums/$var/$imageze.jpg");
$fullimage = "data/settings/modules/albums/$var/$imageze.jpg";
$thumbimage = "data/settings/modules/albums/$var/thumb/$imageze.jpg"; 
	}

//Define which filenames are already in use, and define what filename we should use
if (file_exists("data/settings/modules/albums/$var/image1.jpg")) {
$i = 2;
$o = 3;
while ((file_exists("data/settings/modules/albums/$var/image$i.jpg")) || (file_exists("data/settings/modules/albums/$var/image$o.jpg"))) {
$i = $i+1;
$o = $o+1; }
$newfile = "image$i"; }
else {
$newfile = "image1"; }

//Then rename the file and give it the right filename, also define new image-variables
rename($fullimage, "data/settings/modules/albums/$var/$newfile.jpg");
$fullimage = "data/settings/modules/albums/$var/$newfile.jpg";
$thumbimage = "data/settings/modules/albums/$var/thumb/$newfile.jpg"; 
}

//Block images other then JPG
else {
echo "<p><span style=\"color: red;\">$lang_image2</span></p>";
include("data/inc/footer.php");
exit; }

//Copy the image to the thumbdir
copy ("$fullimage", "$thumbimage")
	or die ("error: thumb copying failed");

//Compress the big image
$ThumbWidth = 640;
           list($width, $height) = getimagesize("$fullimage");
           $imgratio=$width/$height;
           if ($imgratio>1){
              $newwidth = $ThumbWidth;
              $newheight = $ThumbWidth/$imgratio;
           } 
           else {
                 $newheight = $ThumbWidth;
                 $newwidth = $ThumbWidth*$imgratio;
           }        
           
           $resized_img = imagecreatetruecolor($newwidth,$newheight);
			  $new_img = imagecreatefromjpeg($fullimage);           
           
           imagecopyresampled($resized_img, $new_img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

           ImageJpeg ($resized_img,$tempimage, $quality);
           ImageDestroy ($resized_img);
           ImageDestroy ($new_img);
           unlink("$fullimage");
           rename("$tempimage", "$fullimage");
           
//Then make a thumb from the image
$ThumbWidth = 200;
           list($width, $height) = getimagesize("$thumbimage");
           $imgratio=$width/$height;
           if ($imgratio>1){
              $newwidth = $ThumbWidth;
              $newheight = $ThumbWidth/$imgratio;
           } 
           else {
                 $newheight = $ThumbWidth;
                 $newwidth = $ThumbWidth*$imgratio;
           }        
           
           $resized_img = imagecreatetruecolor($newwidth,$newheight);
			  $new_img = imagecreatefromjpeg($thumbimage);           
           
           imagecopyresampled($resized_img, $new_img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

           ImageJpeg ($resized_img,$tempimage);
           ImageDestroy ($resized_img);
           ImageDestroy ($new_img);
           unlink("$thumbimage");
           rename("$tempimage", "$thumbimage");           
           
//Strip slashes and sanitize data
$cont1 = stripslashes($cont1);
$cont2 = stripslashes($cont2);
$cont1 = str_replace ("\"","\\\"", $cont1);
$cont2 = str_replace ("\"","\\\"", $cont2);
$cont2 = str_replace ("\n","<br>", $cont2);
$cont1 = htmlspecialchars($cont1);
$cont2 = htmlspecialchars($cont2);

//Then save the imageinformation
$data = "data/settings/modules/albums/$var/$newfile.php";     
$file = fopen($data, "w");  
fputs($file, "<?php 
\$name = \"$cont1\";;
\$info = \"$cont2\";
?>");  
fclose($file);

//Redirect
redirect("?module=albums&page=editalbum&var=$var","0");
}
}

echo "<p><a href=\"?module=albums\"><<< $lang_theme12</a></p>";
?>