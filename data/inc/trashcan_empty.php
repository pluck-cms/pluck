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

function recursive_remove_directory_contents($directory, $empty=TRUE)
 {
     if(substr($directory,-1) == '/')
     {
         $directory = substr($directory,0,-1);
    }
    if(!file_exists($directory) || !is_dir($directory))
     {
         return FALSE;
     }elseif(is_readable($directory))
     {
         $handle = opendir($directory);
         while (FALSE !== ($item = readdir($handle)))
         {
             if($item != '.' && $item != '..')
             {
                 $path = $directory.'/'.$item;
                 if(is_dir($path)) 
                 {
                     recursive_remove_directory($path);
                 }else{
                     unlink($path);
                 }
             }
         }
         closedir($handle);
         if($empty == FALSE)
         {
             if(!rmdir($directory))
             {                 return FALSE;
             }
         }
     }
     return TRUE;
 }

recursive_remove_directory_contents("data/trash/images");
recursive_remove_directory_contents("data/trash/pages");
echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"0; URL=?action=trashcan\">";
?>