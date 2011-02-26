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
Language Romanian
Translators: Adi Roiban https://launchpad.net/~adiroiban
------------------------------------------------
*/

//Name of the language
$language = 'Romanian';

//----------------
//Translation data

//Frontend
$lang['general']['404'] = '404: negăsit.';
$lang['general']['not_found'] = 'Această pagină nu a putut fi găsită.';

//General data
$lang_pluck = 'Pluck';
$lang['general']['copyright'] = 'pluck &copy; 2005-2008 <a href="http://www.somp.nl" target="_blank">somp</a>. Pluck este disponibil sub termenii licenței GNU General Public License.';
$lang['install']['not'] = 'Nu este instalat';
$lang['login']['not'] = 'Nu sunteți autentificat';
$lang['login']['not_message'] = 'Nu sunteți autentificat! . Așteptați o clipă...';

//Login page
$lang['login']['title'] = 'Autentificare';
$lang['install']['not_message'] = 'Pluck nu a fost instalat încă. Așteptați o clipă...';
$lang['login']['password'] = 'Parola';
$lang_login4 = 'Autentifică';
$lang['login']['correct'] = 'Parolă corectă. Se autentifică...';
$lang['login']['incorrect'] = 'Parolă incorectă. Așteptați o clipă...';

//Install page
$lang['install']['title'] = 'Instalarea';
$lang['install']['already'] = 'Pluck a fost instalat deja. Așteptați o clipă...';
$lang['install']['welcome'] = 'Bun venit! Înainte de a configura siteul, trebui să instalați Pluck.';
$lang['install']['start'] = 'Începe instalarea...';
$lang['install']['step_1'] = 'Pasul 1';
$lang['install']['step_2'] = 'Pasul 2';
$lang['install']['step_3'] = 'Pasul 3';
$lang['install']['writable'] = 'Verificați că fișierele afișate pot fi scrise. Puteți actualiza pagina, apăsând pe butonul „Reîncarcă”. În cazul în care sunteți siguri că fișierele pot fi scrise, puteți trece la următorul pas.';
$lang['install']['good'] = 'Corect';
$lang_install9 = 'Greșit';
$lang['install']['refresh'] = 'Reîncarcă';
$lang['install']['proceed'] = 'Continuă...';
$lang['general']['save'] = 'Salvează';
$lang['general']['cancel'] = 'Anulează';
$lang_install15 = 'Trebuie să introduceți un titlu!';
$lang['install']['homepage'] = 'De aici puteți modifica pagina principală a siteului. Alegeți un titlu și adăugați conținut.';
$lang['general']['title']= 'Titlu';
$lang['general']['contents'] = 'Conținut';
$lang['install']['success'] = 'Pluck a fost instalat cu succes!';
$lang['start']['website'] = 'Vizualizați siteul';
$lang['start']['result'] = 'Aruncați o privire peste rezultat';
$lang['general']['admin_center'] = 'Centrul de administrare';
$lang['install']['manage'] = 'Administrați siteul dumneavoastră';

//Admincenter:Titles
$lang['start']['title'] = 'Start';
$lang['page']['title'] = 'Pagini';
$lang['options']['title'] = 'Opțiuni';
$lang['login']['log_out'] = 'Ieșire';
$lang['general']['change_title'] = 'Modificare titlu';
$lang['changepass']['title'] = 'Schimbare parolă';
$lang_kop11 = 'Pagină nouă';
$lang['albums']['delete_image'] = 'Șterge imaginea';
$lang['language']['title'] = 'Configurări limbă';

//Admincenter:: Start
$lang['start']['welcome'] = 'Bun venit la pagina de administrare Pluck!';
$lang['start']['help'] = 'Aveți nevoie de ajutor?';
$lang['start']['love'] = 'Suntem bucuroși să vă putem ajuta.';

//Admincenter:: Pages
$lang['page']['message'] = 'De aici puteți administra, șterge sau creea pagini.';
$lang['page']['new'] = 'Pagină nouă';
$lang['page']['edit'] = 'Modificare pagină';
//Admincenter:: Pages:: Deletepage
$lang_page5 = 'Se șterge...';
//Admincenter:: Pages:: Rightmenu
$lang['general']['insert'] = 'Introdu';

