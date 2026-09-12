# Migrating a Pluck 4.x site to 5

The migrator reads an old install and writes into a fresh Pluck 5. It never
writes to the old directory — not a timestamp, not a lock file. The test suite
fingerprints the whole source tree before and after a migration and fails if a
single byte changed.

## Before you start

You need:

- a copy of the old site, including `data/` and `files/`. An FTP download or a
  tarball is fine; it does not have to be the live directory
- a Pluck 5 install that has been through `install.php` and has **no pages yet**
- shell access, because this is a command line tool

If you have no shell on the target host, migrate on a machine that does — a
laptop with PHP 8.3 works — and upload the resulting `data/` and `media/`
afterwards.

## Run the plan first

    php bin/migrate --from=/path/to/old/pluck

This writes nothing. It reads the old install and prints a report of what would
happen. Read it properly: it is the only moment where the awkward parts are laid
out in one place, before anything is decided.

The report has sections for:

- **Addresses that changed.** Set up redirects for these or you lose the links
  other people made to your site.
- **Content the sanitiser changed.** Pages where the allow-list removed markup.
  On an old install that usually means a script tag or an event handler — which
  is the point — but it can also be an embed you actually wanted.
- **Uploads not copied.** Files the upload policy refuses. A `.php` in `files/`
  is the interesting case: on a 4.x site that is either a leftover or a webshell,
  and either way it is not coming along.
- **Module data kept on pages.** 4.x modules wrote bare top-level variables into
  the page file. They are preserved under a `legacy` key so nothing is lost, but
  nothing reads them yet.
- **Modules and themes in the old install**, with what happens to each.
- **Skipped.** Page files that could not be parsed at all.

## Then commit

    php bin/migrate --from=/path/to/old/pluck --commit --owner=yourname --report=migration.txt

`--owner` is the account you will sign in with. It is created with a long
one-time password that is printed in the report and must be changed at first
sign-in. Save that report somewhere; the password is shown once.

Inside the Docker image, use `php bin/migrate` rather than `./bin/migrate` — the
image normalises file modes to 644, so the executable bit is gone.

## What comes across

**Pages**, with their tree, order, hidden flag, description and keywords. The
address is rebuilt from the 4.x `seoname` through the same slug rules new pages
use, so a child ends up under its parent's new path.

