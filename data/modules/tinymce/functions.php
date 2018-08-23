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

function tinymce_display_code() {
	global $lang, $module_list; ?>
	<script type="text/javascript" src="<?php echo TINYMCE_DIR; ?>/tinymce.min.js"></script>
	<?php run_hook('tinymce_scripts'); ?>
	<script type="text/javascript">
		tinymce.init({
  			selector: 'textarea:not(.mceNoEditor)',
			height: 600,
			menubar:false,
			setup: function(editor) {
				editor.addButton('mybutton', {
					type: 'menubutton',
					text: 'Pluck Actions',
					icon: false,
					menu: [<?php if (!isset($_GET['module']) || $_GET['module'] != 'blog'){
					?>{
						text: '<?php echo $lang['general']['insert_module']; ?>',
						menu: [<?php
							foreach ($module_list as $module) {
							if (file_exists('data/modules/'.$module.'/'.$module.'.site.php'))
								require_once ('data/modules/'.$module.'/'.$module.'.site.php');
							}
							unset($module);	
							foreach ($module_list as $module) {
								if (module_is_compatible($module) && function_exists($module.'_theme_main')) { ?>
									{
										text: '<?php echo sanitize($module); ?>',
										onclick: function() {
											editor.insertContent('<div class="module_<?php echo str_replace(' ', '_',$module);  ?>">{pluck show_module(<?php echo $module; ?>)}</div>');
										},

										<?php //Check if we need to display categories for the module
										$module_info = call_user_func($module.'_info');
										if (isset($module_info['categories']) && is_array($module_info['categories'])) { ?>
										menu:[
										<?php 
											foreach ($module_info['categories'] as $category){ ?>
												{
													text: '<?php echo sanitize($category); ?>',
													onclick: function() {
														editor.insertContent('<div class="module_<?php $hulp = sanitize($module.','.$category); echo str_replace(' ', '_', $hulp);  ?>">{pluck show_module(<?php echo sanitize($module.','.$category); ?>)}</div>');
													}
												},
											<?php } ?> {}]
									<?php } ?>
									 }, <?php 
									
								}
							} ?> 
						,{}]        
					}, <?php } ?>{
					text: '<?php echo $lang['general']['insert_image']; ?>',
//load images here
				<?php 
					$images = read_dir_contents('images', 'files');
					if ($images) {
					?>
					menu: [
						<?php
						natcasesort($images);
						foreach ($images as $image) { ?>
						{

							text: '<?php echo sanitize($image); ?>',
							onclick: function() {
								editor.insertContent('<img src="images/<?php echo str_replace('\'', '%27', $image);?>" alt="" \/>');
							}
						},
					<?php }
						?>{}]<?php        
					}
					unset($image);
?>
//end load
}, {
					text: '<?php echo $lang['general']['insert_page']; ?>',
//load pages here
				<?php 	
				$pages = get_pages();
				if ($pages) { ?>
					menu: [ <?php
					foreach ($pages as $page) {
						require PAGE_DIR.'/'.$page;
						$page = get_page_seoname($page);
						preg_match_all('|\/|', $page, $indent);
						?>{
							text: '<?php echo sanitize($title); ?>',
							onclick: function() {
								editor.insertContent('<a href="?file=<?php echo str_replace('\'', '%27', $page); ?>" title="<?php echo sanitize($title); ?>"><?php echo sanitize($title); ?><\/a>');
							}
						},
						<?php
					}
					unset($page);
				}?>{}]
//end load
}, {
					text: '<?php echo $lang['general']['insert_file']; ?>',
//load files here
				<?php 	
				$files = read_dir_contents('files', 'files');
				if ($files) { ?>
					menu: [ <?php
						natcasesort($files);
						foreach ($files as $file) {
						?>{
							text: '<?php echo sanitize($file); ?>',
							onclick: function() {
								editor.insertContent('<a href="files/<?php echo sanitize($file); ?>" title="<?php echo sanitize($file); ?>"><?php echo sanitize($file); ?><\/a>');
							}
						},
						<?php
					}
					unset($file);
				}?>{}]
//end load
}]
});
},
plugins: [
"advlist autolink autosave link image lists charmap print preview hr anchor pagebreak",
"searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
"table contextmenu directionality emoticons template textcolor paste textcolor colorpicker textpattern"
],
toolbar1: "bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | styleselect formatselect fontselect fontsizeselect mybutton",
toolbar2: "cut copy paste | searchreplace | bullist numlist | outdent indent blockquote | undo redo | link unlink anchor image media code | insertdatetime preview | forecolor backcolor",
toolbar3: "table | hr removeformat | subscript superscript | charmap emoticons | print fullscreen | ltr rtl"

});	</script>
	<?php
}
?>
