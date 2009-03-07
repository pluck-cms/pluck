<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php theme_meta(); ?>
</head>

<body>
<div id="container">
	<div id="top"></div>
	<div id="mainblok">
		<div id="blok1">
			<div class="binnen">          
				<h3 class="headtext" title="<?php theme_sitetitle(); ?>"><?php theme_sitetitle(); ?></h3>
				<ul class="menu1">
					<?php theme_menu('<li class="menu1"><a href="#file">#title</a></li>'); ?>
				</ul>
			</div>
		</div>

		<div id="blok2">
			<div class="binnen">
				<h1 title="<?php theme_pagetitle(); ?>"><?php theme_pagetitle(); ?></h1>
			</div>

			<div class="txt">
				<?php theme_content(); ?>
				<?php theme_area("main"); ?>
			</div>
		</div> 
	</div>
	
	<div id="footer">
		>> <a href="login.php">admin</a>
		<br />powered by <a href="http://www.pluck-cms.org">pluck</a>
	</div>  
</div>			
</body> 
</html>