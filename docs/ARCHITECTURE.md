# Architecture

Pluck 5 in one sentence: content is data, never code, and everything that reads
or writes it goes through one interface.

That sentence is the whole design. Pluck 4 stored pages as PHP files it
`include`d, which meant writing a page and executing code were the same act, and
a theme or module was arbitrary code running inside the request. No amount of
input filtering fixes that shape — which is why 4.x needed a blacklist in
`data/inc/security.php`, and why replacing it meant a rewrite rather than a
patch.

## The layers

```
index.php / admin.php / install.php     entry points
        |
   Bootstrap                            the container: storage, session, csrf, i18n
        |
   +----+-----------------+-------------------+
   |                      |                   |
 Site\SiteRenderer     Admin\*             Migrate\*
   |                                          |
 Theme + Module                            LegacySite (read-only)
   |
 Storage\StorageDriver  <-- the only thing that touches data
   |
 FlatFileDriver | SqliteDriver
```

Nothing above the storage line opens a file or a database for content. That rule
is what makes the installer's flat-file/SQLite choice possible at all, and
`tests/StorageParityTest.php` runs the same 128 assertions against both drivers
to keep them honest.

## Storage

One interface, `Storage\StorageDriver`, covering settings, pages, users and
module data. Two implementations. A driver is chosen at install time and written
into `data/config.php`; nothing else in the codebase knows which one it got.

Module data is a namespaced key-value store: `setModuleData('blog', 'post:some-slug', [...])`.
It is how the blog and albums store their content, and it is why those two
migrate once and then work on either backend without a schema.

## Themes

A theme is `theme.json` plus a `templates/` directory of plain PHP files, and
that is deliberately all it is. Templates are rendered through `View`, which
hands them values that are already escaped or already sanitised. A theme cannot
reach storage, the session or the request.

That constraint is the point. In 4.x a theme was PHP running inside the page, so
installing a theme you downloaded was equivalent to handing over the server. Here
the worst a hostile theme can do is look wrong.

`$menu` carries the full `Page` objects, content included, because `allPages()`
loads them. That is what lets the bundled One page theme lay the whole site out
in a single document without anything being added to the core — worth knowing
before making `allPages()` lazy, since nothing else would notice.

Required templates are `layout.php` and `page.php`. Optional: `module.php` and
`404.php`, each falling back to `page.php`. A theme that will not load is stepped
over and reported — `ThemeRepository::active()` walks page theme, then site
setting, then the bundled `default` — because a typo in a template someone edited
over FTP should cost them their design for a moment, not their website.

Escaping rule, and there is only one: every value a template prints goes through
`e()`. The two exceptions are page content the sanitiser has cleaned and markup a
module built, both wrapped in `Raw` so they are visible in review.

## Modules

`Module\SiteModule` is five methods: a name, a mount path, `render()`, which
gets the remaining path, validated query parameters, read access to storage and a
URL builder, and returns a `ModuleView` or null; `embed()`, a separate, smaller
view for a page that only contains a glimpse of the module; and `search()`,
returning this module's own `SearchResult` matches. A module does not echo, does
not touch the filesystem, does not know what theme is in use, and cannot reach the
admin session. Returning null from `render()` means "nothing here" — the front
controller decides what a 404 is, because a module does not know what else might
claim an address.

Modules mount on a first path segment: `blog` owns `/blog` and everything under
it, never `/news/blog`. Nesting under pages would make a post's address depend on
where an editor last moved a page to, and every old link would break.

The registry is a list built in code, not a directory scan. "Drop a folder in and
it runs" is how 4.x shipped and is exactly the property that made a compromised
install so easy to keep.

### The admin half

`Module\AdminModule` is the editing side: a name, a `adminRoutes(Router)` and a
navigation entry. Routes go into the **same table** `Admin\Routes::table()`
builds, which is the point rather than a convenience — `CsrfSurfaceTest` and
`RouteAccessTest` walk that table, so a module route is held to the same rules as
a core one and cannot opt out by being registered somewhere else.

What a module screen runs with is `Module\ModuleContext`, not the admin
`Context`. It can read and write **its own** module data — the module name is
fixed at construction and is never a method argument, so there is no call that
reaches another module's data — read the media library by name, and ask who is
signed in. It does not get the storage driver, `Auth`, `Bootstrap` or any
filesystem path. `Context::forModule()` is the only place one is built, so the
narrowing is visible in one file.

