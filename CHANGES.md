# rc32 → rc38

On top of the `pluck5.0` branch. The admin's navigation moves, so this is worth
merging before anybody else starts in `views/admin/`.

## 1. A theme can ask the site to fill things in

`src/Theme/Theme.php`, `src/Theme/ThemeParameters.php`, `src/Site/SiteRenderer.php`,
`src/Admin/ThemeController.php`, `views/admin/themes.php`, `docs/THEMES.md`

A theme declares parameters in `theme.json`; an owner fills them in under
Appearance; templates read `$params`.

```json
"parameters": {
    "carnavalsdata": { "label": "De drie dagen", "default": "7, 8 en 9 februari 2027" },
    "motto": "Kielekielekiele"
}
```

This is what Pluck 4's template editor was for, minus the part that made it a
shell. Editing PHP through a browser is a shell whatever it is called — but most
of what that editor was used for was changing words, and there is no reason that
should need FTP. The person who knows this year's motto is not the person with
the SSH key.

Four decisions worth stating:

- **Only declared parameters exist.** Anything else posted is dropped, and a
  stored value for a parameter the theme no longer declares is not returned. A
  theme can rely on its parameters existing; a site cannot accumulate values that
  nothing reads.
- **Values are text.** Entities are decoded first, then tags stripped, then
  control characters replaced — `&lt;script&gt;` cannot survive as a tag that
  something decodes back later, and a newline cannot turn one attribute into two.
  Templates escape anyway; this makes that a guarantee rather than a habit.
- **Stored per theme**, so switching and switching back finds what was there.
- **Empty means default**, so resetting never has to know what the default was,
  and a theme that changes its default afterwards is followed.

## 2. Appearance, and Modules, as their own sections

`views/admin/layout.php`, `src/Admin/ModulesController.php`, `views/admin/modules.php`

Theme, parameters and stylesheet now sit together under Appearance. The
stylesheet used to be its own entry three places away from the theme picker it
belongs with, and changing how a site looked was a tour of the admin.

Modules were appended to the main navigation, one entry each. With two bundled
ones that was a short list; with a site's own modules added, the navigation was
being decided by whatever happened to be installed, and pages and media sank
further down each time. They have a section of their own now.

Which modules load stays under Settings: enabling one is a security decision and
belongs with the other ones.

## 3. Backup policy under Settings, backups page lists backups

`views/admin/settings.php`, `views/admin/backups.php`, `src/Admin/SettingsController.php`

How many to keep and how often to run is a decision; the backups page is a list.

## 4. The media list can be narrowed

`src/Admin/MediaController.php`, `views/admin/media/index.php`

Everything / pictures / files / in an album or module, with counts. The folder
stays flat — the same picture can be in an album and on a page, and giving each
module its own copy hides that and lets the copies drift — so the view does the
narrowing instead.

## 5. Relative addresses in page content broke on nested pages

`src/Site/SiteRenderer.php`, `tests/SiteRouteTest.php`

`media/photo.jpg` is right until a page is nested and readable addresses are on —
then the browser looks in `/de-club/media/photo.jpg` and every picture is a 404.
It reached a live site while the preview looked fine, because the preview runs at
a different path.

A `<base>` is the obvious fix and a trap: it also changes what `#anchor` means.

## 6. The editor wrapped text at half the width of its box

`assets/admin/pluck.css`

`p { max-width: 60ch }` is a reading measure and right for the admin's own prose.
It also applied to the paragraphs somebody is typing. Removed there, and in the
preview, where it made the preview a lie.

A `<pre>` scrolled sideways rather than wrapping, which is right for code and
wrong for the song lyrics somebody pasted. Also removed: `.editor-with-preview`,
a grid for a class nothing has ever carried.

## 7. An owner who loses their password cannot get back in

`bin/account`

The only advice available was "migrate again", which is starting over and does
not work anyway. The password is generated rather than typed: one on a command
line ends up in the shell history.

## 8. A module of your own had nowhere to live

`src/Module/Modules.php`, `src/Update/Applier.php`, `modules/`

`src/` is replaced wholesale by the updater, so a third-party module was gone the
first time somebody pressed the button. `modules/` is preserved, and a module
there loads only when its name is enabled.

## 9. A page could not embed a video, and should still not be able to

`src/Security/Csp.php`

The sanitiser strips `<iframe>`, correctly, so an embed belongs in a module — but
the CSP refused it too. `frame_hosts` names services from a short list rather
than taking hosts as written.

## 10. Two settings with no screen, and an untranslatable hint

`src/Admin/SettingsController.php`, `src/Http/Request.php`, `bin/lang`,
`views/admin/media/index.php`

`modules_enabled` and `frame_hosts` could only be set by hand.
`Request::postArray()` is new — a group of checkboxes posts a list and there was
no way to read one.

## 11. Version

rc32 → rc38. 4511 assertions over 31 suites.

Polish is at 95.8%: 31 keys, almost all from the new Appearance and Modules
screens. `php bin/lang pl` lists them.
