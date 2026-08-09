# Contributing to Pluck 5

Pluck 5 lives on the `pluck5.0` branch. `master` is 4.7 and stays that way until
5.0 is released, so open pull requests against `pluck5.0`.

It is a rewrite: it shares no code and no history with 4.x. If you know 4.7 well,
the thing to read first is `docs/ARCHITECTURE.md`, because most of what you know
about where things live has changed.

## Before you open a pull request

Two commands. Both have to pass:

```sh
php tests/run.php
php bin/lang --leaks
```

The suite refuses to run as root, deliberately — several tests check what happens
when a file cannot be written, and root can write anything. Run it as your own
user, or as the web user.

`--leaks` finds English typed straight into a template. A key nobody translated
shows up as a gap in `php bin/lang`; English written into a view shows up as
nothing at all and stays English in every language forever.

## What this project asks of a change

**PHP 8.3, no dependencies, no build step.** Pluck's whole premise is that you
unpack it on the cheapest shared hosting there is and it runs. No composer
packages at runtime, no npm, no CDN. If something needs a library, the first
question is whether it needs doing.

**Tabs, `declare(strict_types=1)`, one class per file, PSR-4 under `Pluck\`.**
`.editorconfig` has the rest.

**A comment says why, not what.** `// increment the counter` above `$i++` is
noise. `// Written before the pretty-URL check, which returns early when
rewriting does not work` is the reason somebody will need in a year. Where a
choice was made between two reasonable options, say which and why — that is the
part nobody can reconstruct from the code.

## Escaping

This is the one that has bitten this project most often.

`View::t()` **already escapes**. Do not wrap it in `e()`:

```php
<?= $view->t('nav.pages') ?>          <!-- right -->
<?= e($view->t('nav.pages')) ?>       <!-- wrong: Pagina&#039;s on screen -->
```

It is invisible until a value happens to contain an apostrophe, at which point it
is on every page that shows it. Twenty-eight templates were written that way
before anyone noticed, and it came back once afterwards.

Everything else gets `e()`. The exceptions are page content and `$content`, which
have been through the sanitiser and are markup on purpose.

## One rule, one place

A rule lives in one class. If you find yourself writing a second implementation
of something the codebase already decides — a default, a name check, a list of
allowed things, a resolution with a fallback — extract it instead. Two routes to
the same answer is not a design; it is a bug that has not happened yet, and this
project has had six of them.

The tell is a default. Two places asking the same question with different
fallbacks do not have a default between them, they have a disagreement waiting
for the setting to be absent.

This applies to JavaScript against the server too. If a script is about to decide
something the server already decides, ask the server.

## Security boundaries

Three things carry the weight. Changes near them need care and a test:

**`Security\Sanitizer`** is the allow-list. Everything written into a page goes
through it, including whatever the visual editor produced — the editor is a
convenience, never a boundary. A save path that trusted its output because it
"already cleaned it" would be the quiet way to lose this.

**`Support\Path::within()`** is what keeps a path inside its directory. It
resolves symlinks and compares real paths, and it is the reason a crafted page
name cannot write outside `data/`.

**`Form\Guard`** stands in front of every form a visitor can submit. Honeypot,
signed timestamp, rate limit, and an optional challenge. A module that accepts
input implements `Module\PublicForm` and does not repeat those checks — the front
controller runs them, not the module.

## Tests

`tests/run.php` is a small runner; there is no PHPUnit and nothing to install.

**A test for a bug must be seen to fail.** Write the test, put the bug back, watch
it go red, then fix it again. Several tests in this repository were written after
a fix, passed immediately, and proved nothing — one of them was checking a
`Location` header under the CLI SAPI, where `header()` does nothing at all.

**A screen must be opened, not only linted.** `AdminScreenTest` runs every admin
route in a subprocess and checks it comes back. It exists because the suite once
passed with 2,862 assertions while the settings screen died on every request.

**Need a malicious payload?** Use `TestCase::webshell()`. Writing one as a literal
puts a working webshell in the distribution, and every scanner every user runs is
right to flag it. `CsrfSurfaceTest` fails on any literal one.

## Translations

`docs/TRANSLATIONS.md` has the detail. In short: `php bin/lang --stub=de` starts a
language, `php bin/lang de` tells you what is left, and English is the source.

You cannot put HTML in a translation. A string that needs a link around part of it
needs the template to build the link and the catalogue to supply the words.

## Themes and modules

`docs/THEMES.md` and `docs/MODULES.md` describe what each is given and what each
may not do. Two things worth knowing before you start:

A theme template is PHP that renders given data. It is not meant to reach for
more — no storage, no filesystem, no `$_GET`. Everything a page needs is handed
to it.

A module is deliberately fenced: it cannot read another module's data, cannot
touch the filesystem except through `addMedia()`, and cannot read the user table.
If you need something on that list, say so in an issue — it is either a gap in the
module API or a boundary that needs explaining, and both are worth knowing.

And whatever a module renders has to be legible with no stylesheet at all. A
`<label>` is inline, so a form built out of them lands on one line in every theme
that has never heard of your module.

## Reporting something

The most useful bug report says what you saw, not what you think it is. "The
timezone does not save" sent two rounds of work into the saving code; "it saves,
shows the old value, and is correct after navigating away" identified it as a
reading fault in ten minutes. It was OPcache.

If it is a security issue, `SECURITY.md` has the address. Do not open an issue for
it.

## What this is right now

A release candidate. One production site, since August 2026, and the updater has
never applied a real release — the plan is to tag 5.0 and roll it out with the
updater, so that last untested piece is proved while it still affects one install.

`docs/ISSUES.md` lists what is known to be wrong. It is kept honest: if something
on it is fixed, it says so, and if something was never reproduced, it says that
too.
