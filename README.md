# Pluck 5

A small, file-first content management system. No database required, no build
step, no composer install to get a working site: upload the folder, open
`install.php`, and you have a site.

Version 5 is a rewrite of the parts of Pluck 4 that could not be repaired in
place — the security model above all — while keeping the thing that made Pluck
worth using: it runs on the cheapest shared hosting there is, and you can
understand the whole of it in an afternoon.

**Status: phases 1 to 3.** The admin area, both storage drivers, the installer,
authentication and the 4.x migrator are written and tested. Themes, modules and
the self-updater are not. Do not put this on a live site yet.

## What you need

- PHP 8.3 or newer
- `ext-json`, `ext-mbstring`, `ext-dom` — all three are in every normal PHP build
- `ext-pdo_sqlite` only if you want the database storage option
- `ext-intl` optional; without it, page addresses in non-latin scripts keep their
  original characters instead of being transliterated

The installer checks all of this and tells you which of it is a blocker and which
is only a note.

## Installing

    git clone -b v5 https://github.com/pluck-cms/pluck.git
    # upload the contents somewhere the web server can read
    # open https://your-site/install.php in a browser

The installer asks two things: a language, and then the site title, your account
and where content should be stored. When it finishes it deletes itself. If it
cannot — some hosts make the document root read-only for the web server — it
says so and you remove `install.php` by hand.

With Docker:

    docker compose up --build      # http://localhost:8080

The image is Apache with mod_php, because that is the shape of the hosting Pluck
actually runs on. `data/` and `media/` are volumes; back those two up and you
have backed up the site.

## Where things live

    admin.php            the admin front controller
    index.php            the site front controller
    install.php          deletes itself when it is done
    bin/migrate          the 4.x migrator (see docs/MIGRATION.md)
    src/                 all the code, PSR-4 under the Pluck namespace
    views/               admin templates, plain PHP
    themes/              site themes; `default` is the bundled one
    lang/                translation catalogues, one JSON file per language
    assets/admin/        one stylesheet and one script, no build step
    data/                content, settings, accounts. Never served
    media/               uploads. Served, never executed
    tests/               php tests/run.php, no dependencies
    docs/                see below

## Contributors

Polish translation by the Pluck community. Windows path handling reported and
diagnosed by a contributor testing on XAMPP — Pluck did not run there at all,
and nothing in this suite could have found it.

## Contributing

Pull requests against `pluck5.0`. [CONTRIBUTING.md](CONTRIBUTING.md) has what the
project asks of a change; the short version is that `php tests/run.php` and
`php bin/lang --leaks` both have to pass.

## Documentation

- [Making a theme](docs/THEMES.md)
- [Making a module](docs/MODULES.md)
- [Translating Pluck](docs/TRANSLATIONS.md)

| | |
|---|---|
| `docs/ARCHITECTURE.md` | How the layers fit together, and the theme and module contracts |
| `docs/CONFIG.md` | `config.php`, stored settings, and which is which |
| `docs/MIGRATION.md` | Moving a 4.x site across |
| `docs/TRANSLATIONS.md` | Adding a language, plurals, and why translations are escaped |
| `docs/ISSUES.md` | What is missing or wrong, honestly |
| `docs/ROADMAP.md` | Phases, and what is left before 5.0 |
| `NGINX.md` | The `.htaccess` rules, restated for nginx, where none of them apply |
| `SECURITY.md` | The model, and the 4.x CVEs each test pins down |

## Storage

You choose at install time and can change your mind later by exporting and
reimporting.

**Flat file** writes `data/content/pages/<slug>.json`, one file per page, with
children in a folder of the same name. You can read it, grep it, and put it in
git. This is the default and what most sites should use.

**SQLite** writes one `data/pluck.sqlite` in WAL mode. Worth it once a site has
enough pages that listing them means opening hundreds of files.

Both drivers implement the same interface and the test suite runs the same 128
assertions against each of them, so a site behaves identically either way.

## Accounts

Four roles. Pluck 4 had one shared password for the whole install; the point of
five is that you can hand someone an account without handing over the site.

| Role | Can |
| --- | --- |
| Owner | Everything, including accounts and updates. Cannot be demoted by anyone else. |
| Administrator | Everything except managing owners. |
| Editor | All content, files and module settings. No site settings, no accounts. |
| Author | Their own pages, and uploading files. |

Sign-in is throttled (five attempts, then a fifteen-minute lock that lengthens on
each further lock), and TOTP two-factor is available per account.

## Translations

The admin area reads `lang/<code>.json`. Keys are flat and dotted
(`page.flash.page_saved`), which exists so `tests/CatalogueTest.php` can check
both directions: every key the code asks for is defined, and every key defined is
actually used. A translation that drifts fails the build rather than showing up
as a blank screen months later.

English is the source of truth. To add a language, copy `lang/en.json`, translate
the values, keep the keys, and check the plural forms match what your language
uses.

## Running the tests

    php tests/run.php

**Run it as an unprivileged user.** The suite refuses to start under uid 0 and
says so. That guard is there because the cleanup routine used to follow symlinks:
`PathTest` deliberately creates one to prove the path sanitiser refuses it, and a
recursive delete that trusts `is_dir()` walks straight through it and empties
whatever it points at. Run as root, that was most of `/etc`. It is fixed, the
guard is the second line of defence, and `PLUCK_ALLOW_ROOT_TESTS=1` overrides it
if a container genuinely offers no other user.

No PHPUnit, no composer install — the runner is 40 lines and the base class is
three assertion methods. That is deliberate: someone fixing one screen should be
able to run the suite without setting up a toolchain first.

Roughly 1720 assertions across 25 suites. Anything the current PHP build cannot
run is reported as a skip rather than silently passing.

## Contributing

The v5 work happens on the `v5` branch of `pluck-cms/pluck`. Two things are worth
knowing before you send a patch:

- Every known security issue from 4.x has a test naming it. If you change
  something those tests cover, the test is the specification, not the code.
- New user-facing text goes in `lang/en.json`, not in the template. The suite
  enforces this for flash messages and for javascript; views still have a ratchet
  that may only go down.

## Licence

GPL-3.0-or-later, as Pluck has always been.
