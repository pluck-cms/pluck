# Roadmap

## Why there is a v5 at all

Pluck 4 stored pages as PHP files it `include`d. Writing a page and executing
code were therefore the same act, which is not a bug you can filter your way out
of — it is the shape of the program. `data/inc/security.php` held a blacklist
because a blacklist was the only thing that could be bolted onto that shape.

v5 replaces the shape. Content is data, never code. Everything that reads or
writes it goes through one interface. That is the entire project; the rest is
consequences.

What must not change on the way: Pluck runs on the cheapest shared hosting, needs
no database, has no build step, and can be understood in an afternoon. A version
that is secure and needs Composer, Node and a VPS has lost the argument.

## Where things stand

| Phase | | |
|---|---|---|
| 1 | Repo skeleton, storage interface and both drivers, security baseline, installer | **done** |
| 2 | Admin: sign-in, page list, editor, users, media, settings | **done** |
| 3 | Content, modules, themes, migrator | **done** |
| 4 | Search, updater, editor, more themes | **started** |

### Phase 3, in detail

Done:

- `bin/migrate`, `Archive\SafeZip`, `Archive\EntryPolicy`
- i18n with catalogue tests that fail the build on drift
- Blog and albums migrated out of 4.x, including reactions and categories
- Theme layer: `Theme\Theme`, `Theme\ThemeRepository`, `Site\SiteRenderer`,
  bundled `default` theme
- Module API: `Module\SiteModule`, `ModuleView`, `ModuleRegistry`, with blog and
  albums as the two implementations
- Menu, breadcrumbs, pretty URLs, and an `index.php` that is a front controller
  rather than a stub

Also done since:

- **An access list**: per role, per permission, edited by an owner from a grid.
  Covers pages, media, people, the site and every module. An empty list changes
  nothing, so an install that never opens the screen behaves exactly as before.

- **An admin for albums**, and with it the answer to whether `ModuleContext` was
  too narrow: it was. Modules can now add files to the shared media library
  through the same policy the media screen uses, and remove only the ones they
  added. Narrowed by ownership rather than by location, so a photo in an album and
  the same photo on a page stay one file.
- **A media picker in the page editor**, inserting at the cursor with no
  rich-text dependency.

- **The admin module contract**: `Module\AdminModule`, plus the narrowed
  `ModuleContext` a module screen runs with. Module routes join the same table
  the rest of the admin uses, so the CSRF and permission tests cover them.
- **A theme chooser** in Settings, offering only themes that load.
- **A pretty-URL switch** that refuses to turn itself on until a probe request
  proves a path-style address really reaches `index.php`.

- **Modules embedded in pages**, as `[module:blog count=3]` in the page text,
  inserted at the cursor from the editor. A page shows a glimpse of a module and
  links through to it, rather than becoming the module — which is what 4.x did,
  and why sites that used the SEO module have addresses v5 cannot serve.

Phase 3 is finished. What was deferred out of it, deliberately:

- ~~**Public input**~~ — done. A reaction form under every post and a contact
  form as `[module:contact-form]`, both behind one `Form\Guard`: honeypot, timing
  and rate limit always on, plus a sum or reCAPTCHA. The optional layer is the
  only one a visitor sees, and turning it off never removes the other three.
- **A per-page theme field**. `Page::$theme` is stored and honoured; the editor
  has no field for it.

Both are in `ISSUES.md` with the reasoning.

### Backlog: features from widely-used 4.x modules

**Backups — done.** An archive of `data/` and `media/` in `data/backups/`, which
both Apache and nginx refuse; downloading streams through an owner-only route, so
there is never a link to the archive. Names carry eight random characters, in case
a host ignores those rules. Restore takes a safety copy first, refuses an archive
from a newer Pluck, and writes only under `data/` and `media/`. Written as tar by
hand: `ext-zip` is absent from the whole test bed and `Phar` is often disabled.

**Album descriptions and lightbox**, from `pluck-cms/pluck-albums-enhancements`:
an album gets a description with HTML, the name becomes editable, and a random
image can be shown elsewhere on the site. It also bundles lightbox2, which v5
should not — a gallery viewer is a theme's business, and bundling one means
shipping a JavaScript dependency into a project that has none.

**SEO module.** Read, and there is nothing to build: `pluck-cms/seo-module` is
pretty URLs and nothing else, which Pluck 5 already has as a setting, with a
probe that checks the rewrite really works instead of telling you to copy
`htaccess.txt` by hand. What it does leave behind is a redirect problem — see
`ISSUES.md`.

### Phase 4