Two cases produce a changed address. A title with no latin characters got a
timestamp filename in 4.x (issue #27) — something like `20231104093012` — and
that becomes a readable slug built from the title. And two pages that slug to the
same thing get a counter. Both end up in the redirect list.

**Page content**, through the sanitiser. This is not optional and cannot be
turned off. Content stored before 4.7 had any working XSS defence is exactly the
content most likely to carry a payload, and importing it raw would move the
problem rather than solve it.

**Uploads** from `files/` and `images/`, judged one at a time. Names are rebuilt
the same way new uploads are, so a name that was itself an XSS payload
(CVE-2018-19420) does not survive the move.

**Settings**: site title, contact email, language. The theme name is stored as
`legacy_theme` so you know what it was.

**The blog**, into the `blog` module: posts with their category, publish date
and content (through the sanitiser, same as pages), plus categories and
reactions. Reactions come across already approved — they were visible for
years on the old site, so a moderation queue on moving day would be a
surprise rather than a safety measure — though a reaction's e-mail address is
not carried, since 4.x never showed it either. The old blog-enhancements
settings (`reverse_posts`, `truncate_posts` and the rest) come across too, so
an install that used it keeps its ordering and date formats.

**Albums**, into the `albums` module: each album's title, description and
images, with captions, in their original order. A picture used in more than
one album is stored once and shared, the way Pluck 5's single media folder
works; an entry whose image file is missing or was refused by the upload
policy is reported and skipped rather than guessed at.

## What does not come across

**Themes.** 4.x themes are PHP. Themes in 5 are templates. The migrator records
which theme you had and leaves the conversion to you — see "Themes from Pluck
4" below, or `docs/THEMES.md`.

**Third-party modules.** The blog, albums and contact form are handled as
above; search, the updater, pretty URLs and a few other 4.x modules are things
Pluck 5 already does natively (the report says which, per module). Anything
else — a plugin nobody here wrote — needs a hand review: a 4.x module is
arbitrary PHP running inside the admin session, which is precisely the thing
v5 is careful about, and there is today no tool that converts one for you. See
"Bringing a 4.x plugin across" in `docs/MODULES.md`.

**Accounts.** 4.x had one shared password, not accounts. There is nothing to
carry over; you create the owner during the migration and the rest afterwards.

## Afterwards

- Check the pages named under "content the sanitiser changed"
- Put the redirects in place
- Sign in and change the one-time password
- Confirm `install.php` is gone from the new install
- Keep the old directory until you are sure. It is untouched, so it is still a
  working 4.x site you can point a web server at.

## If it refuses

Two refusals are deliberate:

- *This installation already has pages.* Migrate into a fresh install so old and
  new content are never mixed. Reinstall the target and try again.
- *Install Pluck 5 first.* The migrator writes through the storage driver, so
  there has to be an installed site to write into.

## Pictures that appear in more than one album

4.x gave every album its own directory, so a photograph used in three albums was
three files on disk. Pluck 5 has one media folder shared by pages, albums and
every module, and the migrator carries identical content across once — the albums
then all point at the same file, and each keeps its own caption for it.

Identical means byte for byte, never merely the same filename: two files called
`logo.png` are routinely two different logos, and merging those would replace one
of them without saying so. The report counts what it recognised.

## Why you cannot unpack Pluck 5 over Pluck 4

It is the obvious thing to try, because it is exactly how an *update* works:
overwrite the program, leave `data/` alone. Between 4 and 5 it does not, and the
migrator refuses rather than letting it happen.

**`data/inc/` is not configuration — it is the whole of Pluck 4.** Its router,
its sign-in, its settings code. Unpacking Pluck 5 over the top leaves that on
disk and reachable, so the old application is still being served alongside the
new one, with every hole it has.

**The two stores have nothing in common.** 4.x keeps a page as a PHP file that
declares variables; 5 keeps JSON or SQLite. The migrator reads one and writes the
other, so pointing both ends at one directory means reading from what is being
overwritten as it is overwritten.

`bin/migrate` therefore refuses two things: a `--from` that is this install or
contains it, and an install that still has `data/inc/` or `data/modules/` in it —
which means somebody has already unpacked over the top and should start again
from a clean copy.

The way that works:

```sh
# 1. a fresh Pluck 5, somewhere else
mkdir /var/www/newsite && cd /var/www/newsite && unzip pluck5.zip

# 2. migrate. This installs Pluck 5 for you if you have not already.
php bin/migrate --from=/var/www/oldsite --dry-run
php bin/migrate --from=/var/www/oldsite --commit --owner=bas

# 3. look at it, then swap the two directories over
```

**You do not have to run install.php first.** Everything the installer asks for —
the site title, the timezone, the language — is already in the old site, so it is
taken from there rather than typed again. Add `--storage=sqlite` if you want that
instead of flat files.

Installing by hand first still works. The migration reuses the account rather
than adding a second one with the same name, and resets its password to the
one-time password on the report.

Keep the old directory until you are sure. It is the only copy of the original.

## Themes from Pluck 4

A 4.x theme is PHP that runs inside the page — the shape Pluck 5 exists to
refuse — so a theme cannot simply be copied across. It can, however, be
translated, and that turns out to be a much smaller job than it sounds.

Reading the thirty themes in `pluck-cms/themes`, the entire vocabulary is eight
functions and a handful of variables: `theme_meta`, `theme_sitetitle`,
`theme_pagetitle`, `theme_content`, `theme_menu`, `theme_submenu`,
`theme_module`, `theme_area`. That is a small language, and a small language can
be translated.

```sh
php bin/convert-theme /path/to/old/theme themes/mytheme
```

The markup, the stylesheet and the images come across as they are. The holes
where Pluck 4 called a function become the Pluck 5 equivalent: an escaped value,
or a loop over the real menu keeping the theme's own tags and its own class for
the current page.

**Read `templates/layout.php` before putting it on a live site.** The converter
says what it could not translate rather than dropping it quietly, and anything it
did not recognise is left in the layout as an HTML comment naming the line.

Measured against those thirty published themes: **all thirty render**, and twenty
convert without needing a person at all.

### What cannot come across

**Module spaces.** `theme_module('footer')` declared a slot for Pluck 4 to drop a
module into. Pluck 5 mounts a module at its own address instead, and a page shows
one with `[module:blog]` in its text — so there is nothing for a slot to mean.
The converter leaves a comment where the call was and says so in its report.

**Anything with logic in it.** A few themes contain their own `if` blocks or call
functions of their own. Left running, those would be a fatal error on every page:
a Pluck 5 template is rendered with the URL builder and the translator in scope
and nothing else. They are commented out instead, so the theme loads with a
visible gap rather than not loading at all.

**Executable files.** Only stylesheets, images and fonts are copied. A theme
archive is something somebody downloaded, and a converted theme has no business
carrying a PHP file into a directory the web server serves.
