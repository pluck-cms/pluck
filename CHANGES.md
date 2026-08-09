# rc32 → rc35

On top of the `pluck5.0` branch. Six subjects, separable into six commits.
All of them were found by building real sites on it rather than by reading.

## 1. An owner who loses their password cannot get back in

`bin/account`, `docs/ISSUES.md`, `README.md`

There was no way back. Somebody handed a migrated `data/` folder cannot sign in
to it, and the only advice available was "migrate again from the 4.x copy" —
which is starting over, and does not work anyway because `bin/migrate` refuses an
install that already has pages.

```sh
php bin/account list
php bin/account password <username>
php bin/account owner <username>
php bin/account create <username>
```

The password is generated rather than typed: one on a command line ends up in the
shell history, and asking somebody to invent one at that moment is asking for the
one they use elsewhere. Anybody who can run this can already read `data/`, so
shell access is the whole of the permission check.

## 2. A module of your own had nowhere to live

`src/Module/Modules.php`, `src/Update/Applier.php`, `index.php`, `admin.php`,
`modules/`, `docs/MODULES.md`

Found by writing one. The registry is built in code — deliberately, because "drop
a folder in and it runs" is what made a compromised Pluck 4 so easy to keep — so
a third-party module had to go in `src/`. And `src/` is replaced wholesale by the
updater, so it would have been gone the first time somebody pressed the button.

`modules/` is now preserved by the updater, and a module there loads only when
its name is enabled. Both steps are needed: a folder nobody has named is inert.

`docs/MODULES.md` said a folder in `modules/` is picked up on the next request.
That was never true — written from the shape I expected rather than from the
code.

## 3. A page could not embed a video, and should still not be able to

`src/Security/Csp.php`, `index.php`, `docs/MODULES.md`

The sanitiser strips `<iframe>`, correctly: an allow-list that accepts a frame
accepts one pointing anywhere. A module renders on the other side of that line —
but the CSP refused it too, since `frame-src` falls back to `default-src 'self'`.
The result was a blank space with nothing in the page to explain it.

`siteHeaders()` now takes hosts from a `frame_hosts` setting, checked against a
short list of service names rather than taken as written. `["youtube"]` yields
`frame-src 'self' https://www.youtube-nocookie.com`; anything else is dropped,
and with nothing set frames stay refused.

An allow-list rather than a syntax check on purpose: "anything that parses as a
host" lets one careless setting point a frame wherever somebody talked an owner
into typing, and the people running these sites are not the ones who should have
to judge that.

## 4. Two settings with no screen

`src/Admin/SettingsController.php`, `views/admin/settings.php`,
`src/Http/Request.php`, `lang/*.json`, `assets/admin/pluck.css`

`modules_enabled` and `frame_hosts` could only be changed by writing them by
hand. A setting nobody can reach is a setting nobody has, and I built both of
them that way in the same afternoon.

Settings now lists the module folders it finds, with a checkbox each, and the
frame services from `Csp::frameHostNames()`. Both are checked against what
actually exists when saved rather than stored as posted: a name that is not a
folder would be a line in the store that does nothing and confuses whoever reads
it next.

`Request::postArray()` is new — a group of checkboxes posts a list and there was
no way to read one. It keeps only scalars, because a form can post arrays nested
however deep the sender likes.

## 5. An upload hint that was never translatable

`views/admin/media/index.php`, `lang/*.json`, `bin/lang`

The sentence after the closing PHP tag was English in every language. One key
with a placeholder now, and `--leaks` finds this shape — text after a closing tag
was in none of the patterns. Checked in both directions.

## 6. Version

rc32 → rc35.

---

Polish is at 99.4%: the four keys added by item 4 are still English.
`php bin/lang pl` lists them.
