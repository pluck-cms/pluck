# Known issues

What is broken, missing, or decided-but-not-done, as of 31 July 2026.

This file is meant to be honest rather than encouraging. A gap nobody wrote down
gets discovered by whoever migrates a real site on a Friday afternoon.

## Blocking, in the sense that a v5 site cannot be run without them

### ~~A setting can read back stale for a second or two~~ — fixed

`config.php` is PHP, so OPcache held its compiled form and by default only asked
whether the file had changed every couple of seconds. A save, a redirect, and the
next request landed inside that window and was handed the file as it was: right
on disk, stale on screen, correct again after navigating away and returning.

`Path::writeAtomic` calls `opcache_invalidate` on any `.php` it replaces.

Confirmed on a real server, which is the only place it could be: OPcache in CLI
is per-process, so no test in this suite starts with the shared cache the failure
needs.

### ~~An owner who loses their password cannot get back in~~ — fixed

There was no way back at all. The only advice available was to migrate the site
again from a 4.x copy, which is not advice — it is starting over, and it does not
work anyway because `bin/migrate` refuses an install that already has pages.

`bin/account password <username>` sets a new one and asks for it to be changed on
the next sign-in. Anybody who can run it can already read `data/`, so shell
access is the whole of the permission check.

### Sites that used the SEO module have addresses v5 cannot serve

`pluck-cms/seo-module` rewrote module URLs as `/<page>/.blog/<post>` and
`/<page>/.album/<album>` — a module nested underneath whichever page it was
placed on. Pluck 5 mounts modules on a first path segment instead, deliberately,
so that a post's address does not change when an editor moves a page.

The consequence is that a migrated site which used that module has links in the
wild, and in search results, that now 404. The migrator does not know about them,
because the old address depended on module placement rather than on anything
stored with the post.

Fixable: the migration knows which page carried each module, so it could emit the
old-style paths into the redirect map. Nobody has written that.

### A mistyped embed parameter is invisible

`[module:blgo]` stays on the page, so a misspelled module name is findable.
`[module:albums album=vakanti]` renders nothing at all, because a module
returning null means "nothing to show" — which is also the honest answer on a
site that has no albums yet, so the two cases cannot be told apart.

Fixing it means a module saying *why* it has nothing rather than just that it
has nothing, and the admin surfacing that on the page list. Worth doing before
anyone builds a site around embeds; not worth guessing at now.

### Permissions are per role, not per person

The access list grants and denies by role. "This editor may manage the blog, that
one may not" needs two roles, and there are four.

Per-person overrides would be more flexible and considerably more to keep track
of — a permission question would have to consult the person, then their role,
then the defaults, and an admin screen would have to show all three. Nobody has
asked for it. It is written down here so the answer is on record rather than
rediscovered.

### Deleting a file from the media screen can empty an album

One media folder is shared by pages, albums and any future module. Nothing warns
that a file is in use before it is deleted, so removing a photograph from the
media screen leaves any album referencing it showing a broken image.

The intended fix is for the media screen to say *"used by 2 albums and 1 page"*
before deleting, which is the honest version — the alternative, giving each
module its own copy of every file, hides the relationship instead of reporting it
and lets the copies drift apart. Counting page usage means searching page bodies
for `media/<name>`, which nothing does yet.

### Per-page themes cannot be chosen

The site-wide theme is now a field in Settings. `Page::$theme` — rendering one
page with a different theme — exists in the model, is stored by both drivers and
is read by the front controller, but the page editor has no field for it.

### The excerpt is the first paragraph when no length is set

With `truncate_posts` at zero — the default — `BlogModule::excerpt()` cuts at the
first `</p>`, so a post opening with a heading, an image or a list gets a summary
that is one of those and nothing else. Setting a character limit avoids that,
since `Support\Excerpt` counts text across the whole fragment, but the default
still has the old behaviour.

### ~~A post's old address is recorded but not honoured~~ — fixed

The migration writes `legacy_redirects`, and `index.php` consults it when nothing
else matched. Both 4.x address shapes are handled: the path form, and the
`?file=blog&blog=name` query form — which never reached the not-found path at
all, because Pluck read it as a request for the front page and served it.

<details><summary>What it was</summary>

Renaming a post moves it and takes its reactions along, but the old address
becomes a 404. `legacy_seoname` is carried through the migration and kept across
edits, so the information needed for a redirect is there; nothing reads it.

</details>

### Search scans on every request

No index. `Site\Search` reads every page and asks every module, each time. On the
sites Pluck is for this is not measurable — the siwohjun export is 17 pages and
82 posts, and a search across all of it is one pass over a few hundred kilobytes.

