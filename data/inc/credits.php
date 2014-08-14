<?php
/*
 * This file is part of pluck, the easy content management system
 * Copyright (c) pluck team
 * http://www.pluck-cms.org

 * Pluck is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * See docs/COPYING for the complete license.
*/

//Make sure the file isn't accessed directly.
defined('IN_PLUCK') or exit('Access denied!');
?>

<p>
	<strong><?php echo $lang['credits']['message']; ?></strong>
</p>
<p>
	<span class="kop2"><?php echo $lang['credits']['project_leader']; ?></span>
	<a href="http://www.somp.nl" target="_blank">Sander Thijsen</a>
</p>
<p>
	<span class="kop2"><?php echo $lang['credits']['developers']; ?></span>
	<a href="http://spirit55555.dk" target="_blank">Anders Jørgensen</a>
	<br />
	<a href="http://www.ekyo.pl" target="_blank">Bogumił Cieniek</a>
	<br />
	<a href="http://www.somp.nl" target="_blank">Sander Thijsen</a>

</p>
<p>
	<span class="kop2"><?php echo $lang['credits']['contributions']; ?></span>
	Kristaps Ancāns
	<br />
	Callan Barrett
	<br />
	Dennis Sewbarath
	<br />
	Kevin Zettler
</p>
<?php
//Translation
//-----------
//First determine who's the translator
switch($langpref) {
	case 'bg.php':
		$translator = 'Aleksander Dimov';
		break;
	case 'ca.php':
		$translator = 'Cesc Llopart';
		break;
	case 'da.php':
		$translator = 'Thomas Andresen<br />Lone Hansen';
		break;
	case 'de.php':
		$translator = 'Max Effenberger<br />Dennis Sewberath<br />stoffal';
		break;
	case 'el.php':
		$translator = 'swiss_blade';
		break;
	case 'es.php':
		$translator = 'Cesc Llopart';
		break;
	case 'fa.php':
		$translator = 'heam';
		break;
	case 'fi.php':
		$translator = 'maxtuska';
		break;
	case 'fr.php':
		$translator = 'zigzagbe<br />Dominique Heimler';
		break;
	case 'he.php':
		$translator = 'Erez Wolf';
		break;
	case 'hr.php':
		$translator = 'atghoust';
		break;
	case 'hu.php':
		$translator = 'Wix';
		break;
	case 'it.php':
		$translator = 'Skc';
		break;
	case 'ja.php':
		$translator = 'Shi-no';
		break;
	case 'lt.php':
		$translator = 'Mindaugas Salamachinas';
		break;
	case 'lv.php':
		$translator = 'Munky';
		break;
	case 'nl.php':
		$translator = 'Sander Thijsen';
		break;
	case 'no.php':
		$translator = 'John Erik Kristensen';
		break;
	case 'pl.php':
		$translator = 'Leszek Soltys<br />Bogumił Cieniek';
		break;
	case 'pt.php':
		$translator = 'Marco Paulo Ferreira<br />Hélio Carrasqueira';
		break;
	case 'pt_br.php':
		$translator = 'Gilnei Moraes<br />Henrique Gogó<br />sarkioja';
		break;
	case 'ro.php':
		$translator = 'Adi Roiban';
		break;
	case 'ru.php':
		$translator = 'Tkachev Vasily<br />Sergey Shutov';
		break;
	case 'sk.php':
		$translator = 'greppi';
		break;
	case 'sl.php':
		$translator = 'Evelina';
		break;
	case 'sv.php':
		$translator = 'Carl Jansson';
		break;
	case 'th.php':
		$translator = 'meandev';
		break;
	case 'tr.php':
		$translator = 'Gürkan Gür';
		break;
	case 'zh-cn.php':
		$translator = '';
		break;
	case 'zh-tw.php':
		$translator = '';
		break;
}

//Then display, if language is not English
if ($langpref != 'en.php') {
?>
	<p>
		<span class="kop2"><?php echo $lang['credits']['translation'].' ('.$language.')'; ?></span>
		<?php echo $translator; ?>
	</p>
<?php
}
?>
<p>
	<span class="kop2"><?php echo $lang['credits']['more']; ?></span>
	<a href="http://tinymce.moxiecode.com" target="_blank">MoxieCode</a>, <?php echo $lang['credits']['tinymce']; ?>
	<br />
	<a href="http://maxg.info" target="_blank">Maxg</a>, <?php echo $lang['credits']['maxgtar']; ?>
	<br />
	<a href="https://plus.google.com/+MdShahadatHossainKhan/posts" target="_blank">Md. Shahadat Hossain Khan</a>, Unzip PHP Class
	<br />
	<a href="http://www.dolem.com/lytebox" target="_blank">Markus F. Hay</a>, <?php echo $lang['credits']['lytebox']; ?>
	<br />
	<a href="http://www.gnome.org" target="_blank">Gnome</a> &amp; <a href="http://tango.freedesktop.org" target="_blank">Tango</a> projects, <?php echo $lang['credits']['tango']; ?>
	<?php /* Silk icons not used in pluck anymore
	<br />
	<a href="http://www.famfamfam.com/lab/icons/silk/" target="_blank">Mark James</a>, <?php echo $lang['credits']['slik']; ?> */
	?>
</p>