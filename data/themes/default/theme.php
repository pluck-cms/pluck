<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php theme_meta(); ?>
</head>

<body>

<div id="container"><div id="hd">
<h2><?php theme_sitetitle(); ?></h2>
<ul>
<?php theme_menu('<li><a href="#file">#title</a></li>','<li><a href="#file" class="act">#title</a></li>'); ?>
</ul>
</div>

<div id="ct"><h1><?php theme_pagetitle(); ?></h1>

<?php theme_content(); ?>
<?php theme_module("main"); ?>

</div>
<div id="ft">
<?php theme_module("footer"); ?>
<a href="login.php">admin</a> | powered by <a href="http://www.pluck-cms.org">pluck</a>
</div></div>		
</body>
</html>