The threshold, written down so nobody has to guess later: if a site is slow to
search, it is because reading the content costs more than the request budget, and
that starts to matter somewhere around a thousand pages on shared hosting. The
fix is an index behind the same class, rebuilt on save, and the reason it is not
there now is that an index can go stale — and "I changed that page and search
still shows the old text" is a complaint nobody can reproduce.

Matching is a case-insensitive substring, with no stemming and no ranking by
frequency. Both are language-specific, and Pluck runs in languages nobody here
has thought about. Searching for "openingstijd" therefore does not find
"openingstijden".

## Untested, rather than broken

### Two optional extensions are absent from most of the matrix

The official `php:8.x` images ship with neither `ext-zip` nor `ext-intl`, and
neither does plenty of shared hosting. Both are declared in `composer.json` under
`suggest`, and both degrade rather than fail — but the degradation is untested on
the machines that will actually hit it.

Without `ext-zip`, theme and module archives cannot be read: `SafeZipTest` skips
itself entirely, so the archive policy is exercised on the developer's machine
and nowhere else.

Without `ext-intl`, dates fall back from "1 June 2021" to `2021-06-01`. That is
deliberate and unambiguous, but it means the localised path — the one most
installs are meant to take — is the one the test bed cannot check.

### The hash register can be rebuilt but is not rebuilt on a schedule

`MediaLibrary` keeps `name => sha256` as a setting so a duplicate is recognised
without rereading the folder. A miss rebuilds it, which covers a file copied in
over FTP. What is not covered is a file *replaced* in place with different
content under the same name: the register would then claim a hash the file no
longer has, and the next identical upload would be told it is already here when
it is not.

`rebuildRegister()` fixes it and nothing calls it on a schedule.

### Sub-directory installs are unit-tested only

`Urls::detectBase()` and `detectPath()` have assertions for `/cms/`. No install
has actually been served from one.

## Not Pluck 5's bugs, but worth knowing before migrating

### The 4.x backup module writes its archives where the web server can serve them

`pluck-cms/pluck-backup` tars `data/settings/` into
`data/modules/backup/backups/<H.i-d.m.y>.tar.gz`, chmods it 0777, and links to it
directly for download. A stock 4.7 install ships no `.htaccess` under `data/`, so
that path is fetched over HTTP by anyone who asks for it.

The archive contains everything in `data/settings/`, including `pass.php`. The
filename is hour, minute, day, month, year — around 1440 candidates for a given
day, which is not a search.

Its delete route is worse in a smaller way:
`unlink('data/modules/backup/backups/' . $_GET['delfile'])` with no path
checking, reached by GET. It needs a signed-in admin, but being a GET it is
reachable by getting that admin to load an image tag, and the path is not
constrained to the backup folder.

None of this is inherited by Pluck 5 — `data/` is denied by the bundled
`.htaccess` and by the nginx rules, and backups are not built yet. It matters
because **an install that has ever run this module may have archives sitting in a
public directory right now**, and migrating does not remove them. Worth checking
before a migration, and worth checking on any 4.x install that is staying put.

The same shape applies to the 4.x updater, which wrote its pre-upgrade backup
into `files/` — also web-served, also a copy of the whole install.

### Restoring a newer backup needs the updater

An archive from a newer Pluck is refused, and the message says to update first.
There is no updater yet, so on an install that cannot be updated by hand the
answer is "unpack the tar yourself" — which works, since they are ordinary
`tar.gz` files, but is not an answer a site owner should need.

Listed here so the updater picks it up rather than being built without it.

## Decided, and worth re-reading before changing

### The old password is not migrated

4.x stored an unsalted sha512 in `data/settings/pass.php`. Importing it would
mean a v5 install starting life with a hash nobody should be using, so the owner
gets a one-time password instead. This is not an oversight.

### Reaction e-mail addresses are dropped

4.x recorded an address with every comment and never displayed it. Carrying it
over means the new install holds personal data it has no use for and the
commenter cannot ask about. The report says how many were dropped.

### The 404 page does not echo the requested address

Reflecting it lets anyone put text of their choosing on the site by getting a
link clicked. The visitor already knows what they typed.

### The test suite refuses to run as root

`tests/run.php` exits under uid 0. `TestCase::removeTree()` used to follow
symlinks, and `PathTest` creates one deliberately; as root that combination
emptied `/etc` on a production server and killed three development sessions. The
underlying bug is fixed. The guard stays.

`PLUCK_ALLOW_ROOT_TESTS=1` overrides it. Read
`TestCase::removeTree()` before you use it.
