# rc32 → rc33

On top of the `pluck5.0` branch. Four subjects, separable into four commits.
All four were found by building a real site on it rather than by reading.

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
one they use elsewhere. The account is asked to change it on the next sign-in.

Anybody who can run this can already read `data/` and change anything in it, so
shell access is the whole of the permission check.

## 2. A module of your own had nowhere to live

`src/Module/Modules.php`, `src/Update/Applier.php`, `index.php`, `admin.php`,
`modules/`, `docs/MODULES.md`

Found by writing one. The registry is built in code — deliberately, because "drop
a folder in and it runs" is what made a compromised Pluck 4 so easy to keep — so a
third-party module had to be added to `src/`. And `src/` is replaced wholesale by
the updater, so it would have been gone the first time somebody pressed the
button.

`modules/` is now preserved by the updater, and a module there loads only when its
name is in the `modules_enabled` setting. Both steps are needed: a folder nobody
has named is inert, which keeps the property the code comment was protecting.

`docs/MODULES.md` said a folder in `modules/` is picked up on the next request.
That was never true — I wrote it from the shape I expected rather than from the
code. Corrected.

`KEEP` still lists `instances`, a directory from an idea that was never built.
Left alone: removing an entry from a list that protects things is the kind of
tidying that goes wrong once.

## 3. An upload hint that was never translatable

`views/admin/media/index.php`, `lang/*.json`, `bin/lang`

The sentence after the closing PHP tag was English in every language:

```php
<?= $view->t('ui.media.index.up_to') ?> <?= e($human($maxBytes)) ?>. Images, PDF, audio, video, zip and Office documents.
```

One key with a placeholder now. `--leaks` finds this shape — text after a closing
tag was in none of the patterns — checked in both directions.

## 4. Version

rc32 → rc33.