//Admincenter:: Images
$lang['images']['message'] = 'De aici puteți încărca imagini ce pot fi introduce mai târziu în paginile dumneavoastră. Sunt suportate trei tipuri de imagini: JPG, PNG și GIF.';
$lang['general']['upload_failed'] = 'Încărcarea a eșuat.';
$lang['images']['name'] = 'Nume:';
$lang['images']['size'] = 'Mărime:';
$lang['images']['type'] = 'Tip:';
$lang['images']['success'] = 'Încărcare reușită!';
$lang['images']['uploaded'] = 'Imagini încărcate';
$lang['general']['upload'] = 'Încărcare imagine';
$lang_image9 = 'Start';
$lang_image11 = 'Această imagine nu poate fi ștearsă.';
$lang_image12 = 'Nu s-a putut șterge această imagine. Verificați permisiunile dosarului și a fișierului.';

//Admincenter:: Options
$lang['options']['message'] = 'De aici puteți configura Pluck pentru a corespunde dorințelor dumneavoastră.';
$lang_options2 = 'De aici puteți schimba numele siteului';
$lang['options']['themes_descr'] = 'Schimbați aspectul și modul de afișare al siteului';
$lang['options']['pass_descr'] = 'Se recomandă schimbarea periodică a parolei';
$lang['options']['lang_descr'] = 'Alegeți limba folosită de Pluck';

//Admincenter:: Options:: Sitetitle
$lang['settings']['fill_name'] = 'Trebuie să introduceți un nume pentru site. Acest câmp nu poate fi lăsat necompletat.';

//Admincenter:: Options:: Siteinfo
$lang['editmeta']['keywords'] = 'Cuvinte cheie';
$lang['editmeta']['comma'] = 'despărțite de virgulă';

//Admincenter:: Options:: Changepass
$lang['changepass']['message'] = 'De aici puteți schimba parola folosită pentru autentificarea pe pagina de administrare <i>Pluck</i>. Se recomandă schimbarea perioadică a parolei.';
$lang['changepass']['old'] = 'Parola veche';
$lang['changepass']['new'] = 'Parola nouă';
$lang['changepass']['cant_change'] = 'Nu s-a putut schimba parola: vechea parolă introdusă nu este corectă.';
$lang['changepass']['changed'] = 'Parola a fost schimbată.';

//Admincenter:: Options:: Languagesettings
$lang['language']['choose'] = 'Alegeți limba folosită în Pluck.';
$lang['general']['choose'] = 'Alegeți...';
$lang['language']['saved'] = 'Configurările limbii au fost salvate.';

//Admincenter:: Stats
//FIXME: Do we need these anymore?
$lang_stats1 = 'De aici puteți vizualiza unele informații despre vizitatorii siteului dumneavoastră. În josul pagini veți putea alege luna pentru care să fie afișate statisticile.';
$lang_stats2 = 'Statistici de la';
$lang_stats3 = 'Sisteme de operare';
$lang_stats4 = 'Navigatoare';
$lang_stats5 = 'Arhive';
$lang_stats6 = 'Alegeți unul dintre următoarele elemente:';
$lang_stats7 = 'Total';
$lang_stats8 = 'Accesări';
$lang_stats9 = 'Accesări totale';
$lang_stats10 = 'Accesări în această lună';

//VERSION 4.2 NEW
//---------------
$lang['theme']['title'] = 'Alegere aspect';
$lang['theme']['choose'] = 'De aici puteți alege care dintre temele instalate să fie folosite.';
$lang['theme']['saved'] = 'Configurările de aspect au fost salvate.';
$lang['page']['change_order'] = 'Schimbă ordinea pagini';
$lang['page']['top'] = 'This page already is on the top, so its rank can\'t be changed.';
$lang['general']['changing_rank'] = 'Changing rank...';
$lang['page']['last'] = 'This page already is the last one, so its rank can\'t be changed.';

