<?php
//Some functions needed for the album-module are defined here

//Predefined variables
if (isset($_GET['var']))
	$var = $_GET['var'];
if (isset($_GET['var2']))
	$var2 = $_GET['var2'];

//Function: readout albums
//------------
function read_albums($dir) {
   $path = opendir($dir);
   while (false !== ($file = readdir($path))) {
       if(($file !== ".") and ($file !== "..")) {
           if(is_file($dir."/".$file))
               $files[]=$file;
           else
               $dirs[]=$file;
       }
   }
   if (!isset($dirs)) {
	//Include Translation data
	include ("data/settings/langpref.php");
	include ("data/inc/lang/en.php");
	include ("data/inc/lang/$langpref");
	echo "<span class=\"kop4\">$lang_albums14</span>"; }

   if (isset($dirs)) {
   natcasesort($dirs);
   foreach ($dirs as $dir) {
		//Include Translation data
		include ("data/settings/langpref.php");
		include ("data/inc/lang/en.php");
		include ("data/inc/lang/$langpref");
		echo "<div class=\"menudiv\" style=\"margin: 20px;\">
			<table>
				<tr>
					<td>
						<img src=\"data/modules/albums/images/albums.png\" border=\"0\" alt=\"\">
					</td>
					<td style=\"width: 350px;\">
						<span style=\"font-size: 17pt;\">$dir</span>
					</td>
					<td>
					<a href=\"?module=albums&page=editalbum&var=$dir\"><img src=\"data/image/edit.png\" border=\"0\" title=\"$lang_albums6\" alt=\"$lang_albums6\"></a>
					</td>
					<td>
					<a href=\"?module=albums&page=deletealbum&var=$dir\"><img src=\"data/image/delete_from_trash.png\" border=\"0\" title=\"$lang_albums5\" alt=\"$lang_albums5\"></a>
					</td>
				</tr>
			</table>
			</div>"; }
   }
   closedir($path);
}


//Function: readout album-images
//------------
function read_albumimages($dir) {
   $path = opendir($dir);
   while (false !== ($file = readdir($path))) {
       if(($file !== ".") and ($file !== "..")) {
           if(is_file($dir."/".$file))
               $files[]=$file;
           else
               $dirs[]=$file;
       }
   }
	if (!$files) {
	//Include Translation data
	include ("data/settings/langpref.php");
	include ("data/inc/lang/en.php");
	include ("data/inc/lang/$langpref");
	echo "<span class=\"kop4\">$lang_albums14</span>"; }

   if($files) {
	natcasesort($files);

   foreach ($files as $file) {
	//Include Translation data
	include ("data/settings/langpref.php");
	include ("data/inc/lang/en.php");
	include ("data/inc/lang/$langpref");
   list($fdirname, $ext) = explode(".", $file);
				if (($ext == "jpg") || ($ext == "JPG")) {
            $var = $_GET['var'];
            include ("data/settings/modules/albums/$var/$fdirname.php");

				echo "<div class=\"menudiv\" style=\"margin: 10px;\">
							<table>
							<tr>
								<td>
									<a href=\"data/modules/albums/pages_admin/albums_getimage.php?image=$var/$fdirname.jpg\" target=\"_blank\"><img src=\"data/modules/albums/pages_admin/albums_getimage.php?image=$var/thumb/$fdirname.jpg\" title=\"$name\" alt=\"$name\" border=\"0\"></a>
								</td>
								<td style=\"width: 500px;\">
									<span style=\"font-size: 17pt;\">$name</span><br>
									<i>$info</i>
								</td>
								<td>
								<a href=\"?module=albums&page=editimage&var=$fdirname&var2=$var\"><img src=\"data/image/edit.png\" border=\"0\" title=\"$lang_albums6\" alt=\"$lang_albums6\"></a>
								</td>
								<td>
								<a href=\"?module=albums&page=imageup&var=$fdirname&var2=$var\"><img src=\"data/image/up.png\" border=\"0\" title=\"$lang_updown5\" alt=\"$lang_updown5\"></a>
								</td>
								<td>
								<a href=\"?module=albums&page=imagedown&var=$fdirname&var2=$var\"><img src=\"data/image/down.png\" border=\"0\" title=\"$lang_updown5\" alt=\"$lang_updown5\"></a>
								</td>
								<td>
								<a href=\"?module=albums&page=deleteimage&var=$fdirname&var2=$var\"><img src=\"data/image/delete_from_trash.png\" border=\"0\" title=\"$lang_kop13\" alt=\"$lang_kop13\"></a>
								</td>
								</tr>
							</table>
						</div>";
           }
        }
   }
   closedir($path);
}

?>