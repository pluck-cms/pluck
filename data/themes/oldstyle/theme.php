<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php theme_meta(); ?>
</head>

<body>
<div class="head">
	<div class="header">
		<div class="headerkop"><?php theme_sitetitle(); ?></div>
		<div class="menu">
			<?php theme_menu('<a href="#file">#title</a> || '); ?>
		</div>
	</div>

	<div class="content">
		<div class="kop"><?php theme_pagetitle(); ?></div><br />
		<div class="txt">
			<?php theme_content(); ?>
			<?php theme_module("main"); ?>
			<div class="footer">
				<?php theme_module("footer"); ?>
				>> <a href="login.php">admin</a>
				<br />powered by <a href="http://www.pluck-cms.org">pluck</a>
			</div>
		</div>
	</div>
</div>
</body>
</html>