//VERSION 4.3 NEW
//---------------
$lang['theme_install']['title'] = 'Instalare temă';
$lang['theme_install']['message'] = 'De aici puteți instala o nouă temă. Inainte de a continua asigurați-vă că ați descărcat un fișier cu tema.';
$lang['theme_install']['too_big'] = 'Fișierul temă este prea mare; Limata este 1MB.';
$lang_theme9 = 'Installation failed. The server probably doesn\'t have installed the php-zlib module. You can contact your systemadministrator and ask to install this module.';
$lang['theme_install']['success'] = 'Tema a fost instalată';
$lang['theme_install']['return'] = 'Înapoi pe <a href="?action=theme">pagina temei</a>';
$lang['general']['back'] = 'Înapoi';
$lang['settings']['email'] = 'Email';
$lang['settings']['email_descr'] = 'Adresa dumneavoastră de email va fi folosită pentru a permite vizitatorilor să vă contacteze prin intermediul formularului de contact';
$lang['changepass']['repeat'] = 'Reintroduceți parola';
$lang['install']['general_info'] = 'Introduceți câteva informații generale despre dumneavoastră și site.';
$lang['changepass']['different'] = 'Ați introdus două parole diferite !';
$lang_install29 = 'Pasul 4';
$lang['settings']['title'] = 'Configurări generale';
$lang_settings2 = 'Alegeți titlul siteului';
$lang['options']['settings_descr'] = 'Schimbați configurări generale precum titlul siteului sau adresa de email';
$lang['settings']['changing_settings'] = 'Se modifică configurările generale...';
$lang['general']['other_options'] = 'Alte opțiuni';
$lang['general']['name'] = 'Nume:';
$lang['general']['email'] = 'Adresă de email:';
$lang['general']['message'] = 'Mesaj:';
$lang['contactform']['fields'] = 'Nu ați introdus toate câmpurile obligatorii.';
$lang['contactform']['email_title'] = 'Mesaj de pe forumarul de contact';
$lang['contactform']['been_send'] = 'Mesajul dumneavoastră a fost trimis cu succes.';
$lang['contactform']['not_send'] = 'S-a produs o eroare iar mesajul dumneavoastră nu a putut fi trimis.';
$lang['general']['send'] = 'Trimite';
$lang['images']['title'] = 'Administrare imagini';
$lang['albums']['title'] = 'Albume';
$lang['albums']['message'] = 'Here you can manage your albums. Use albums to show your visitors your favourite photos and images. Insert the albums in your page(s) by choosing "insert album" when editing a page.';
$lang['albums']['edit_albums'] = 'Modificare albume';
$lang['albums']['new_album'] = 'Album nou';
$lang['albums']['choose_name'] = 'Alegeți un nume pentru noul album, apoi faceți clic pe „Salvează”';
$lang['albums']['delete_album'] = 'Ștergere album';
$lang['albums']['edit_album'] = 'Modificare album';
$lang['albums']['descr'] = 'Folosiți albumele pentru a afișa fotografiile și imaginile dumneavoastră';
$lang['albums']['album_message1'] = 'Folosiți această pagină pentru a adăuga, a șterge sau a modifica imagini din albumul dumneavoastră. Sunt suportate doar imagini <b>JPG</b>.';
$lang['albums']['edit_images'] = 'Modificare imagini';
$lang['albums']['new_image'] = 'Imagine nouă';
$lang['general']['description'] = 'Descriere';
$lang['albums']['quality'] = 'Calitate (1-100)';
$lang['albums']['album_message2'] = 'De aici puteți încărca o nouă imagine. Alegeți un titlu și o descriere precum și calitatea la care imaginea va fi salvată. O calitate mai mare va determina o dimensiune mai mare.';
$lang['general']['nothing_yet'] = 'Încă nimic...';
$lang['albums']['edit_image'] = 'Modificare imagine';
$lang_albums16 = 'Albumele nu sunt suportate pe acest server: foarte probabil din cauza faptului că modulul php-gd nu este instalat. Puteți contacta administratorul de sistem pentru a-i cere instalarea modulului.';
$lang_albums17 = 'Include albumul în această pagină:';