`BlogAdminModule` and `BlogAdminController` are the worked example: routes, a
narrowed controller extending `Module\ModuleController`, and templates under
`views/admin/blog/`. Nothing in that controller can reach a page, a user or a
site setting, and that is a property of what it was handed rather than of care
taken writing it.

### Files

There is one media folder for the whole site, shared by pages and every module.
Pluck 4 kept album pictures under `data/settings` and served them through a PHP
script taking a filename from the query string; reusing one on a page meant
uploading it twice. One folder ends both problems.

`Media\MediaLibrary` is the only thing that writes to it, and holds the single
decision about what an acceptable upload is — extension allow-list, MIME check
against the bytes rather than the browser's claim, and a rebuilt filename.
`ModuleContext::addMedia()` calls it, so a module faces exactly the media
screen's policy.

Write access is narrowed by **ownership, not location**: `addMedia()` records the
filename in the module's own data, and `removeMedia()` refuses anything not
recorded there. A photo album can therefore add pictures and clean up after
itself, but cannot delete the site's logo or another module's photographs.

Permissions go through `Module\ModulePermission`, which asks about
`module.<name>.manage` before falling back to `module.manage`. Only the broad one
is granted today; the specific one is the hook for per-role, per-module access,
so that arriving later changes an implementation rather than every call site.

### Embedding

A page may contain `[module:blog count=3]`, expanded at render time by
`Site\Embed`. Plain text rather than a comment or a `data-` attribute because
the sanitiser strips both, and text needs no widening of the allow-list at all.

A page *contains* a view into a module; it does not *become* one. `SiteModule`
therefore has `embed()` alongside `render()`, and the two differ on purpose — an
embedded blog is a handful of recent posts with a link through, not a paginated
index whose "older posts" button leads the reader off the page. Links inside an
embed point at the module's own mount, so a post has one canonical address.

A marker naming a module that is not installed is left visible: an author staring
at a page that is silently missing something has nothing to go on. A module that
throws leaves a gap and the rest of the page renders.

## Addresses

`Site\Urls` builds every link, in one of two styles:

| | front page | a page | a post |
|---|---|---|---|
| pretty | `/` | `/about/team` | `/blog/some-post` |
| plain | `/` | `/?page=about/team` | `/?page=blog/some-post` |

Pretty URLs are a setting, off by default, because Pluck runs on hosting where
`mod_rewrite` may be off, in a subdirectory, behind someone else's proxy, or all
three. Both styles are read back through `Slug::path()`, so a request can never
describe something the storage layer would refuse to look up. The install
directory is worked out from `SCRIPT_NAME`, which is the one server variable that
survives all three of those situations.

Turn the setting on only after confirming `.htaccess` is being read. See
`NGINX.md` for the same rules stated for nginx, where none of that file applies.

## Search

`Site\Search` scans: every page from storage, plus whatever each module returns
from `SiteModule::search()`. No index, deliberately — see `ISSUES.md` for the
threshold at which that should change.

`/search` is the site's own address, answered before the module registry is
consulted, and `ModuleRegistry::RESERVED` refuses to mount a module there so the
two can never disagree about who owns it.

Two things a search page has to get right. The query comes back onto the page, so
it is the one input on a public site anyone can aim by sending a link — it is
escaped like everything else, and the highlight is applied *after* escaping, since
marking up first would escape the mark. And a module that throws contributes no
results rather than an error page.

## The preview

`PageController::preview()` renders the whole page through `SiteRenderer` with
the site's own theme — menu, header, footer and all — from a `Page` built out of
the form and never stored. Nothing is written, and a page that has never been
saved previews perfectly well.

It goes into a `sandbox=""` iframe, which is what makes it a preview rather than
a copy of the site you can wander off into: no scripts, no navigation, no forms.

`SiteRenderer::previewing()` exists for one reason. A theme that builds itself
from the menu — the bundled one-pager stacks every top-level page into a single
document — reads its content from storage rather than from the page handed to
`page()`, so previewing an unsaved change showed the *saved* version. The unsaved
page is substituted into the menu too, in memory, for the length of the request.

## The editor

`assets/admin/editor.js` turns the content textarea into a `contenteditable`
surface. It is a convenience and explicitly not a boundary: everything it
produces goes through `Security\Sanitizer` on save, exactly as hand-typed markup
does, and `CsrfSurfaceTest` asserts that the save path still sanitises.

