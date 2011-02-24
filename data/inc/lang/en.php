<?php
/*
This is a pluck language file. You can find pluck at http://www.pluck-cms.org/.
If you want to help us, please start a new translation on http://www.pluck-cms.org/dev/translate/
The translation will be included in the next release of pluck.

Note: if you translate, please note the use of capitals: use them sparely.

pluck is licensed under the GNU General Public License, and thus open source.
This language file is also licensed under the GPL.
See docs/COPYING for the full license text.

------------------------------------------------
Language		English
------------------------------------------------
*/

//Name of the language.
$language = 'English';

//----------------
//Translation data.

//General
$lang['general']['404']            = '404: not found';
$lang['general']['not_found']      = 'This page could not be found.';
$lang['general']['copyright']      = 'pluck is available under the terms of the <a href="http://www.gnu.org/licenses/gpl.html" target="_blank">GNU General Public License</a>.';
$lang['general']['save']           = 'Save';
$lang['general']['save_exit']      = 'Save and Exit';
$lang['general']['cancel']         = 'Cancel';
$lang['general']['other_options']  = 'other options';
$lang['general']['title']          = 'title';
$lang['general']['contents']       = 'contents';
$lang['general']['choose']         = 'Choose...';
$lang['general']['back']           = 'back';
$lang['general']['upload_failed']  = 'Upload failed.';
$lang['general']['admin_center']   = 'administration center';
$lang['general']['changing_rank']  = 'Changing rank...';
$lang['general']['insert']         = 'insert';
$lang['general']['insert_module']  = 'insert module';
$lang['general']['insert_image']   = 'insert image';
$lang['general']['dont_display']   = 'Don\'t display';
$lang['general']['upload']         = 'Upload';
$lang['general']['change_title']   = 'change title';
$lang['general']['images']         = 'images';
$lang['general']['not_valid_file'] = 'Install failed: the file you specified is not a valid file.';
$lang['general']['none']           = 'none';
$lang['general']['description']    = 'description';
$lang['general']['nothing_yet']    = 'nothing yet...';
$lang['general']['send']           = 'Send';
$lang['general']['name']           = 'Name:';
$lang['general']['email']          = 'Email:';
$lang['general']['message']        = 'Message:';
$lang['general']['website']        = 'Website:';

//Login
$lang['login']['not']               = 'not logged in';
$lang['login']['not_message']       = 'You are not logged in! A moment, please...';
$lang['login']['title']             = 'log in';
$lang['login']['password']          = 'password';
$lang['login']['correct']           = 'Password correct. Logging you in...';
$lang['login']['incorrect']         = 'Password incorrect.';
$lang['login']['too_many_attempts'] = 'You have exceeded the number of login attempts. Please wait 5 minutes before logging in again.';
$lang['login']['log_out']           = 'log out';

//Install
$lang['install']['not']          = 'not installed';
$lang['install']['not_message']  = 'pluck hasn\'t been installed yet. A moment, please...';
$lang['install']['already']      = 'pluck has already been installed. A moment, please...';
$lang['install']['title']        = 'installation';
$lang['install']['welcome']      = 'Welcome! Before you can setup your new website, you have to install pluck.';
$lang['install']['start']        = 'Start the installation...';
$lang['install']['step_1']       = 'step 1';
$lang['install']['step_2']       = 'step 2';
$lang['install']['step_3']       = 'step 3';
$lang['install']['writable']     = 'Check if the displayed files and directories are writable, by clicking on the link "Refresh". If you\'re sure the files are writable, you can proceed to the next step.';
$lang['install']['good']         = 'Good';
$lang['install']['refresh']      = 'Refresh';
$lang['install']['proceed']      = 'Proceed...';
$lang['install']['homepage']     = 'Here you can edit the homepage of your website. Choose a title and edit the contents.';
$lang['install']['success']      = 'pluck has been successfully installed!';
$lang['install']['manage']       = 'manage your website';
$lang['install']['general_info'] = 'Please give some general information about you and your website.';

//Update
$lang['update']['up_to_date'] = 'pluck is up-to-date';
$lang['update']['available']  = 'update available';
$lang['update']['urgent']     = '<strong>urgent</strong> update available';
$lang['update']['failed']     = 'update check failed';