//VERSION 4.4 NEW
//---------------
$lang['modules']['title'] = 'Module';
$lang['modules']['message'] = 'Pluck dispune de o varietate de module pe care le puteți folosi pentru a extinde siteul introducând conținut dinamic.';
$lang['albums']['change_order'] = 'Schimbă ordinea imagini';
$lang['albums']['already_top'] = 'This image already is on the top, so its rank can\'t be changed.';
$lang['albums']['already_last'] = 'This image already is the last one, so its rank can\'t be changed.';
$lang['page']['in_menu'] = 'Afișează pagina în meniu';
$lang['page']['items'] = 'Aceste elemente sunt pregătite pentru a fi introduse în pagină:';
$lang['page']['insert_link'] = 'Introdu legătura';
$lang['editmeta']['title'] = 'Modifcă informațiile paginii';
$lang['editmeta']['message'] = 'De aici puteți adugă informații despre pagină, pentru a obține rezultate mai bune în motoarele de căutare.';
$lang['editmeta']['changing'] = 'Se modifică informațiile paginii...';
$lang['settings']['message'] = 'De aici puteți modifica configurări generale precum titlul siteului sau adresa email de contact.';
$lang['blog']['title'] = 'blog';
$lang['blog']['descr'] = 'use a blog to post news or write articles for your visitors';
$lang['blog']['categories'] = 'Categorii';
$lang['blog']['new_cat'] = 'Categorie nouă';
$lang['blog']['new_cat_message'] = 'Alegeți un nume pentru noua categorie, apoi faceți clic pe „Salveză”';
$lang['blog']['delete_cat'] = 'Ștergere categorie';
$lang_blog7 = 'Modificare categorie';
$lang['blog']['posts'] = 'Articole existente';
$lang['blog']['new_post'] = 'Scrie un nou articol';
$lang['blog']['edit_post'] = 'Modificare articol';
$lang['blog']['delete_post'] = 'Șterge articol';
$lang_blog14 = 'Adăuga în';
$lang['blog']['reactions'] = 'Reacții';
$lang_blog17 = 'Titlu:';
$lang['blog']['edit_reactions'] = 'Modificare reacții';
$lang['blog']['edit_reactions_message'] = 'De aici puteți modifica reacțiile vizitatorilor la articolele dumneavoastră.';

//VERSION 4.5 NEW
//---------------
$lang['blog']['delete_reaction'] = 'Șterge reacția';
$lang['albums']['doesnt_exist'] = 'Albumul specificat nu există.';
$lang['blog']['html_not_allowed'] = 'Nu este permis cod HTML.';
$lang['trashcan']['title'] = 'Coș de gunoi';
$lang['trashcan']['move_to_trash'] = 'Mută elementul la gunoi';
$lang['trashcan']['moving_item'] = 'Se mută elementul la gunoi...';
$lang['trashcan']['items_in_trash'] = 'elemente în coșul de gunoi';
$lang['trashcan']['same_name'] = 'Elementul nu a putut fi mutat la gunoi: coșul de gunoi deja un nume cu același nume.';
$lang['trashcan']['message'] = 'Aici sunt afișate elementele șterse. Puteți să le vizualizați, să le recuperați sau să le ștergeți din coșul de gunoi.';
$lang['trashcan']['empty'] = 'Golește coșul de gunoi';
$lang['trashcan']['view_item'] = 'Vizualizare element';
$lang['trashcan']['delete_item'] = 'Ștergere element din coșul de gunoi';
$lang['general']['images'] = 'Imagini';
$lang['trashcan']['restore_item'] = 'Recuperare element';
$lang_blog23 = 'Vizualizează sau adaugă reacții';
$lang['credits']['title'] = 'Autori și mulțumiri';
$lang['start']['people'] = 'Toate persoanele ce au ajutat la realizarea Pluck';
$lang['credits']['message'] = 'Mulțumirile noastre se adresează următoarelor persoane ce au ajutat la dezvoltarea sistemului Pluck.';
$lang['credits']['project_leader'] = 'Conducător proiect';
$lang['credits']['translation'] = 'Traduceri';
$lang['credits']['more'] = 'Mai multe mulțumiri';
$lang['start']['manage'] = 'De aici puteți administra siteul dumneavoastră. Alegeți o legătură din meniul de mai sus.';
$lang['start']['more'] = 'Mai mult...';
$lang['update']['up_to_date'] = 'Pluck este actualizat';
$lang['update']['available'] = 'Actualizări disponibile';
$lang['update']['urgent'] = 'Actualizări disponibile <b>urgents</b>';
$lang['settings']['xhtml_mode'] = 'Activează modul de compatibilitate XHTML (poate fi mai încet)';

