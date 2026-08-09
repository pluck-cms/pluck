# Configuration

Pluck keeps configuration in two places, and the split is deliberate.

**`data/settings/config.php`** holds what has to be readable *before* storage
exists: which driver to open, what language to apologise in, what timezone to
stamp dates with. The installer writes it; it is a plain PHP file returning an
array, safe to edit by hand.

**Settings in storage** hold everything else. They live behind
`StorageDriver::getSetting()` / `setSetting()`, which means they work identically
on flat files and SQLite, and most of them have a field in the admin.

The rule for deciding where something belongs: if Pluck needs it to open the
storage driver, it goes in `config.php`. Otherwise it goes in storage.

## `data/settings/config.php`

```php
<?php
declare(strict_types=1);

return [
	'storage' => 'flatfile',
	'language' => 'nl',
	'timezone' => 'Europe/Amsterdam',
	'version' => '5.0.0-dev',
	'installed_at' => '2026-07-30T21:12:44+00:00',
];
```

| Key | Values | Notes |
|---|---|---|
| `storage` | `flatfile`, `sqlite` | Chosen at install time. Changing it afterwards does **not** move your content — the new driver opens an empty install. |
| `language` | a locale code with a `lang/<code>.json` | The fallback language, including for the installer, which runs before storage exists. |
| `timezone` | any `timezone_identifiers_list()` entry | Anything else is ignored and UTC is used. |
| `version` | version string | Written at install. Used to decide whether a schema upgrade is needed. |
| `installed_at` | ISO 8601 | Informational. |

This file must not be served over HTTP. `data/.htaccess` denies the whole
directory on Apache; on nginx you have to say so yourself — see `NGINX.md`.

### `update_source`

Where the updater looks for releases. Unset means the Pluck repository, which is
what a normal install wants.

```php
// Replace the owner and repository with your own. Leaving the example as written
// gives a 404, because there is no repository called that.
'update_source' => 'https://api.github.com/repos/OWNER/REPO/releases/latest',
```

Only that shape is accepted — `api.github.com`, over TLS, a repository's latest
release. Anything else falls back to the default rather than being fetched: a
general "get it from wherever this says" is the same hole by a longer road.

It is here rather than in Settings on purpose. An update source is code this
install downloads and unpacks, so anything that can change it can run code here,
and an administrator cannot do that today. Whoever can edit this file can already
replace `src/` outright, so putting it here gives nothing away.

The reason it exists: testing the updater otherwise means publishing a real
release on the shared repository, and `/releases/latest` skips pre-releases — so
a release candidate would have to go out as a normal release and become the
headline release for everybody still on 4.7. Point a test install at a fork
instead.

## Settings in storage

| Key | Type | Default | Set where |
|---|---|---|---|
| `site_title` | string | `Pluck` | Admin → Settings |
| `site_description` | string | empty | Admin → Settings |
| `language` | locale code | from `config.php` | Admin → Settings |
| `media_max_bytes` | int | `8388608` (8 MB) | Admin → Settings, in MB |
| `search_enabled` | bool | `false` | Admin → Settings. Puts a search box in the theme and answers `/search`. |
| `updates_check_enabled` | bool | `true` | Admin → Settings |
| `updates_channel` | `stable` | `stable` | not settable yet |
| `update_last_check` | timestamp | `0` | written by the checker; GitHub is asked at most once every six hours |
| `update_last_seen` | the newest release | `{}` | cached so several admins do not each spend a call |
| `schema_version` | int | `1` | written by the upgrade path, never by hand |
| `media_hashes` | name → sha256 | `{}` | written by the media library; rebuilt when it disagrees with the folder |
| `theme` | theme directory name | `default` | **not settable yet** |
| `pretty_urls` | bool | `false` | **not settable yet** |

The last two are read and honoured but have no field in the admin. Set them with
a one-off script if you need them before that lands:

```php
require 'src/autoload.php';
$app = Pluck\Bootstrap::boot(__DIR__);
$app->storage()->setSetting('pretty_urls', true);
```

### `pretty_urls`

Off by default, because turning it on where `mod_rewrite` is unavailable or
`AllowOverride` is off produces a site of dead links rather than an error.

Confirm the rewrite works first: with the setting still off, request
`/some-page`. If the bundled `.htaccess` is being read you get Pluck's 404 page.
If you get the web server's own 404, rewriting is not reaching `index.php` and
the setting must stay off.

### `theme`

The directory name under `themes/`, not the display name in `theme.json`. A theme
that will not load is stepped over: `ThemeRepository::active()` tries the page's
own theme, then this setting, then the bundled `default`, and reports each failure
rather than serving a blank page.

## Backups

