# Security

## Reporting a vulnerability

Report privately through GitHub's advisory form on `pluck-cms/pluck`
("Security" → "Report a vulnerability"). That opens a private thread with the
maintainers.

Please do not open a public issue for anything exploitable, and please do not
report it only on a forum or a chat — those get lost.

Tell us what you can: the version, whether an account is needed and at which
role, and the smallest request or file that shows the problem. A proof of
concept is welcome but not required; a clear description of the mechanism is
usually enough.

You will get an acknowledgement within a few days. This is a volunteer project,
so a fix takes as long as it takes, but you will be told where it stands rather
than left guessing. Credit in the advisory unless you would rather not have it.

## What is supported

| Version | Supported |
| --- | --- |
| 5.x | Yes, once released |
| 4.7.x | Security fixes only |
| Older | No |

Pluck 5 is not released yet. Until it is, report issues in it as bugs.

## How version 5 differs

Pluck 4's approach to injection was a blacklist: `data/inc/security.php`
inspected `$_GET` for suspicious substrings, and the templates echoed values
raw. The file carried a comment calling itself a quick and dirty fix. It was
bypassed repeatedly, because a blacklist of attack strings is a list of the
attacks somebody already thought of.

Version 5 inverts it. Input is accepted as it arrives. Every value is escaped
for the context it lands in (`Security\Escaper`), and HTML that gets stored is
run through an allow-list sanitiser on the way in (`Security\Sanitizer`, which is
why `ext-dom` is a hard requirement). There is no blacklist to bypass.

The rest, briefly:

- **Sessions.** Regenerated on privilege change, bound to a fingerprint, and
  re-authentication is forced after twelve hours whatever the cookie says.
- **CSRF.** Verified in the router for every POST, before any controller runs, so
  no screen can be the one that forgot. Tokens are bound to an action. This is
  what closes the family of 4.x issues where an attacker got a logged-in admin to
  click a link.
- **Passwords.** Hashed with PHP's own `password_hash()` default (bcrypt on
  current PHP versions; Pluck picks up Argon2id automatically if a future PHP
  release changes that default), with a policy check that prefers length over
  punctuation — twelve characters minimum, nothing about mixing cases or
  symbols. Sign-in is throttled and compares against a dummy hash when the
  account does not exist, so a wrong username and a wrong password take the
  same time.
- **Uploads.** The stored filename is rebuilt from a slug plus one validated
  extension, never cleaned (`Support\UploadName`). The declared content type is
  ignored in favour of what the bytes look like. `media/.htaccess` turns handlers
  off and refuses executable extensions outright.
- **Archives.** Themes are data, not code. There is no feature that unpacks
  attacker-supplied PHP into the document root, because that is remote code
  execution with a friendly button. `Archive\EntryPolicy` judges each entry and
  `Archive\SafeZip` writes nothing at all unless the whole archive passes.
- **Paths.** Anything that becomes a filesystem path goes through
  `Support\Path::within()`, which resolves and then checks containment.
- **Headers.** CSP with a per-request nonce, plus the usual set, sent from one
  place.

## Known 4.x issues and where they are now tested

Each of these has a test that names it. The test is the specification: if you
change the code it covers, the assertion is what you have to argue with.

| Issue | What it was | Where |
| --- | --- | --- |
| CVE-2018-19420 | Filename itself as stored XSS, because the character set for names was not restricted | `tests/UploadNamingTest.php` |
| CVE-2022-26965, #85 | Theme install accepting a `theme.php` that is a shell | `tests/EntryPolicyTest.php`, `tests/SafeZipTest.php` |
| CVE-2026-31205, #64 | Stored XSS in page content from before the sanitiser | `tests/SecurityTest.php`, `tests/MigrateTest.php` |
| #96 | `.phar` and double extensions surviving upload | `tests/UploadNamingTest.php` |
| #100 | Zip slip: an archive writing outside its target | `tests/EntryPolicyTest.php`, `tests/SafeZipTest.php` |
| #039 | CSRF on state-changing admin links | `tests/CsrfSurfaceTest.php` |
| #27 | Non-latin titles producing timestamp addresses | `tests/SlugTest.php`, `tests/MigrateTest.php` |

## If you are still on 4.7

4.7 gets security fixes and nothing else. The realistic options are to keep it
patched and behind whatever hardening your host offers, or to migrate — see
MIGRATION.md, which is explicit about what the migrator does with content that
was stored before any of the above existed.