Three things it does that matter. The buttons are the allow-list and nothing
else, so no button produces markup the save would quietly remove. Paste is
cleaned to the same shape, because a page that looks one way while being edited
and another once saved is the most confusing thing an editor can do. And the
textarea is one click away, which is the way out when it does something odd.

Pictures resize by dragging a corner and tables by dragging a column edge. Both
write attributes that are on the allow-list — `width` on `<img>`, `width` on
`<col>` — rather than inline styles, which are not on it and should not be: a
width holds a number, a style attribute can hold a background image or a position
that covers the page.

The resize handle is drawn *over* the editor rather than inside it. Anything
inside a `contenteditable` can be selected, dragged into the text, or deleted with
backspace, and a handle that ends up in somebody's paragraph is a bug that looks
like magic.

With JavaScript off none of it happens and the textarea is the editor.

## One rule, one place

**A rule lives in one class. Two routes to the same answer is not a design, it is
a bug that has not happened yet.** That is a hard rule, not a preference, and it
applies to PHP against PHP as much as to JavaScript against the server.

Six faults have had this shape, and every one of them was invisible until it was
not:

- The browser folded a title into an address with its own regex. It dropped
  Polish `ł`, filled the field in, and the server used that answer instead of
  `Slug::make()`.
- The page preview loaded a theme by name instead of asking `ThemeRepository`,
  so it died on installs where the site rendered perfectly.
- The stylesheet editor did the same thing a third time, and would have offered
  to edit a file in a directory that was not there.

An audit found two more, both cross-language and both already drifted: the
editor's list of image extensions included `svg` where the media picker's did
not, and its paste allow-list had lost `hr`, `sub`, `sup`, `mark`, `q` and the
definition list. Neither list is written twice now — the server hands them over
in a data attribute.

A second audit, of PHP against PHP, found four more:

- `'5.0.0-dev'` as a fallback for "which version am I", against
  `Bootstrap::VERSION` elsewhere. Nothing writes that setting, so the fallback
  was the answer — and `dev` sorts below everything, so the update badge counted
  releases older than the running code as new. Now `Updates::runningVersion()`.
- The default upload limit, written as `8 * 1024 * 1024` in one place and
  `8388608` in two others. They agreed, which is the dangerous version.
- `detectMime()` in two classes, already drifted: one had the PHP 8.5 fix and one
  did not. It decides whether a `.jpg` is really a script.
- `pathOf`, `exists`, `delete` and a listing, in `BackupManager` and `Updates`.
  Now `Archive\ArchiveStore`, which took a latent fault with it: the backup
  listing accepted names `pathOf()` would refuse, and then called `describe()` on
  them — so one stray file in `data/backups` threw on the backups page.

The tell each time was a *default*: if two places ask the same question with
different fallbacks, there is no default, there is a disagreement waiting for the
setting to be absent.

`CsrfSurfaceTest` asserts that only the settings screen reads the theme setting
directly, and `SlugTest` that the browser folds nothing. The general rule: if
JavaScript is about to decide something the server already decides, ask the
server.

`Failure.php` is the one deliberate exception. It runs before Pluck is known to
work, so reaching for `Escaper` there is how an error handler becomes a second
error.

## Test payloads

Several tests need something malicious to prove it is refused: an archive with a
shell in it, a legacy theme with executable code, a migration source with a
backdoor planted in an album folder.

Written as literals, those are literals. A distribution that contains them trips
every scanner every user runs — and the scanner is not wrong, because a
byte-for-byte webshell is a byte-for-byte webshell whatever the file it sits in
is called.

`TestCase::webshell()` assembles the same bytes at run time. What reaches disk
during a test is identical, so nothing is weakened; the pattern is simply not in
the shipped file. `CsrfSurfaceTest` asserts no shipped file contains one, so the
next person who needs a payload finds the helper rather than writing another.

## Public forms

`Form\Guard` is the only thing between a visitor's submission and storage, and
every public form goes through it — the reaction form and the contact form now,
anything added later by the same route. Four layers: a honeypot, a signed
timestamp, a per-address rate limit in storage, and an optional challenge.

The first three stay on whatever the fourth is set to. That is the whole design
of the setting: reCAPTCHA hands every visitor to Google, and a site owner who
declines that should not lose the protection which costs nothing.