- **Search — done.** Scans rather than indexes; modules contribute their own
  matches through a fourth contract method. `search_enabled` finally does
  something, having been a switch that did nothing since phase 2.
- **Updater — done.** Checks GitHub, fetches the archive, shows its SHA-256, and
  installs it when somebody presses the button. A backup is taken first and the
  update is refused outright if that backup cannot be made.

  **A button, never a schedule**, and that line is the whole design. The checksum
  proves the bytes arrived as GitHub sent them — not that the release is sound; a
  compromised release ships a matching hash. The defence against *that* is a
  signature, which is a promise a project has to keep on every single release, and
  one lapse gets the check switched off for good. So one bad release must not
  reach every site before anybody has looked at it, and a person decides.
  `UpdateTest` asserts against the source that applying is never scheduled, so the
  reasoning cannot quietly erode into a cron job.

  Owners and administrators are told a release exists: a badge in the navigation,
  and one e-mail per version to the accounts that could act on it. The 4.x updater made a
  backup first, which was right; it put it in `files/`, where the web server
  serves it, which was not. A button that can break someone's site with no way
  back is worse than no button.
  Whatever replaces it goes through `EntryPolicy`, and signature checking should
  be settled before anything is written, not after.
- **Editor — a visible one, after all.** The first answer was a textarea with a
  toolbar and a preview, argued for on the grounds that an editor is the largest
  piece of foreign code a CMS carries. That reasoning was right about the risk and
  wrong about the weight: Pluck 4 shipped TinyMCE and its users learned to expect
  one, and asking them to type HTML is asking them why they are using a CMS.

  What changed the calculation is that the sanitiser is the boundary, not the
  editor. Everything the editor produces goes through the allow-list on save, so a
  bug in it cannot put anything into a page that a hand-typed `<script>` could
  not — which makes a visible editor a convenience rather than a layer to trust.

  Written here rather than pulled in, because Pluck's promise is that you unpack
  it and it runs: no npm, no CDN, no build step. The buttons are exactly the
  allow-list, paste is cleaned to the same shape so the page does not change
  appearance on save, and `</>` brings the markup back for anybody who wants it.
  With JavaScript off it is the textarea, as before.

  <details><summary>What the first answer was</summary> A wider toolbar covering what the
  allow-list actually accepts, the three keyboard shortcuts everybody already
  knows, and a live preview showing what the sanitiser will leave behind.

  No rich-text editor, deliberately. An editor is the largest piece of foreign
  code a CMS carries, it attracts CVEs, and Pluck has no dependencies to weigh
  that against — this project has already spent a painful upgrade on one.
  Writing a contenteditable editor instead means years of browser edge cases.

  The preview is rendered by the server, not the browser, because the useful
  question is not "what does this HTML look like" but "what will be left of it".
  It lands in a `sandbox=""` iframe: the markup is sanitised, but sanitised is not
  a reason to give page content the run of the admin page.
  </details>

- **More themes — answered from a different direction.** `bin/convert-theme`
  translates a Pluck 4 theme into a Pluck 5 one. Measured against the thirty in
  `pluck-cms/themes`: all thirty render, twenty need nobody at all. That exercises
  the theme API against thirty designs nobody here wrote, which is what a second
  hand-made theme could never have done.

  Module spaces are the one thing that cannot come across, by design rather than
  by omission.

  Four themes ship as a result. **Columns**, rebuilt from a real Pluck 4 theme
  with its branding made settable. **Blank** and **Blank dark**, the same markup
  with the styling removed, for a customer stylesheet to sit on. And **One page**,
  which stacks every top-level page into one scrolling document with anchors for
  a menu — the editor still keeps separate pages, the visitor gets one.

  None of them carries any JavaScript. The framework they replace bound
  `touchmove` while its menu was open and cleared the flag only in an animation
  callback, so an interrupted animation left the page unscrollable until it was
  reloaded. A menu on a checkbox cannot have that bug.

## Not planned

- A database requirement. SQLite is an option; it is not the direction.
- A package manager, bundler, or anything requiring Node.
- Blocks, page builders, or a component model. Pluck edits pages.
- Multi-site. Two installs in two folders is the answer, and it works.

### ~~A stylesheet editor~~ — done

One file, the one the theme loads, with a copy of the previous version kept so a
bad save is one click back. Templates stay uneditable for the reason below.

<details><summary>The reasoning, kept</summary>

4.x had a wider one as the `editor` module — theme PHP *and* CSS. Only the CSS
half is wanted, and that limit is the design rather than a first step.

