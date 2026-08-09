# rc32 → rc33

On top of the `pluck5.0` branch. Three subjects, separable into three commits.

## 1. A module of your own had nowhere to live

`src/Module/Modules.php`, `src/Update/Applier.php`, `index.php`, `admin.php`,
`modules/`, `docs/MODULES.md`

Found by writing one. The registry is built in code — deliberately, because
"drop a folder in and it runs" is what made a compromised Pluck 4 so easy to
keep — so a third-party module had to be added to `src/`. And `src/` is replaced
wholesale by the updater, so it would be gone the first time somebody pressed the
button.

`modules/` is now preserved by the updater, and a module there loads only when
its name is in the `modules_enabled` setting. Both steps are needed: a folder
nobody has named is inert, which keeps the property the code comment was
protecting.

`docs/MODULES.md` said "put the folder in `modules/`, it is picked up on the next
request". That was never true — I wrote it from the shape I expected rather than
the code. Corrected.

`KEEP` also still lists `instances`, a directory from an idea that was never
built. Left alone: removing an entry from a list that protects things is the kind
of tidying that goes wrong once.

## 2. An upload hint that was never translatable

`views/admin/media/index.php`, `lang/*.json`, `bin/lang`

The sentence after the closing PHP tag was English in every language:

```php
<?= $view->t('ui.media.index.up_to') ?> <?= e($human($maxBytes)) ?>. Images, PDF, audio, video, zip and Office documents.
```

One key with a placeholder now. `--leaks` finds this shape — text after a closing
tag was in none of the patterns — checked in both directions.

## 3. Version

rc32 → rc33.
