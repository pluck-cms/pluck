<?php
//Include functions
include ('data/inc/functions.all.php');
//Show title
echo "<h3>Pluck update</h3>";

//----------------
//Startpage
//Show the different options for upgrading
//----------------

if(!isset($_GET['step'])) {
echo "<p>Welcome to the pluck upgrading script. This script will help you upgrade your pluck to the newest version,
<b>4.6</b>. This script only supports upgrading your pluck installation if you use pluck 4.3, 4.4, 4.5.x.
Any older versions are not tested, but may work.</p>

<a href=\"?step=2\"><b>Proceed...</b></a>";
}

elseif(isset($_GET['step'])) {
$step = $_GET['step'];

//----------------
//Step 2
//Make files writable
//----------------

if($step == "2") {
	//First define the function
	function check_writable($file) {
		//Include Translation data
		include ('data/inc/lang/en.php');
		if (is_writable($file)) {
				echo "<tr><td>/$file &nbsp;"; 
				echo "<td><img src=\"data/image/update-no.png\" width=\"15\" height=\"15\" alt=\"true\"></td></tr>";
		}
		else {
			echo "<tr><td>/$file &nbsp;"; 
			echo "<td><img src=\"data/image/error.png\" width=\"15\" height=\"15\" alt=\"false\"></td></tr>"; 
		}
	}

	echo "<p>Now, make sure all following files are writable. Refresh the page if you want to update the status.
	Proceed if all files have the right permissions.</p><table>";

	check_writable('images');
	check_writable('data/modules');
	check_writable('data/settings');
	check_writable('data/trash');
	check_writable('data/themes');
	check_writable('data/themes/default');
	check_writable('data/themes/green');
	check_writable('data/themes/oldstyle');
	check_writable('data/settings/langpref.php');
	check_writable('data/settings/themepref.php');

	echo '</table><p>All files have right permissions? Ok! Now, choose the pluck version you was using before starting this upgrade procedure.
	Click on the correct version to start the upgrade procedure.</p>
	<ul>
		<li><a href="?step=3">version 4.3 or 4.4</a></li>
		<li><a href="?step=4">version 4.5 or 4.5.x (eg, 4.5.1, 4.5.2 etc.)</a></li>
	</ul>';

	include('data/inc/footer.php');
}

//----------------
//Step 3
//Optional, for upgrade from 4.3 or 4.4
//Rearrange files for pluck 4.5 compatibility
//----------------

elseif($step == "3") {
	echo 'Rearranging files for compatibility with pluck 4.5...<br />';

	//title.dat file
	copy("data/title.dat", "data/settings/title.dat");
	//install.dat file
	copy("data/install.dat", "data/settings/install.dat");
	//options.php file
	copy("data/options.php", "data/settings/options.php");
	//pass.php file
	copy("data/pass.php", "data/settings/pass.php");
	//langpref.php file
	unlink("data/settings/langpref.php");
	copy("data/inc/lang/langpref.php", "data/settings/langpref.php");

	//make needed folders
	mkdir('data/trash/pages', 0777);
	chmod('data/trash/pages', 0777);
	mkdir('data/trash/images', 0777);
	chmod('data/trash/images', 0777);

	echo '<p>We just did a file reorganization, to make your pluck installation compatible
	with the directory structure of pluck 4.5. We can now proceed to the next step.<br />
	<b><a href="?step=4"><b>Proceed...</b></a></p>';
}

/*----------------
STEP 4
Upgrade files to pluck 4.6 structure
 - move pages to data/settings/page
 - read out blogposts and transfer to new location
 - tell the user how to transfer albums
----------------*/

elseif($step == '4') {
	//First, create needed folders
	mkdir('data/settings/modules', 0777);
	chmod('data/settings/modules', 0777);
	mkdir('data/settings/pages', 0777);
	chmod('data/settings/pages', 0777);
	mkdir('data/settings/modules/albums', 0777);
	chmod('data/settings/modules/albums', 0777);
	mkdir('data/settings/modules/blog', 0777);
	chmod('data/settings/modules/blog', 0777);

	//Then, copy all pages to new location
	$pages = read_dir_contents('data/content','files');
	if(isset($pages)) {
		foreach ($pages as $page) {
			copy('data/content/'.$file,'data/settings/pages/'.$file);
			unlink('data/content/'.$file);
			echo 'Transferred page "'.$file.'"...<br />';
		}
	}

	//Convert blog posts (TODO)

	echo '<p>Your pages and blogposts have just been transferred. If you have any albums in your pluck installation, please go to <a href="?step=5">step 5</a>. Otherwise, directly go to <a href="?step=6">step 6</a></p>';

}

/*----------------
STEP 4
Upgrade has been finished
----------------*/
elseif($step == '6') {
	echo '<p>Done! Don\'t forget to <b>delete this file (update.php)</b> using your FTP-application. Then, you can <a href="login.php">login</a>.</p>';
}

}
?>