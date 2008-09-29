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
if((!ereg('index.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('admin.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('install.php', $_SERVER['SCRIPT_FILENAME'])) && (!ereg('login.php', $_SERVER['SCRIPT_FILENAME']))){
    //Give out an "access denied" error
    echo 'access denied';
    //Block all other code
    exit();
}

//Include old settings, to display them
include ('data/settings/options.php');
?>
<p><b><?php echo $lang_settings5; ?></b></p>
<form method="post" action="">
<p>
	<label class="kop2" for="title"><?php echo $lang_kop6; ?></label><br />
	<span class="kop4"><?php echo $lang_settings2; ?></span><br />
	<input name="title" id="title" type="text" value="<?php echo $sitetitle; ?>" />
</p>
<p>
	<label class="kop2" for="email"><?php echo $lang_install24; ?></label><br />
	<span class="kop4"><?php echo $lang_install25; ?></span><br />
	<input name="email" id="email" type="text" value="<?php echo $email; ?>" />
</p>
<p>
	<span class="kop2"><?php echo $lang_contact2; ?></span><br />
	<input type="checkbox" name="xhtml" id="xhtml" value="true" <?php if ($xhtmlruleset == 'true') echo 'checked="checked"'; ?> /><label for="xhtml">&nbsp;<?php echo $lang_settings6; ?></label>
</p>
<input type="submit" name="Submit" value="<?php echo $lang_install13; ?>" />
<input type="button" name="Cancel" value="<?php echo $lang_install14; ?>" onclick="javascript: window.location='?action=options';" />
</form>

<?php
//If form has been submitted
if(isset($_POST['Submit'])) {

	//Check if a sitetitle has been given in
	if (!isset($_POST['title'])) { ?>
		<strong><?php echo $lang_stitle2; ?></strong>
	<?php }

	//Check if emailaddress is valid
	elseif(!eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $_POST['email'])) { ?>
		<strong><?php echo $lang_settings7; ?></strong>
	<?php }

	else {
		//If XHTML-ruleset is not on, turn it off
		if($_POST['xhtml'] != 'true')
			$xhtml = 'false';
		else
			$xhtml = 'true';

		//Then, save the settings
		save_options($_POST['title'],$_POST['email'],$xhtml);
		redirect('?action=options',0);
		echo $lang_settings4;
	}
} 
?>