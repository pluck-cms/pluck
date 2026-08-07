# Translations

Every string a person reads lives in `lang/<code>.json`. English is the source of
truth, and the test suite enforces that rather than trusting anyone to remember.

## Adding a language

1. Copy `lang/en.json` to `lang/<code>.json`.
2. Translate the values. **Keep the keys exactly as they are.**
3. Check the plural forms (below).
4. Run `php tests/run.php` as an unprivileged user. `CatalogueTest` will tell you
   what is missing, in detail.

That is the whole process. There is no extraction step, no `.po` file, no
compilation.

## The tool

```sh
php bin/lang            every language, with a percentage
php bin/lang pl         exactly which keys are still English
php bin/lang --leaks    English typed straight into a template
php bin/lang --stub=de  start a new language from the English
```

Run `--leaks` even if you are not translating. A key nobody translated shows up
as a gap; English written directly into a view shows up as nothing at all and
stays English in every language forever. That is how Dutch reported 93% complete
while the page editor still said "Save page".

## What the tests enforce

`tests/CatalogueTest.php` is the specification. It fails the build when:

- a key exists in English but not in a translation
- a key exists in a translation but not in English (a leftover from a rename)
- a placeholder like `{count}` appears in one language and not the other
- a plural object is missing a category the language actually uses
- a key defined in English is used nowhere in the code
- a new hardcoded string appears in a view, past an agreed ceiling

The point is that a translation which drifts fails immediately, rather than
surfacing as a blank spot on someone's admin screen months later.

The scan covers `src/`, `views/`, `themes/` and the three entry points. **Themes
are included on purpose** — several `site.*` strings are used only by the bundled
theme, and without that the catalogue would call them dead and invite someone to
delete a string the 404 page needs.

## Placeholders

Written as `{name}` and substituted at render time:

```json
"blog.page_of": "Page {current} of {total}"
```

Placeholder names are part of the contract. `CatalogueTest` compares the set in
each language against English, so renaming one means renaming it everywhere.

Values are escaped individually before substitution, so a placeholder cannot
smuggle markup into a page even if what fills it came from a visitor.

## Plurals

A key that varies by number is an object rather than a string:

```json
"blog.reactions": {
	"one": "One reaction",
	"other": "{count} reactions"
}
```

Which categories a language uses is declared in `src/I18n/Plural.php`. Dutch,
English, German and most western European languages use `one` and `other`. Polish,
Russian and Ukrainian use `one`, `few` and `many`. Czech uses `one`, `few` and
`other`. Japanese, Chinese and Indonesian use only `other`.

A language with no rule falls back to the two-form pattern — wrong for Polish, but
predictable, and `CatalogueTest` reports it rather than letting it pass unnoticed.
Adding a language with a different rule means adding it to `Plural::CATEGORIES`.

Note that `one` is not the same as "the number 1". In several languages the `one`
form covers other numbers too, which is exactly why this is a table rather than an
`if`.

## Where strings must live

**In `lang/en.json`, not in the template.** `CatalogueTest` enforces this for
flash messages and for anything shown from JavaScript. Views carry a ratchet: a
counted number of literal words, which may go down and never up. Converting a
screen means lowering the constant, and the test prints the new number for you.

New user-facing text in a module or a theme belongs in the catalogue too. Modules
and themes may ship their own `lang/` directory; `Bootstrap::translator()` adds
those after core, and they are expected to namespace their keys so they cannot
overwrite a core string by accident.

## Escaping, and why translations are not trusted

`View::t()` escapes translated text, and there is deliberately no raw variant.

A theme may ship its own `lang/*.json`, and a theme arrives as an uploaded
archive. A translation is therefore attacker-influenceable in the same way page
content is. Escaping it means a hostile translation can produce visible text and
nothing else.

**And do not escape it again.** `<?= e($view->t('key', [...])) ?>` escapes twice,
so an apostrophe reaches the reader as `&amp;#039;`. It is invisible until a value
happens to contain one, at which point it is on every page that shows it — which
is how twenty-eight of them were written before anyone noticed. `t()` output goes
straight into the template, in attributes as well as text: it escapes with
`ENT_QUOTES`.

The practical consequence: **you cannot put HTML in a translation.** A string that
needs a link around part of it needs the template to build the link and the
catalogue to supply the words.

## Regional variants

`nl-BE` falls back to `nl`, then to `en`. A Flemish file only needs the words
that differ from Dutch — not the other five hundred. The same applies to `en-GB`
and `en-US`: English is written in British spelling, so an American file is a
handful of keys rather than a copy.

## Tone, and things worth watching

**Length.** A button that says "Save" in English and "Instellingen opslaan" in
Dutch still has to fit. The admin wraps rather than clips, but a nine-word button
looks like a mistake. Polish runs about 60% longer than English in places; that
is normal and fine.

**Formality.** Pluck addresses people directly and plainly — "Your account cannot
delete files", not "Insufficient privileges". Languages with a formal and an
informal you should pick the informal one unless that reads as rude: this is a
tool somebody uses alone, not a letter from a bank.

**Error messages say what to do.** "The session folder is not writable" is a
fact; "Give the web server write access to data/cache" is help. Where the English
does the second, do not translate it back into the first.

**Do not translate:** placeholders in braces, file paths, `ext-intl` and other
technical names, and the keys themselves.

## Languages that exist

| | |
|---|---|
| `en` | English — the source |
| `nl` | Dutch |
| `pl` | Polish, contributed |

Send a new one as a pull request against `pluck5.0`. Run `php bin/lang <code>`
first: if it says 100%, it is complete, and if it does not, say which parts you
left.

## Key naming

Prefix by area, dot-separated, lowercase with underscores:

```
site.page_not_found       the public site
nav.pages                 admin navigation
page.flash.saved          a flash message after saving a page
blog.read_more            the blog module
albums.count              the albums module
install.step_storage      the installer
```

The prefix is not decoration. `Bootstrap::translator()` loads theme and module
catalogues after core, and the namespace is what stops a module from redefining
`site.home` for the whole install.

## Dates and numbers

Dates do not go through the catalogue. `Support\Dates` formats them with
`ext-intl` in the site's language, falling back to the ISO form (`2021-05-03`)
where the extension is missing.

Do not translate month names into the catalogue by hand. `date('j F Y')` always
produces English month names regardless of locale, which is how a Dutch site ends
up saying "3 May 2021" — plausible enough to survive review for years.