A template in 5 is PHP the site executes. An editor for it is an editor for
running code, which is the thing the whole security model was rebuilt to avoid,
and a mistake takes the site down with a fatal error while the editor that would
fix it lives on that same site. A stylesheet is text: the worst outcome is an
ugly page, reachable and fixable.

What it needs: the current theme's `assets/style.css` in a textarea, a copy of
the previous version kept so a bad save is one click back, and nothing else. Not
a file browser — one file, the one the theme actually loads.

</details>

### ~~A diagnostics page~~ — done

Five groups, each row saying what this server gives and who fixes a gap — the
site owner or their host, with wording meant to be pasted into a support ticket.
`phpinfo()` is still there, owner-only, for what no list anticipates.

<details><summary>The reasoning, kept</summary>

4.x had one that printed `phpinfo()`. Worth having and worth being more than
that: a list of what Pluck needs, what this server provides, and — the part
`phpinfo()` never answers — what to do about each gap.

Most of it is already written down somewhere else and could be gathered:

**What the installer already checks**, kept visible afterwards rather than only
before: PHP version, the required extensions, whether `data/` and `media/` are
writable.

**The things that went wrong in practice**, which is the more useful list. The
session folder writable *by the web server* — an unwritable one produces a
sign-in that loops with nothing on the page, and it is what running a migration
as root leaves behind every time. Whether `data/` is refused over HTTP, which
differs between Apache and nginx and is the check the test bed exists for.
Whether a `.php` under `media/` executes. Whether the rewrite reaches
`index.php`, which the pretty-URL switch already probes.

**What is optional and what it costs**: `ext-intl` absent means dates fall back
to `2021-06-01`; `ext-zip` absent means theme archives cannot be read; no
outbound HTTPS means update checks cannot run; no `mail()` means the contact form
keeps messages but sends none.

**Numbers worth seeing before they bite**: `upload_max_filesize` and
`post_max_size` against the media limit, `memory_limit` and
`max_execution_time` against what a backup of a large site needs, free disk
space against the archives already kept.

Each line should say who fixes it. "Give the web server write access to
data/cache" is the site owner; "ask for ext-intl" is a support ticket, and
phrasing it as one saves a person writing it themselves. A link to `phpinfo()`
can stay, behind the owner check, for the things no list anticipates.

</details>

### Installing a theme from the admin

There is no way to add a theme through Pluck: you put the folder in `themes/`
over FTP. For somebody who bought a theme, or was sent one, that is a step they
may not be able to take.

What makes it more than an upload form: an archive is untrusted input, and a
theme is PHP the site executes. `EntryPolicy` and `SafeZip` already exist for
exactly this, and `Backup\Tar` reads tar — which matters, because `ext-zip` is
missing from most shared hosting, so a zip-only upload would fail on the
machines that most need it.

The check on the way in should be the same one `bin/convert-theme` applies on the
way out: templates and a `theme.json`, stylesheets, images and fonts, and
nothing else executable outside `templates/`.

## 5.0-rc1, and why not 5.0

Nothing blocks a migration. What is not yet true is that anybody has *run* this.

The version number is a promise to people who are not in this conversation, and
three things argue for holding it a fortnight:

**The updater has never applied a real release.** It has been exercised against
an archive built for the purpose and nothing else. The first genuine update it
performs will be on installs running whatever gets tagged — so tagging rc1 and
then using the updater to reach 5.0 tests it on the one install where somebody is
watching, before it is anyone else's problem.

**Half of this shipped today.** Public forms, backups and restore, the apply
button, the diagnostics screen, the stylesheet editor. All are tested; none has
survived contact with a site that people use.

**One migration is one migration.** It went well and it was a small site with a
sympathetic owner sitting next to the person doing it. That is the easiest case,
not the representative one.

None of that is a reason to wait before migrating. It is a reason for the tag to
say what is true: a release candidate, on one production site, for a fortnight.

## Before 5.0 final

The infrastructure list is finished. What remains is a migration of a site that
is actually in production, and the features below.

- ~~A run under Apache and under nginx~~ — done: six servers, three PHP versions,
  every check passing, including a real PHP file refusing to execute under
  `media/`
- ~~A sub-directory install, installed and used~~ — done: installed at `/cms/`,
  then a page, a blog post and an album created, and both modules embedded in a
  page. Every link, redirect and media address carried the prefix
- Private vulnerability reporting switched on for `pluck-cms/pluck` (Settings →
  Security), which is what `SECURITY.md` already tells people to use. It needs no
  e-mail address, and it is the difference between a maintainer hearing about a
  flaw first and everyone hearing about it at once — which matters most for the
  people running Pluck rather than writing it
- One migration of a site that is actually in production, with the owner watching