//VERSION 4.6 NEW
//---------------
$lang['modules_manage']['version'] = 'Versiune';
$lang['modules_manage']['title'] = 'Administrare module';
$lang['options']['modules_descr'] = 'Administrați module și activați-le în siteul dumneavoastră';
$lang['modules_manage']['message'] = 'De aici administrați modulele. Ștergeți modulele nefolosite sau căutați module noi pentru a îmbunătății siteul cu funcționalități noi. Deasemenea puteți adăuga module în site alegând <i>Adăugare module în site</i>.';
$lang['general']['dont_display'] = 'Nu afișa';
$lang['modules_addtosite']['choose_order'] = 'Algeți ordinea în care să fie afișate modulele.';
$lang['modules_manage']['information'] = 'Informații modul';
$lang_modules9 = 'It\'s also possible to include a module only in one, seperate page. You can do this by editing the page, and then choosing the module you want to include.';
$lang['modules_manage']['uninstall'] = 'Dezinstalare modul';
$lang['modules_manage']['install'] = 'Instalare modul...';
$lang['modules_manage']['add'] = 'Adăugare module în site...';
$lang['modules_addtosite']['title']  = 'Adăugare module în site';
$lang['modules_addtosite']['message'] = 'De aici puteți configura în ce zonă a paginii să fie afișate modulele. Aceste configurări sunt specifice temei: în cazul în care schimbați tema, va trebui să le configurați din nou. Aceste configurări vor fi aplicate tuturor paginilor din site.';
$lang['page']['modules'] = 'Alegeți dacă vreți să afișați module pe această pagină și în ce ordine.';
$lang['general']['website'] = 'Pagină web';
$lang['modules_manage']['author'] = 'Autor';
$lang['modules_manage']['uninstall_confirm'] = 'Sigur doriți să dezinstalați acest modul? Configurările modulului vor fi pierdute.';
$lang['modules_install']['message'] = 'De aici puteți instala noi module. Înainte de instalare asigurați-vă vă ați descărcat fișierul modul.';
$lang['modules_install']['title'] = 'Intalare module';
$lang['modules_install']['too_big'] = 'Fișierul modul este prea mare; Limita este de 2MB.';
$lang_modules25 = 'Modulul a fost instalat';
$lang_modules26 = 'Înapoi la <a href="?action=managemodules">pagina modulelor</a>';
$lang['theme_install']['not_supported'] = 'Instalarea de module și teme nu este posibilă pe acest server. Trebui să instalați <a href="http://www.pluck-cms.org/docs/doku.php/docs:install_nozlib" target="_blank">manual</a>';
$lang['general']['not_valid_file'] = 'Instalarea a eșuat: fișierul specificat nu este valid.';
$lang['update']['failed'] = 'Verificarea actualizării a eșuat';
$lang['contactform']['module_name'] = 'Formular de contact';
$lang['contactform']['module_intro'] = 'Prin intermediul unui formalar de contact veți oferii vizitatorilor posibilitatea de a vă trimite mesaje.';
$lang['trashcan']['empty_confirm'] = 'Sigur doriți să goliți coșul de gunoi? Toate elementele din el vor fi pierdute.';

//VERSION 4.7 NEW
//---------------
$lang['modules_manage']['not_compatible'] = 'Acest modul nu este compatibil cu versiunea dumneavoastră de Pluck. Din acest motiv a fost dezactivat.';
$lang['settings']['email_invalid'] = 'Adresa de email introdusă nu este validă!';
$lang_blog24 = 'De aici puteți adăug articole pe blogul dumneavoastră. Articolele vor fi sortate automat după dată.';
$lang['blog']['choose_cat'] = 'Alege categorie...';
$lang['blog']['category'] = 'Categorie';
$lang['blog']['no_cat'] = 'Fără categorie';
$lang['credits']['developers'] = 'Dezvoltatori principali';
$lang['credits']['contributions'] = 'Contribuitori';
$lang_album19 = 'Există deja un album cu acest nume.'
?>
