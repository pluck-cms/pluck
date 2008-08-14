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
//Include functions
include("data/inc/functions.all.php");
//Include variables
include("data/inc/variables.all.php");

//Include POST/GET data
include ("data/inc/post_get.php");

//Check if we've installed pluck
require ("data/settings/install.dat");
//If not:
if ($install=="no") {
	$titelkop = $lang_login1;
	include ("data/inc/header2.php");
	echo "<p><b>$lang_login2</b></p>";
	echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"2; URL=install.php\">";
	include ("data/inc/footer.php");
} 

//If pluck is installed:
elseif ($install=="yes") {
	require ("data/settings/pass.php");

	//Check if we're already logged in
	session_start();
	if ((isset($_SESSION["cmssystem_loggedin"])) && ($_SESSION["cmssystem_loggedin"] == "ok")) {
		header("Location: admin.php");
		exit;
	}

	//If password has not yet been sent
	if(!isset($_POST['Submit'])) {
		//Include header-file
		$titelkop = $lang_login1;
		include ("data/inc/header2.php");

		echo "<span class=\"kop2\">$lang_login3</span><br>
		<form action=\"login.php\" method=\"post\" name=\"passform\">
		<input name=\"pass\" size=\"25\" type=\"password\" value=\"\" size=\"9\">
		<input type=\"text\" name=\"bogusField\" style=\"display: none;\" />
		<input type=\"submit\" name=\"Submit\" value=\"$lang_login4\">
		</form>";

		include ("data/inc/footer.php");
	}

	//If password has been sent...
	elseif(isset($_POST['Submit'])) {

		//...first MD5-encrypt password that has been posted
		$pass = md5($pass);

		//...and is correct:
		if ($pass == $ww) {
			//Save session
			$_SESSION["cmssystem_loggedin"] = "ok";
			//Display successmessage   
			$titelkop = $lang_login1;
			include ("data/inc/header2.php");
			echo "$lang_login5
			<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"1; URL=admin.php?action=start\">";
			include ("data/inc/footer.php");
		}

		//---------------
		//...or is empty:
		elseif ($pass == "") {
			echo "<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"0; URL=login.php\">";
		}

		//---------------
		//...or is NOT correct:
		elseif ($pass != "$ww") {
			$titelkop = $lang_login1;
			include ("data/inc/header2.php");
			echo "$lang_login6
			<META HTTP-EQUIV=\"REFRESH\" CONTENT=\"2; URL=login.php\">";
			include ("data/inc/footer.php");
		}
	}
}
?>