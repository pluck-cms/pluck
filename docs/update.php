<?php
//First, check if file has been put in the right directory
if (!file_exists('admin.php')) {
	echo 'At the moment, this file is situated in the <i>docs</i> directory. To perform an update, please
	move this file to the root directory of pluck first.';
	exit;
}

//First, define that we are in pluck.
define('IN_PLUCK', true);

//Then start session support.
session_start();

//Include security-enhancements.
require_once ('data/inc/security.php');
//Include functions.
require_once ('data/inc/functions.modules.php');
require_once ('data/inc/functions.all.php');
require_once ('data/inc/functions.admin.php');
//Include Translation data.
require_once ('data/inc/variables.all.php');

//Include header
$titelkop = 'update helper';
include_once ('data/inc/header2.php');

//----------------
//Startpage
//Show the different options for upgrading
//----------------

if (!isset($_GET['step'])) { ?>
	<p>Welcome to the pluck upgrading script. This script will help you upgrade your pluck to the newest version,
	<b><?php echo PLUCK_VERSION; ?></b>. This script only supports upgrading your pluck installation if you use pluck 4.6.x. Updating from any older version is not supported.</p>

	<p><b>We strongly recommend creating a complete backup of your pluck installation before proceeding with the upgrade procedure.</b></p>

	<a href="?step=1"><b>Proceed...</b></a>
<?php }

//----------------
//Step 1
//Convert password to new SHA512 hash
//----------------

if ((isset($_GET['step'])) && ($_GET['step']) == '1') { ?>
	<p>To start the upgrade procedure, please enter the password of your pluck installation below.</p>

	<form action="" method="post">
		<input name="cont1" size="25" type="password" />
		<input type="submit" name="submit" value="Proceed" />
	</form>

<?php
	if (isset($_POST['submit'])) {
		//Include old MD5-hashed password
		require_once ('data/settings/pass.php');
		//If entered password is valid, safe it in new SHA512 hash
		if ($ww == md5($cont1)) {
			save_password($cont1);
			redirect('?step=2', 0);
		}
		//If it's invalid, show error
		else
			show_error('The password you entered is invalid.', 1);
	}
}

//----------------
//Step 2
//Convert pages, blog (posts, comments & categories) and albums
//----------------

if ((isset($_GET['step'])) && ($_GET['step']) == '2') { ?>

	<p>The updater is now converting all the files to the new formats...</p>

	<?php
	//----------------
	//Pages
	//----------------
	if (is_dir('data/settings/pages')) {
		$pages = read_dir_contents('data/settings/pages', 'files');
		if ($pages != FALSE) {
			natcasesort($pages);
			//Move all pages to data/settings (otherwise, page numbers will be messed up
			foreach ($pages as $page) {
				rename('data/settings/pages/'.$page, 'data/settings/'.$page);
			}
			//Save all pages in new format
			foreach ($pages as $page) {
				include_once ('data/settings/'.$page);
				if (save_page($title, $content, $hidden, null))
					unlink('data/settings/'.$page);
				else
					show_error('Could not convert page '.$page.' to the new format.', 1);
			}
		}
	}


	//----------------
	//Blog
	//----------------
	//Check if we need to convert the blog
	if (file_exists('data/settings/modules/blog/post_index.dat')) {
		$handle = fopen('data/settings/modules/blog/post_index.dat', 'r');
		//Make array of posts
		while(!feof($handle)) {
			$file = fgets($handle, 4096);
			//Filter out line breaks
			$file = str_replace ("\n",'', $file);
			//Check if post exists
			if (file_exists('data/settings/modules/blog/posts/'.$file)) {
				$posts[] = $file;
			}
		}
		//Reverse array
		$posts = array_reverse($posts);
		//Move all posts to data/settings/modules/blog
		foreach ($posts as $post) {
			rename('data/settings/modules/blog/posts/'.$post, 'data/settings/modules/blog/'.$post);
		}
		//Include blog functions
		include_once ('data/modules/blog/functions.php');
		//Save all posts in new format
		foreach ($posts as $post) {
			//Get post information
			include_once ('data/settings/modules/blog/'.$post);

			//Get hour and minute from post_time
			list($hour, $minute) = explode(':', $post_time);
			//Save blog post
			$post_seoname = blog_save_post($post_title, $post_category, $post_content, null, mktime($hour, $minute, '00', $post_month, $post_day, $post_year));

			//Check if there are reactions
			if (isset($post_reaction_title)) {
				foreach ($post_reaction_title as $index => $title) {
					//Get hour and minute from post_reaction_time
					list($hour, $minute) = explode(':', $post_reaction_time[$index]);
					blog_save_reaction($post_seoname, $post_reaction_name[$index], 'unknown', 'unknown', $post_reaction_content[$index], null, mktime($hour, $minute, '00', $post_reaction_month[$index], $post_reaction_day[$index], $post_reaction_year[$index]));
				}
			}
			unset($post_reaction_title);

			//Delete post file
			unlink('data/settings/modules/blog/'.$post);
		}
		unset($posts);
		//Delete post index
		unlink('data/settings/modules/blog/post_index.dat');
	}

	//Then check if there are categories to convert
	if (file_exists('data/settings/modules/blog/categories.dat')) {
		//Load them
		$categories = file_get_contents('data/settings/modules/blog/categories.dat');

		//Then in an array
		$categories = explode(',',$categories);

		//Create categories
		foreach ($categories as $category)
			blog_create_category($category);

		//Delete categories file
		unlink('data/settings/modules/blog/categories.dat');
	}

	//----------------
	//Albums
	//----------------
	//Check if there are albums to convert
	if (file_exists('data/settings/modules/albums')) {
		$albums = read_dir_contents('data/settings/modules/albums', 'dirs');
		if ($albums != FALSE) {
			foreach ($albums as $album) {
				//Save album file
				$data['album_name'] = $album;
				save_file('data/settings/modules/albums/'.$album.'.php', $data);

				//Convert images
				$images = read_dir_contents('data/settings/modules/albums/'.$album, 'files');
				if (isset($images)) {
					natcasesort($images);
					$count = 1;
					foreach ($images as $image) {
						//Get image file extension (.jpg or .jpeg)
						if (file_exists('data/settings/modules/albums/'.$album.'/'.str_replace('.php', '', $image).'.jpg'))
							$ext = '.jpg';
						if (file_exists('data/settings/modules/albums/'.$album.'/'.str_replace('.php', '', $image).'.jpeg'))
							$ext = '.jpeg';
						if (strpos($image, '.php')) {
							rename('data/settings/modules/albums/'.$album.'/'.$image, 'data/settings/modules/albums/'.$album.'/'.$count.'.'.str_replace('.php', '', $image).$ext.'.php');
							$count++;
						}
					}
				}
			}
		}
	}

//Done, display message
echo '<p>The updater is now done converting all files to the new formats. If no error messages have shown up above, you can assume the update was successfull. If any errors have appeared above, please tell us about it in a <a href="https://bugs.launchpad.net/pluck-cms" target="_blank">bug report</a>.</p>';

echo '<p><b>Please delete this file (update.php) before proceeding.</b></p>';

echo '<a href="index.php"><b>Go to your website</b></a>';
}

include_once ('data/inc/footer.php');
?>