//Trashcan
$lang['trashcan']['title']          = 'trashcan';
$lang['trashcan']['items_in_trash'] = 'items in trashcan';
$lang['trashcan']['move_to_trash']  = 'move item to trashcan';
$lang['trashcan']['moving_item']    = 'Moving item to trashcan...';
$lang['trashcan']['same_name']      = 'The item could not be moved to the trashcan: the trashcan contains an item with the same name.';
$lang['trashcan']['message']        = 'Deleted items are listed here. You can take a look at them, restore them or delete them from the trashcan.';
$lang['trashcan']['empty']          = 'empty trashcan';
$lang['trashcan']['empty_confirm']  = 'Are you sure you want to empty the trashcan? Please note that all items will be lost.';
$lang['trashcan']['view_item']      = 'view item';
$lang['trashcan']['delete_item']    = 'delete item from trashcan';
$lang['trashcan']['deleting']       = 'Deleting item from trashcan...';
$lang['trashcan']['restore_item']   = 'restore item';
$lang['trashcan']['restoring']      = 'Restoring item from trashcan...';
$lang['trashcan']['same_page_name'] = 'The page could not be restored from the trashcan: there is already a page with the same name.';

//Start
$lang['start']['title']   = 'start';
$lang['start']['welcome'] = 'Welcome to the administration center of pluck.';
$lang['start']['manage']  = 'Here you can manage your website. Choose a link in the menu at the top of your screen.';
$lang['start']['more']    = 'more...';
$lang['start']['website'] = 'take a look at your website';
$lang['start']['result']  = 'take a look at the result';
$lang['start']['people']  = 'all the people who helped develop pluck';
$lang['start']['help']    = 'need help?';
$lang['start']['love']    = 'we\'d love to help you';

//Credits
$lang['credits']['title']          = 'credits';
$lang['credits']['message']        = 'Our thanks goes to all the following people, for helping in the development of pluck.';
$lang['credits']['project_leader'] = 'project leader';
$lang['credits']['developers']     = 'main developers';
$lang['credits']['contributions']  = 'contributions';
$lang['credits']['translation']    = 'translation';
$lang['credits']['more']           = 'more thanks';
$lang['credits']['tinymce']        = 'for making the excellent TinyMCE-editor used in pluck';
$lang['credits']['maxgtar']        = 'for making MaxgTar, used in the automatic theme and module installer';
$lang['credits']['lytebox']        = 'for developing LyteBox, used in pluck to serve the images in your albums with flair';
$lang['credits']['tango']          = 'for designing the wonderful icons used in the pluck administration center';
$lang['credits']['slik']           = 'for designing the "Silk" icons, also used in pluck';

//Page
$lang['page']['title']        = 'pages';
$lang['page']['message']      = 'Here you can manage, edit and delete your pages.';
$lang['page']['change_order'] = 'change page order';
$lang['page']['top']          = 'This page already is on the top, so its rank can\'t be changed.';
$lang['page']['last']         = 'This page already is the last one, so its rank can\'t be changed.';

//Newpage and Editpage
$lang['page']['new']         = 'new page';
$lang['page']['view']        = 'view page';
$lang['page']['edit']        = 'edit page';
$lang['page']['items']       = 'These items are ready to be implemented in this page:';
$lang['page']['insert_link'] = 'insert link';
$lang['page']['options']     = 'other options related to the page';
$lang['page']['in_menu']     = 'show page in menu';
$lang['page']['sub_page']    = 'sub-page of';
$lang['page']['name_exists'] = 'A page with the same title already exists.<br />Please choose a new title.';
$lang['page']['no_title']    = 'Please choose a title for your page.';

//Editmeta
$lang['editmeta']['title']    = 'edit page information';
$lang['editmeta']['message']  = 'Here you can enter some information about this page, to get better results in search engines.';
$lang['editmeta']['keywords'] = 'keywords';
$lang['editmeta']['comma']    = 'seperated by a comma';
$lang['editmeta']['changing'] = 'Changing page information...';

//Images
$lang['images']['title']    = 'manage images';
$lang['images']['message']  = 'Here you can upload your images, which you can put on your webpages later. There are three supported file formats: JPG, PNG and GIF.';
$lang['images']['uploaded'] = 'uploaded images';
$lang['images']['name']     = 'Name:';
$lang['images']['size']     = 'Size:';
$lang['images']['type']     = 'Type:';
$lang['images']['bytes']    = 'bytes';
$lang['images']['success']  = 'Upload successfull!';

//Modules
$lang['modules']['title']   = 'modules';
$lang['modules']['message'] = 'Pluck has a variety of modules available, which you can use to extend your website with dynamic content.';

//Options
$lang['options']['title']          = 'options';
$lang['options']['message']        = 'Here you can configure pluck so it suits your wishes and taste.';
$lang['options']['settings_descr'] = 'change general settings like the title of your website and your email address';
$lang['options']['modules_descr']  = 'manage modules and include them in your website';
$lang['options']['modules_sett_descr']  = 'change module configuration settings';
$lang['options']['themes_descr']   = 'change the look and feel of your website';
$lang['options']['lang_descr']     = 'choose the language that will be used by pluck';
$lang['options']['pass_descr']     = 'it is a good idea to change your password regularly';