`Module\PublicForm` is a separate interface from `SiteModule` because rendering
needs no write access and accepting a submission needs both that and a session.
The front controller runs the guard, never the module: a module that ran its own
checks is a module that can forget one.

A honeypot catch is reported to the sender as success. A bot told which field
gave it away comes back without filling it in.

## Security model

The blacklist is gone. What replaced it:

- **Content is data.** Pages are JSON or rows, never included PHP.
- **Allow-list sanitising** on every save, via `Security\Sanitizer` (needs
  ext-dom). `inspect()` reports what it removed rather than whether anything
  changed — parsing and re-serialising HTML alters almost every page written in a
  4.x editor, so "changed" is not a signal a person can act on.
- **Uploads face a policy**, `Archive\EntryPolicy`, by extension and size. The
  migration report names every file it refused, because an executable in an old
  `files/` folder is more often a leftover from an exploit than something someone
  uploaded.
- **Paths go through `Support\Path`**, which refuses traversal, absolute paths,
  null bytes and symlinks out of the base.
- **Sessions, CSRF and CSP** are core rather than optional, and
  `tests/CsrfSurfaceTest.php` asserts that every state-changing route has a token.

## Permissions

Every question goes through `Auth::can()`, which consults `Auth\AccessList`
before the role. The list is loaded once per request. Roles remain the default —
the list only refines them, so an install that never opens the screen resolves
exactly as it did before the feature existed.

There are eighteen call sites and they funnel through three places: `Auth::can()`
for the admin, `ModulePermission::allows()` for modules, and a `$can` closure
shared into the layout so the navigation obeys the same rules as the screens it
links to. `User::can()` remains the raw role check, deliberately: the value object
stays a value object and policy lives in `Auth`.

## Backups

`Backup\BackupManager` writes `data/` and `media/` — not the code, which is
re-downloadable and would otherwise make a restore a silent version rollback.

`Backup\Tar` is a tar reader and writer in one file. Not a preference: `ext-zip`
is missing from every image in the test bed and from much shared hosting, and
`Phar` is routinely disabled. Tar is simple enough to implement readably, and the
result opens with `tar xzf` on any machine.

Restore is the dangerous half and is written that way. A safety copy is taken
first. An archive from a newer Pluck is refused. Every path is checked with
`Path::within` against the folder it claims to be in, and only `data/` and
`media/` are accepted — an archive is untrusted input in exactly the way an
upload is. Files present now but missing from the archive are left alone.

`Backup\ScheduledBackup` is the answer to hosting without cron: on an owner
signing in, if the newest archive is older than the interval, one is made — after
`fastcgi_finish_request()` has sent the page, so nobody waits for it.

## Updates

`Update\Updates` asks GitHub for the newest release, caches the answer for six
hours, and can fetch the archive into `data/updates/` — denied by the web server
like `data/backups/`, handed out through an authenticated route.

`Update\Applier` installs one, and the split is deliberate: everything that
touches the network is in `Updates`, everything that writes over the install is in
`Applier`, and `Applier` is only ever reached from a button.

**Never a schedule.** The checksum proves the bytes arrived as GitHub sent them,
not that the release is sound — a compromised release ships a matching hash. The
answer to that is a signature, which is a promise a project has to keep on every
release, and one lapse gets the check switched off for good. So one bad release
must not reach every site before anybody has looked at it.

Applying takes a backup first and refuses if that fails, unpacks to staging,
checks the archive looks like Pluck, copies every file it is about to replace
aside, and puts them back if anything goes wrong halfway. `data/` and `media/` are
never written, even when the archive carries them.

The connection refuses plain HTTP outright and never disables certificate
checking. `UpdateTest` asserts both against the source, along with the absence of
any unpacking call — the test is there so the decision cannot quietly erode.

## Migration

`Migrate\LegacySite` reads a 4.x install as data and never writes to it — a failed
migration costs time and nothing else. The old password is deliberately not
carried over: 4.x stored an unsalted sha512, and importing it would mean starting
the new install with a hash nobody should be using.

The report is written for someone about to point DNS at a new server, so it leads
with the things that need a decision: what got renamed, what the sanitiser
removed and why, which uploads were refused, which modules have no home yet.

## Testing

`php tests/run.php`, no dependencies. **Run it as an unprivileged user** — it
refuses under uid 0, and `tests/TestCase.php::removeTree()` explains why in more
detail than anyone wants.
