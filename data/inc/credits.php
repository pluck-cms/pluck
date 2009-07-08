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

//Make sure the file isn't accessed directly.
if (!strpos($_SERVER['SCRIPT_FILENAME'], 'index.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'admin.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'install.php') && !strpos($_SERVER['SCRIPT_FILENAME'], 'login.php')) {
	//Give out an "Access denied!" error.
	echo 'Access denied!';
	//Block all other code.
	exit;
}
?>

<p>
	<strong><?php echo $lang['credits']['message']; ?></strong>
</p>
<p>
	<span class="kop2"><?php echo $lang['credits']['project_leader']; ?></span>
	<br />
	<a href="http://www.somp.nl" target="_blank">Sander Thijsen</a>
</p>
<p>
	<span class="kop2"><?php echo $lang['credits']['developers']; ?></span>
	<br />
	<a href="http://spirit55555.dk" target="_blank">Anders Jørgensen</a>
	<br />
	<a href="http://www.somp.nl" target="_blank">Sander Thijsen</a>
</p>
<p>
	<span class="kop2"><?php echo $lang['credits']['contributions']; ?></span>
	<br />
	Kristaps Ancāns
	<br />
	Callan Barrett
	<br />
	Bogumił Cieniek
	<br />
	Dennis Sewbarath
	<br />
	Kevin Zettler

</p>
<?php
//Translation
//-----------
//First determine who's the translator
if ($langpref == 'nl.php')
	$translator = 'Sander Thijsen';
elseif ($langpref == 'da.php')
	$translator = 'Thomas Andresen<br />Lone Hansen';
elseif ($langpref == 'de.php')
	$translator = 'Max Effenberger<br />Dennis Sewberath<br />stoffal';
elseif ($langpref == 'es.php')
	$translator = 'Cesc';
elseif ($langpref == 'ct.php')
	$translator = 'Cesc';
elseif ($langpref == 'fr.php')
	$translator = 'zigzagbe<br />Dominique Heimler';
elseif ($langpref == 'he.php')
	$translator = 'Erez Wolf';
elseif ($langpref == 'hu.php')
	$translator = 'Wix';
elseif ($langpref == 'lt.php')
	$translator = 'Mindaugas Salamachinas';
elseif ($langpref == 'no.php')
	$translator = 'John Erik Kristensen';
elseif ($langpref == 'pt.php')
	$translator = 'Marco Paulo Ferreira<br />Hélio Carrasqueira';
elseif ($langpref == 'pt_br.php')
	$translator = 'Gilnei Moraes<br />Henrique Gogó<br />sarkioja';
elseif ($langpref == 'ru.php')
	$translator = 'Tkachev Vasily';
elseif ($langpref == 'sv.php')
	$translator = 'Carl Jansson';
elseif ($langpref == 'bg.php')
	$translator = 'smartx';
elseif ($langpref == 'th.php')
	$translator = 'meandev';
elseif ($langpref == 'lv.php')
	$translator = 'Munky';
elseif ($langpref == 'it.php')
	$translator = 'Skc';
elseif ($langpref == 'hr.php')
	$translator = 'atghoust';
elseif ($langpref == 'pl.php')
	$translator = 'Leszek Soltys<br />Bogumił Cieniek';
elseif ($langpref == 'fa.php')
	$translator = 'heam';
elseif ($langpref == 'fi.php')
	$translator = 'maxtuska';
elseif ($langpref == 'sl.php')
	$translator = 'Evelina';
elseif ($langpref == 'sk.php')
	$translator = 'greppi';

//Then display, if language is not English
if ($langpref != 'en.php') {
?>
	<p>
		<span class="kop2"><?php echo $lang['credits']['translation'].' ('.$lang.')'; ?></span>
		<br />
		<?php echo $translator; ?>
	</p>
<?php
}
?>
<p>
	<span class="kop2"><?php echo $lang['credits']['more']; ?></span>
	<br />
	<a href="http://tinymce.moxiecode.com" target="_blank">MoxieCode</a>, <?php echo $lang['credits']['tinymce']; ?>
	<br />
	<a href="http://maxg.info" target="_blank">Maxg</a>, <?php echo $lang['credits']['maxgtar']; ?>
	<br />
	<a href="http://www.dolem.com/lytebox" target="_blank">Markus F. Hay</a>, <?php echo $lang['credits']['lytebox']; ?>
	<br />
	<a href="http://tango.freedesktop.org" target="_blank">The Tango Desktop Project</a>, <?php echo $lang['credits']['tango']; ?>
	<br />
	<a href="http://www.famfamfam.com/lab/icons/silk/" target="_blank">Mark James</a>, <?php echo $lang['credits']['slik']; ?>
</p>