//Settings
$lang['settings']['title']             = 'general settings';
$lang['settings']['message']           = 'Change general settings like the title of your website and your email address here.';
$lang['settings']['choose_title']      = 'choose the title for your website here';
$lang['settings']['email']             = 'email';
$lang['settings']['email_descr']       = 'your email address will be used to allow your visitors to contact you through an email form';
$lang['settings']['xhtml_mode']        = 'Turn on the XHTML Compatibility Mode (may be slower)';
$lang['settings']['changing_settings'] = 'Saving settings...';
$lang['settings']['fill_name']         = 'You have to fill in a name for your website, it can\'t be empty.';
$lang['settings']['email_invalid']     = 'The emailaddress you entered is invalid!';

//Modules_manage
$lang['modules_manage']['title']             = 'manage modules';
$lang['modules_manage']['message']           = 'Manage your modules here. Remove unused modules, or start your search for new modules to enrich your website with new functionality. You can also add modules to your website, by choosing <i>Add modules to website</i>.';
$lang['modules_manage']['add']               = 'Add modules to website...';
$lang['modules_manage']['install']           = 'Install a module...';
$lang['modules_manage']['information']       = 'module information';
$lang['modules_manage']['uninstall']         = 'uninstall module';
$lang['modules_manage']['uninstall_confirm'] = 'Are you sure you want to uninstall this module? Please note that the settings of the module will not be lost.';
$lang['modules_manage']['version']           = 'version';
$lang['modules_manage']['author']            = 'author';
$lang['modules_manage']['website']           = 'website';
$lang['modules_manage']['not_compatible']    = 'This module is not compatible with your version of pluck, and has been disabled.';

//Modules_settings
$lang['modules_settings']['title']             = 'module settings';
$lang['modules_settings']['message']           = 'Change the configuration settings of your modules.';

//Modules_addtosite
$lang['modules_addtosite']['title']        = 'add modules to website';
$lang['modules_addtosite']['message']      = 'Configure here in which areas on your websites modules will be displayed. These settings are theme specific: if you change to another theme, you will have to set this again. Please also note that these settings will apply for all pages on your website.';
$lang['modules_addtosite']['choose_order'] = 'Choose in which order the modules should be displayed.';

//Installmodule
$lang['modules_install']['title']   = 'install modules';
$lang['modules_install']['message'] = 'Here you can install new modules. Please make sure you have downloaded a module first.';
$lang['modules_install']['too_big'] = 'The module-file is too big; 2MB is the limit.';
$lang['modules_install']['success'] = 'The module has been installed successfully.';

//Theme
$lang['theme']['title']  = 'choose theme';
$lang['theme']['choose'] = 'Here you can choose which of the installed themes you want to use.';
$lang['theme']['saved']  = 'The theme settings have been saved.';

//Themeuninstall
$lang['theme_uninstall']['title']             = 'uninstall theme';
$lang['theme_uninstall']['message']           = 'Here you can uninstall your themes. The currently active theme is not listed here.';
$lang['theme_uninstall']['uninstall_confirm'] = 'Are you sure you want to uninstall this theme?';

//Themeinstall
$lang['theme_install']['title']         = 'install theme';
$lang['theme_install']['message']       = 'Here you can install new themes. Please make sure you\'ve downloaded a theme first.';
$lang['theme_install']['return']        = 'return to the <a href="?action=theme">theme page</a>';
$lang['theme_install']['not_supported'] = 'theme and module installation is not supported on this server, you will have to do it manually.';
$lang['theme_install']['success']       = 'theme installed';
$lang['theme_install']['too_big']       = 'The theme file is too big; 1MB is the maximum.';

//Language
$lang['language']['title']  = 'language settings';
$lang['language']['choose'] = 'Choose the language that will be used by pluck.';
$lang['language']['saved']  = 'The languagesettings have been saved.';

//Changepass
$lang['changepass']['title']       = 'change password';
$lang['changepass']['message']     = 'Here you can change the password you use to login to the <i>pluck</i> administrationcenter. It\'s a good idea to change your password regularly.';
$lang['changepass']['old']         = 'old password';
$lang['changepass']['new']         = 'new password';
$lang['changepass']['repeat']      = 'repeat new password';
$lang['changepass']['cant_change'] = 'Can\'t change your password, because the old password you entered isn\'t correct.';
$lang['changepass']['different']   = 'You entered two different passwords!';
$lang['changepass']['empty']       = 'Your new password can\'t be empty.';
$lang['changepass']['changed']     = 'Password has been changed.';

