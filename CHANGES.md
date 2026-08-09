# rc32 → rc37

On top of the `pluck5.0` branch. All of it found by running real sites.

## 1. The editor wrapped text at half the width of its box

`assets/admin/pluck.css`

`p { max-width: 60ch }` is a reading measure, and right for the admin's own
prose. It also applied to the paragraphs somebody is typing, so text wrapped at
about half the visible width — writing against an invisible edge with empty space
to the right of it, and long words breaking mid-word for no reason a writer could
work out.

The preview panel had it too, which made it a lie: it is showing what the page
will look like, with a measure the site does not have.

Also removed: `.editor-with-preview`, a two-column grid for a class nothing has
ever carried. Dead CSS is a wrong answer waiting for whoever is debugging a
width, and it was mine for ten minutes.

## 2. Long text, and a `<pre>`, ran out of the box

`assets/admin/pluck.css`

`overflow-wrap: anywhere` rather than `break-word`: only `anywhere` also lowers
the element's minimum width, which is what a flex or grid parent measures itself
by.

A `<pre>` was worse because it was deliberate: `overflow-x: auto` is right for
code and wrong for the song lyrics somebody pasted, whose lines simply left the
screen on a phone. `white-space: pre-wrap` keeps the breaks the writer put in and
lets the browser break the rest. The two rules were folded into one rather than
stacked — two rules working against each other is how the next person finds the
wrong one.

## 3. Relative addresses in page content broke on nested pages

`src/Site/SiteRenderer.php`, `tests/SiteRouteTest.php`, `docs/ISSUES.md`

The editor writes `media/photo.jpg`. Right until a page is nested and readable
addresses are on — then the browser looks in `/de-club/media/photo.jpg` and every
picture is a 404. It reached a live site while the preview looked fine, because
the preview runs at a different path.

Relative `src` and `href` in page content are rewritten to be relative to the
install. Fragments, rooted paths, other sites and `mailto:` are left as written.

A `<base>` in the layout is the obvious fix and is a trap: it also changes what
`#anchor` means, so every in-page link — including the skip link — starts
navigating to the front page.

Verified by removing the call: both assertions fail.

## 4. An owner who loses their password cannot get back in

`bin/account`, `docs/ISSUES.md`, `README.md`

There was no way back. The only advice available was "migrate again from the 4.x
copy", which is starting over and does not work anyway, because `bin/migrate`
refuses an install that already has pages.

The password is generated rather than typed: one on a command line ends up in the
shell history. Anybody who can run this can already read `data/`, so shell access
is the whole of the permission check.

## 5. A module of your own had nowhere to live

`src/Module/Modules.php`, `src/Update/Applier.php`, `index.php`, `admin.php`,
`modules/`, `docs/MODULES.md`

The registry is built in code — deliberately — so a third-party module had to go
in `src/`, which the updater replaces wholesale. It would have been gone the first
time somebody pressed the button.

`modules/` is preserved now, and a module there loads only when its name is
enabled. Both steps are needed: a folder nobody has named is inert.

## 6. A page could not embed a video, and should still not be able to

`src/Security/Csp.php`, `index.php`, `docs/MODULES.md`

The sanitiser strips `<iframe>`, correctly. A module renders on the other side of
that line — but the CSP refused it too, since `frame-src` falls back to
`default-src 'self'`, and what a visitor saw was the browser's own "This content
is blocked" with no cause and no cure in it.

`siteHeaders()` takes hosts from a `frame_hosts` setting, checked against a short
list of service names rather than taken as written. An allow-list rather than a
syntax check: "anything that parses as a host" lets one careless setting point a
frame wherever somebody talked an owner into typing.

## 7. Two settings with no screen

`src/Admin/SettingsController.php`, `views/admin/settings.php`,
`src/Http/Request.php`, `lang/*.json`, `assets/admin/pluck.css`

`modules_enabled` and `frame_hosts` could only be changed by writing them by
hand. A setting nobody can reach is a setting nobody has, and I built both that
way in the same afternoon.

`Request::postArray()` is new — a group of checkboxes posts a list and there was
no way to read one.

## 8. An upload hint that was never translatable

`views/admin/media/index.php`, `lang/*.json`, `bin/lang`

Text after a closing PHP tag was in none of the `--leaks` patterns. One key with
a placeholder now, and the tool finds this shape.

## 9. Version

rc32 → rc37. Polish is at 99.4%: the four keys added by item 7.
