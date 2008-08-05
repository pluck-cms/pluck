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

//We need tinyMCE...
$tinymce = "yes";

//Then set character encoding
header("Content-Type:text/html;charset=utf-8");
?>
<html>
<head>
<title>pluck <?php echo $pluck_version; ?> - <?php echo $titelkop; ?></title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<?php
if ((isset($direction)) && ($direction == "rtl")) {
	echo "<link href=\"data/styleadmin-rtl.css\" rel=\"stylesheet\" type=\"text/css\" media=\"screen\">";
}
else {
	echo "<link href=\"data/styleadmin.css\" rel=\"stylesheet\" type=\"text/css\" media=\"screen\">";
}

//Include tinyMCE
include("data/inc/tinymce_inc.php");
?>
<meta name="robots" content="noindex">
<script language="javascript" type="text/javascript">
<!--
function refresh() {
	window.location.reload(false);
}
//-->
</script>
<script language="javascript" type="text/javascript">
<!--
//Enter-listener
if (document.layers)
	document.captureEvents(Event.KEYDOWN);
	document.onkeydown =
	function (evt) { 
		var keyCode = evt ? (evt.which ? evt.which : evt.keyCode) : event.keyCode;
		if (keyCode == 13)   //13 = the code for pressing ENTER

		{
			document.form.submit();
		}
	}
//-->
</script>
</head>

<body>
<div class="menuheader">
<div class="menu2">
	<span class="menuitems"><?php echo $titelkop; ?></span>

</div>
<span class="cmssystem">pluck</span>
</div>

<div class="text">