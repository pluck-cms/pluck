<?php
echo "<h3>Pluck update</h3>";

//----------------
//Startpage
//Show the different options for upgrading
//----------------

if(!isset($_GET['step'])) {
echo "<p>Welcome to the pluck upgrading script. This script will help you upgrade your pluck to the newest version,
<b>4.6</b>. This script only supports upgrading your pluck installation if you use pluck 4.3, 4.4, 4.5 or 4.5.1.
Any older versions are not tested, but may work.</p>

<p>Let's start! First, choose the pluck version you was using before starting this upgrade procedure.
Click on the correct version to start the upgrade.</p>";
}

elseif(isset($_GET['step'])) {
$step = $_GET['step'];

//----------------
//Step 2
//Optional, for upgrade from 4.3 or 4.4
//Make files writable
//----------------

if($step == "2") {
//First define the function
//---------------------------
function check_writable($file) {
//Include Translation data
include ("data/inc/lang/langpref.php");
include ("data/inc/lang/en.php");
include ("data/inc/lang/$langpref");
if (is_writable($file)) {
	echo "<tr><td>/$file &nbsp;"; 
	echo "<td><img src=\"data/image/true.gif\" width=\"15\" height=\"15\" alt=\"true\"></td></tr>";
	}
	else {
	echo "<tr><td>/$file &nbsp;"; 
	echo "<td><img src=\"data/image/false.gif\" width=\"15\" height=\"15\" alt=\"false\"></td></tr>"; 
	}
}

echo "<p>Now, make sure all following files are writable. Refresh the page if you want to update the status.
Proceed if all files have the right permissions.</p><table>";

check_writable("images");
check_writable("data/albums");
check_writable("data/blog");
check_writable("data/content");
check_writable("data/settings");
check_writable("data/stats");
check_writable("data/trash");
check_writable("data/inc/themes");
check_writable("data/inc/themes/default");
check_writable("data/inc/themes/green");
check_writable("data/inc/themes/oldstyle");
check_writable("data/settings/install.dat");
check_writable("data/settings/langpref.php");
check_writable("data/settings/themepref.php");

echo "</table><a href=\"?step=3\"><b>Proceed...</b></a>";
include ("data/inc/footer.php");
}

//----------------
//Step 3
//Optional, for upgrade from 4.3 or 4.4
//Rearrange files for pluck 4.5 compatibility
//----------------

elseif($step == "3") {

echo "Rearranging files for compatibility with pluck 4.5...<br>";

copy("data/title.dat", "data/settings/title.dat");

unlink("data/settings/install.dat");
copy("data/install.dat", "data/settings/install.dat");

copy("data/options.php", "data/settings/options.php");

copy("data/pass.php", "data/settings/pass.php");

unlink("data/settings/langpref.php");
copy("data/inc/lang/langpref.php", "data/settings/langpref.php");

unlink("data/settings/themepref.php");
copy("data/inc/themes/themepref.php", "data/settings/themepref.php");

mkdir ("data/trash/pages", 0777);
mkdir ("data/trash/images", 0777);
mkdir ("data/settings/modules", 0777);
unlink ("images/delete_me");
unlink ("data/content/delete_me");
unlink ("data/albums/delete_me");
unlink ("data/blog/delete_me");
unlink ("data/trash/delete_me");

echo "<p>We just did a file reorganization, to make your pluck installation compatible
with the directory structure of pluck 4.5. We can now proceed to the next step.<br>
<b><a href=\"?step=4\"><b>Proceed...</b></a></p>";
}

//----------------
//Step 4
//We can finally start upgrading to pluck 4.6
//----------------

elseif($step == "4") {

}
//echo "<p>Done! Don't forget to <b>delete this file (update.php)</b> using your FTP-application. Then, you can <a href=\"login.php\">login</a>.</p>";

}
?>