<?php
//Some functions needed for the album-module are defined here

//Predefined variables
if (isset($_GET['var'])) {
	$var = $_GET['var'];
}
if (isset($_GET['var2'])) {
	$var2 = $_GET['var2'];
}
if (isset($_GET['var2'])) {
	$var3 = $_GET['var3'];
}

//Function: readout reactions on a blogpost
//------------
function read_blog_reactions($dir) {
   $path = opendir($dir);
   while (false !== ($file = readdir($path))) {
       if(($file !== ".") and ($file !== "..")) {
           if(is_file($dir."/".$file))
               $files[]=$file;
           else
               $dirs[]=$dir."/".$file;    
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
	$files = array_reverse($files);
   
   foreach ($files as $file) {
			  $var = $_GET['var'];
			  $var2 = $_GET['var2'];
			  list($reactiondir, $extension) = explode(".", $var);
			  
			  //Include the reaction information
			  include("data/blog/$var2/reactions/$reactiondir/$file");
			  //Change html enters in real ones
			  $message = str_replace("<br />", "\n", $message);
			  
           //Include Translation data
			  include ("data/settings/langpref.php");
			  include ("data/inc/lang/en.php");
			  include ("data/inc/lang/$langpref");
			  
			  echo "<div class=\"menudiv\" style=\"margin: 10px;\">
			  <table>
			  <tr>
			  		<td>
			  			<img src=\"data/modules/blog/images/reactions.png\" alt=\"\" border=\"0\">
			  		</td>
			  		<td style=\"width: 600px;\">
			  			<form method=\"post\" action=\"\">
			  			<b>$lang_install17</b><br>
			  			<input name=\"cont1\" type=\"text\" value=\"$title\"><br><br>
			  			
			  			<textarea name=\"cont2\" rows=\"5\" cols=\"65\">$message</textarea><br><br>
			  			
			  			<input name=\"cont3\" type=\"hidden\" value=\"$name\">
			  			<input name=\"cont4\" type=\"hidden\" value=\"$postdate\">
			  			<input name=\"cont5\" type=\"hidden\" value=\"$file\">
			  			<input type=\"submit\" name=\"Submit\" value=\"$lang_install13\">
			  			</form>
			  		</td>
			  		<td>
			  			<a href=\"?module=blog&page=deletereactions&var=$file&var2=$var2&var3=$reactiondir\"><img src=\"data/image/delete_from_trash.png\" border=\"0\" title=\"$lang_blog21\" alt=\"$lang_blog21\"></a>
			  		</td>
			  </tr>
			  </table>
			  </div>";			  
			  }
   }
   closedir($path);
}
?>