//Albums.
$lang['albums']['title']          = 'albums';
$lang['albums']['descr']          = 'use albums to show your visitors your favourite photos and images';
$lang['albums']['message']        = 'Here you can manage your albums. Use albums to show your visitors your favourite photos and images. Insert the albums in your page(s) by choosing "insert album" when editing a page.';
$lang['albums']['edit_albums']    = 'edit albums';
$lang['albums']['new_album']      = 'new album';
$lang['albums']['choose_name']    = 'choose a name for your new album first, then click "save"';
$lang['albums']['delete_album']   = 'delete album';
$lang['albums']['edit_album']     = 'edit album';
$lang['albums']['album_message1'] = 'Use this page to add, delete and edit images in your album. JPG, PNG and GIF images are supported.';
$lang['albums']['album_message2'] = 'upload a new image here. choose a title and a description, and choose the quality at which the images should be processed. The higher the quality, the higher the filesize.';
$lang['albums']['edit_images']    = 'edit images';
$lang['albums']['new_image']      ='new image';
$lang['albums']['quality']        = 'quality (1-100)';
$lang['albums']['edit_image']     = 'edit image';
$lang['albums']['doesnt_exist']   = 'The specified album doesn\'t exist.';
$lang['albums']['name_exist']     = 'There is already an album with that name.';
$lang['albums']['image_exist']    = 'There is already an image with that name.';
$lang['albums']['change_order']   = 'change image order';
$lang['albums']['already_top']    = 'This image already is on the top, so its rank can\'t be changed.';
$lang['albums']['already_last']   = 'This image already is the last one, so its rank can\'t be changed.';
$lang['albums']['delete_image']   = 'delete image';
$lang['albums']['image_width']    = 'The width in pixels the images will be resized to. Choose 0 to disable automatic resizing.';
$lang['albums']['thumb_width']    = 'The width in pixels the thumbnails will be resized to. Cannot be disabled.';
$lang['albums']['settings_error'] = 'The width of the resized images should be numeric.';

//Blog.
$lang['blog']['title']                  = 'blog';
$lang['blog']['main_message']           = 'Here, you can make new posts to add to your blog. Posts will be automatically sorted on date.';
$lang['blog']['descr']                  = 'use a blog to post news or write articles for your visitors';
$lang['blog']['categories']             = 'categories';
$lang['blog']['category']               = 'category';
$lang['blog']['choose_cat']             = 'Choose category...';
$lang['blog']['no_cat']                 = 'No category';
$lang['blog']['new_cat']                = 'new category';
$lang['blog']['new_cat_message']        = 'choose a name for your new category first, then click "save"';
$lang['blog']['delete_cat']             = 'delete category';
$lang['blog']['posts']                  = 'existing posts';
$lang['blog']['new_post']               = 'write new post';
$lang['blog']['edit_post']              = 'edit post';
$lang['blog']['delete_post']            = 'delete post';
$lang['blog']['at']                     = 'at';
$lang['blog']['in']                     = 'in';
$lang['blog']['posted_by']              = 'posted by';
$lang['blog']['reactions']              = 'reactions';
$lang['blog']['reaction']               = 'reaction';
$lang['blog']['edit_reactions']         = 'edit reactions';
$lang['blog']['edit_reactions_message'] = 'Here, you can edit the reactions of your visitors on the post.';
$lang['blog']['edit_reaction']          = 'edit reaction';
$lang['blog']['delete_reaction']        = 'delete reaction';
$lang['blog']['name']                   = 'name';
$lang['blog']['email']                  = 'email';
$lang['blog']['website']                = 'website';
$lang['blog']['message']                = 'message';
$lang['blog']['html_not_allowed']       = 'HTML-code is not allowed.';
$lang['blog']['no_reactions']           = 'no reactions';
$lang['blog']['allow_reactions']        = 'Allow visitors to post reactions.';
$lang['blog']['read_more']              = 'read more';
$lang['blog']['truncate_posts']         = 'The number of characters blog posts will be truncated to. Choose 0 to disable truncation.';
$lang['blog']['truncate_error']         = 'You should choose a numeric value for blog post truncation.';

//Contact form.
$lang['contactform']['module_name']  = 'contact form';
$lang['contactform']['module_intro'] = 'with a contact form, you can allow your visitors to send you a message';
$lang['contactform']['fields']       = 'You didn\'t fill in all fields correctly.';
$lang['contactform']['email_title']  = 'Message from your website from';
$lang['contactform']['been_send']    = 'Your message has been send succesfully.';
$lang['contactform']['not_send']     = 'Your message could not be send, an error occurred.';

//View site link
$lang['viewsite']['module_name']  = 'view site link';
$lang['viewsite']['module_intro'] = 'Created to show the new hooks. Adds a direct link to the site in the admin menu.';
$lang['viewsite']['message']      = 'view site';
?>