| Key | Type | Default | Notes |
|---|---|---|---|
| `backup_keep` | int | `5` | Older archives are removed after each new one. |
| `backup_interval_days` | int | `7` | `0` switches the automatic backup off. |
| `backup_last_attempt` | timestamp | `0` | Written by the scheduler; not a setting to edit. |

Archives live in `data/backups/`, which is denied by the bundled `.htaccess` and
by the nginx rules — the same protection as the content store, verified on six
servers. Every filename ends in eight random hex characters, so a host that
ignores those rules still does not hand an archive to somebody who knows only the
date. Downloading goes through an owner-only admin route that streams the file.

They are ordinary `tar.gz` files. `tar xzf` opens one anywhere, without Pluck.

**Restoring an older backup is fine**: afterwards the store is brought up to the
current schema, the same step the installer runs, so a backup from before a
schema change comes back usable. **Restoring a newer one is refused**, because a
newer Pluck may have written data in a shape this version cannot read. Update
first, then restore — that direction works.

The code is not in the archive, so restoring never changes which version of Pluck
is running. The 4.x module did include it, which meant a restore quietly put back
the old program as well — including whatever had been patched since.

Automatic backups run when an **owner signs in** and the newest archive is older
than the interval, because shared hosting frequently has no cron. The work starts
after the response has been sent, so signing in is not slowed down by it.

## Permissions

Roles ship with a built-in permission set, and for most installs that is the whole
story. An owner can refine it from **Permissions**, which writes one setting:

| Key | Type | Default |
|---|---|---|
| `access_list` | role → permission → bool | `[]` |

Resolution order, and it is the whole design: **owner → an explicit entry → the
role's own list.** So an empty list changes nothing, a ticked box grants what the
role lacks, and a cleared box denies what it had.

Three things the screen will not do, enforced in `Auth\AccessList` rather than
only in the form:

- **An owner may do everything.** Ticking boxes is how you lock yourself out of
  the screen that would let you unlock yourself.
- **No wildcards.** `page.*` is fine for a role to be born with and bad to grant
  from a form, because the person ticking it cannot see what it will come to
  include.
- **Only permissions something consults.** A stored string nothing asks about is a
  ticked box that does nothing, with no way to tell. `Auth\Permissions` holds the
  list and `AccessListTest` checks it against the source.

Cleaning runs on load as well as on save, so hand-editing the setting cannot
introduce what the form refuses.

Only an owner reaches the screen. An administrator who could edit it could grant
themselves anything on it, which would make every other permission advisory.

## Settings the migrator writes

`bin/migrate` carries a few values across from a 4.x install:

| Key | From 4.x |
|---|---|
| `site_title` | `$sitetitle` in `options.php` |
| `contact_email` | `$email` in `options.php` |
| `language` | `$langpref`, with `.php` stripped |
| `legacy_theme` | `$themepref` — **recorded, not applied** |

`legacy_theme` is a note to yourself. A 4.x theme is PHP that ran inside the page
and cannot be used by v5, so the name is kept so you know what to rebuild.

## Blog settings

Stored as module data under `blog`, editable on the blog screen rather than in
Settings, because they belong to the module.

| Key | Default | Notes |
|---|---|---|
| `posts_per_page` | `10` | |
| `truncate_posts` | `0` | Characters shown on the index. `0` means the first paragraph. Markup is cut on the parse tree, never with `substr()`. |
| `reverse_posts` | `false` | Oldest first, for a diary or a series read in order. |
| `allow_reactions` | `false` | Read by the site. Posting is not built yet. |
| `moderate_reactions` | `false` | New reactions wait for approval. Spam stays hidden either way. |
| `post_date` | empty | A `date()` format such as `d/m/Y`. **Empty means the date follows the site language**, which is the better default — a format string reads the same in every language. |
| `post_time` | empty | A `date()` format such as `H:i`. Empty shows no time. |

The last four came from the old `pluck-blog-enhancements` module and are carried
across by the migrator, so an install that used it keeps its ordering and its
date formats.

A configured format renders in the site's timezone, from `config.php`. The
`datetime` attribute alongside it stays UTC, which is what a machine should read.

Per post, on the editor: `published` (a draft is not on the site, not even at its
own address) and `allow_reaction` (close one post without closing the blog).

## Per-page settings

Two fields on `Page` are configuration rather than content:

- **`hidden`** — kept out of the menu. The page still resolves and still renders
  at its address; this is what the admin form promises, and only a page that does
  not exist is a 404.
- **`theme`** — renders this one page with a different theme. Stored and honoured
  by the front controller; nothing sets it yet.

## Environment

Pluck reads no environment variables in normal operation. The one exception is
`PLUCK_ALLOW_ROOT_TESTS=1`, which lets the test suite run as uid 0. Read
`tests/TestCase.php::removeTree()` before you use it.

The Docker image sets PHP options through
`/usr/local/etc/php/conf.d/pluck.ini` rather than through Pluck.
