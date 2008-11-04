<?php
//First, check if file has been put in the right directory
if(!file_exists('admin.php')) {
	echo 'At the moment, this file is situated in the <i>docs</i> directory. To perform an update, please
	move this file to the root directory of pluck first.';
	exit;
}

//Include functions
require ('data/inc/functions.all.php');
require ('data/inc/functions.admin.php');
//Show title
echo "<h3>Pluck update</h3>";

//----------------
//Startpage
//Show the different options for upgrading
//----------------

if(!isset($_GET['step'])) {
echo "<p>Welcome to the pluck upgrading script. This script will help you upgrade your pluck to the newest version,
<b>4.6</b>. This script only supports upgrading your pluck installation if you use pluck 4.3, 4.4 or 4.5.x.
Any older versions are not tested, but may work.</p>

<p><b>We strongly recommend creating a complete backup of your pluck installation before proceeding with the upgrade procedure.</b></p>

<a href=\"?step=2\"><b>Proceed...</b></a>";
}

elseif(isset($_GET['step'])) {
$step = $_GET['step'];

//----------------
//Step 2
//Make files writable
//----------------

if($step == '2') {
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
}

//----------------
//Step 3
//Optional, for upgrade from 4.3 or 4.4
//Rearrange files for pluck 4.5 compatibility
//----------------

elseif($step == '3') {
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
	if(!file_exists('data/trash/pages')) {
		mkdir('data/trash/pages', 0777);
		chmod('data/trash/pages', 0777);
	}
	if(!file_exists('data/trash/images')) {
		mkdir('data/trash/images', 0777);
		chmod('data/trash/images', 0777);
	}

	echo '<p>We just did a file reorganization, to make your pluck installation compatible
	with the directory structure of pluck 4.5. We can now proceed to the next step.<br />
	<a href="?step=4"><b>Proceed...</b></a></p>';
}

/*----------------
STEP 4
Upgrade files to pluck 4.6 structure
 - move pages to data/settings/page
 - read out blogposts and transfer to new location
 - tell the user how to transfer albums
 - convert password to md5
 - add site title to data/settings/options.php
----------------*/
elseif($step == '4') {
	//First, create needed folders
	if(!file_exists('data/settings/modules')) {
		mkdir('data/settings/modules', 0777);
		chmod('data/settings/modules', 0777);
	}
	if(!file_exists('data/settings/pages')) {
		mkdir('data/settings/pages', 0777);
		chmod('data/settings/pages', 0777);
	}
	if(!file_exists('data/settings/modules/albums')) {
		mkdir('data/settings/modules/albums', 0777);
		chmod('data/settings/modules/albums', 0777);
	}
	if(!file_exists('data/settings/modules/blog')) {
		mkdir('data/settings/modules/blog', 0777);
		chmod('data/settings/modules/blog', 0777);
	}
	if(!file_exists('data/settings/modules/blog/posts')) {
		mkdir('data/settings/modules/blog/posts', 0777);
		chmod('data/settings/modules/blog/posts', 0777);
	}

	//Then, copy all pages to new location
	$pages = read_dir_contents('data/content','files');
	if(isset($pages)) {
		foreach ($pages as $page) {
			copy('data/content/'.$page,'data/settings/pages/'.$page);
			unlink('data/content/'.$page);
			echo 'Transferred page "'.$page.'"...<br />';
		}
	}

	//Convert blog posts
	$blog_categories = read_dir_contents('data/blog','dirs');
	if(isset($blog_categories)) {
		foreach ($blog_categories as $blog_category) {
			$blog_posts = read_dir_contents('data/blog/'.$blog_category.'/posts','files');
			foreach ($blog_posts as $blog_post) {
				//Include the blog post information
				include('data/blog/'.$blog_category.'/posts/'.$blog_post);

				//Extract time variables
				list($date, $time) = split(', ', $postdate);
				list($month, $day, $year) = split('[/.-]', $date);

				//Generate new filename
				$newfile = strtolower($title);
				$newfile = str_replace('.','',$newfile);
				$newfile = str_replace(',','',$newfile);
				$newfile = str_replace('?','',$newfile);
				$newfile = str_replace(':','',$newfile);
				$newfile = str_replace('<','',$newfile);
				$newfile = str_replace('>','',$newfile);
				$newfile = str_replace('=','',$newfile);
				$newfile = str_replace('"','',$newfile);
				$newfile = str_replace('\'','',$newfile);
				$newfile = str_replace('/','',$newfile);
				$newfile = str_replace("\\",'',$newfile);
				$newfile = str_replace('  ','-',$newfile);
				$newfile = str_replace(' ','-',$newfile);

				//Make sure chosen filename doesn't exist
				if(file_exists('data/settings/modules/blog/posts/'.$newfile.'.php')) {
					$newfile = $newfile.'-new';
				}

				//Strip slashes from post
				$title = stripslashes($title);
				$title = str_replace("\"", "\\\"", $title);
				$blog_category = stripslashes($blog_category);
				$blog_category = str_replace("\"", "\\\"", $blog_category);
				$content = stripslashes($content);
				$content = str_replace("\"", "\\\"", $content);

				//Save post
				$file = fopen('data/settings/modules/blog/posts/'.$newfile.'.php', 'w');
				fputs($file, '<?php'."\n"
				.'$post_title = "'.$title.'";'."\n"
				.'$post_category = "'.$blog_category.'";'."\n"
				.'$post_content = "'.$content.'";'."\n"
				.'$post_day = "'.$day.'";'."\n"
				.'$post_month = "'.$month.'";'."\n"
				.'$post_year = "'.$year.'";'."\n"
				.'$post_time = "'.$time.'";'."\n");

				//See if we have comments, and save those too
				$blog_post_wext = str_replace('.php','',$blog_post);
				//Read out reactions
				$reactions = read_dir_contents('data/blog/'.$blog_category.'/reactions/'.$blog_post_wext,'files');

				if(isset($reactions)) {
					//Sort array, and reverse it
					natcasesort($reactions);
					$reactions = array_reverse($reactions);
					//Set key to zero
					$key = 0;

					foreach ($reactions as $reaction) {
						//Include reaction
						include('data/blog/'.$blog_category.'/reactions/'.$blog_post_wext.'/'.$reaction);

						//Extract time variables
						list($date, $time) = split(', ', $postdate);
						list($month, $day, $year) = split('[/.-]', $date);

						//Strip slashes from reaction
						$title = stripslashes($title);
						$title = str_replace("\"", "\\\"", $title);
						$name = stripslashes($name);
						$name = str_replace("\"", "\\\"", $name);
						$message = stripslashes($message);
						$message = str_replace("\"", "\\\"", $message);

						//Save reaction
						fputs($file, '$post_reaction_title['.$key.'] = "'.$title.'";'."\n"
						.'$post_reaction_name['.$key.'] = "'.$name.'";'."\n"
						.'$post_reaction_content['.$key.'] = "'.$message.'";'."\n"
						.'$post_reaction_day['.$key.'] = "'.$day.'";'."\n"
						.'$post_reaction_month['.$key.'] = "'.$month.'";'."\n"
						.'$post_reaction_year['.$key.'] = "'.$year.'";'."\n"
						.'$post_reaction_time['.$key.'] = "'.$time.'";'."\n");

						//Higher the key
						$key++;
					}
				}
				//Finish file, chmod it etc.
				fputs($file, '?>');
				fclose($file);
				chmod('data/settings/modules/blog/posts/'.$newfile.'.php', 0777);
			}
		}
		//Now, create array with all new posts
		$new_posts = read_dir_contents('data/settings/modules/blog/posts','files');
		//And put those in a new array, sorted on time
		foreach ($new_posts as $new_post) {
			include('data/settings/modules/blog/posts/'.$new_post);
			$time = $post_year.'-'.$post_month.'-'.$post_day.'-'.$post_time;
			$unix_time = strtotime($time);
			$time_array[$new_post] = $unix_time;
		}
		//Now, sort the new array
		asort($time_array);

		//Create the post_index.dat file
		foreach ($time_array as $newfile => $timestamp) {
			if(file_exists('data/settings/modules/blog/post_index.dat')) {
				$contents = file_get_contents('data/settings/modules/blog/post_index.dat');
				$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
				if(!empty($contents)) {
					fputs($file,$newfile."\n".$contents);
				}
				else {
					fputs($file,$newfile);
				}
			}
			else {
				$file = fopen('data/settings/modules/blog/post_index.dat', 'w');
				fputs($file,$newfile);
			}
			fclose($file);
			unset($file);
			chmod('data/settings/modules/blog/post_index.dat',0777);

			//Show message
			echo 'Transferred blog post "'.$newfile.'"...<br />';
		}
	}

	//Save title in new file
	//First, get title
	$sitetitle_old = file_get_contents('data/settings/title.dat');
	//Get other options
	require('data/settings/options.php');
	//And save them
	save_options($sitetitle_old,$email,$xhtmlruleset);

	//Convert pass to MD5
	//Include password
	require('data/settings/pass.php');
	//Save it again, now MD5'ed
	save_password($ww);

	echo '<p>Your pages and blog posts have just been transferred. If you have any albums in your pluck installation, please go to <a href="?step=5">step 5</a>. Otherwise, directly go to <a href="?step=6">step 6</a>.</p>';
}

/*----------------
STEP 5
Upgrade files to pluck 4.6 structure
 - tell the user how to transfer albums
----------------*/
elseif($step == '5') {
echo '<p>Now, you need to move your albums manually. First, download all folders in the <b>data/albums</b> dir onto your hardrive, using a FTP-application. Then, upload all these directories into the <b>data/settings/modules/albums</b> directory.</p>

<p>Done moving the albums? <a href="?step=6"><b>Proceed...</b></a></p>';
}

/*----------------
STEP 6
Upgrade has been finished
----------------*/
elseif($step == '6') {
	echo '<p>Done! Don\'t forget to <b>delete this file (update.php)</b> using your FTP-application. Then, you can <a href="login.php">login</a>.</p>

	<p>Some old files of your old pluck installation may still exist. If you wish, you can safely remove these files to free up disk space. You can for example use a FTP-application for doing this. The files are:</p>
	
	<ul>
		<li>data/content (entire directory)</li>
		<li>data/albums (entire directory)</li>
		<li>data/blog (entire directory)</li>
		<li>data/stats (entire directory)</li>
		<li>data/tinymce (entire directory)</li>
		<li>data/lightbox (entire directory)</li>
		<li>data/inc/themes (entire directory)</li>
		<li>data/settings/title.dat</li>
	</ul>';
}
}
?>