# Translating Pluck

Every word a person sees comes from a JSON file in `lang/`. English is the source;
the rest are translations of it, and anything a translation is missing falls back
to English rather than showing a key.

## Making one

```sh
php bin/lang --stub=de
```

That writes `lang/de.json` containing the English strings. Translate the values,
leave the keys alone, and the language appears under Settings — and under each
person's own account, so a Dutch site can have an English-reading administrator.

Check your progress at any time:

```sh
php bin/lang            # every language, with a percentage
php bin/lang de         # exactly which keys are still English
php bin/lang --leaks    # English typed straight into a template
```

The last one is worth running even if you are not translating. A key nobody
translated shows up as a gap; English written directly into a view shows up as
nothing at all and stays English in every language forever. That is how the Dutch
translation was 93% complete and still had "Save page" in the page editor.

## What a file looks like

```json
{
	"nav.pages": "Pagina's",
	"page.flash.page_deleted": "Pagina verwijderd.",
	"blog.reactions": {
		"one": "Eén reactie",
		"other": "{count} reacties"
	}
}
```

Most values are strings. A few are objects, for wording that changes with a
number.

**Placeholders in braces stay exactly as they are.** `{count}`, `{name}`,
`{theme}` are replaced at run time, and a translated `{aantal}` is a hole in a
sentence.

## Plurals

Give the forms your language actually has. English and Dutch need two:

```json
"blog.reactions": { "one": "Eén reactie", "other": "{count} reacties" }
```

Polish needs three, and Pluck asks for them by name:

```json
"blog.reactions": {
	"one": "{count} komentarz",
	"few": "{count} komentarze",
	"many": "{count} komentarzy"
}
```

The categories are the CLDR ones: `zero`, `one`, `two`, `few`, `many`, `other`.
Use the ones your language has and leave out the rest. If you are unsure, `one`
and `other` is right for most European languages.

## Regional variants

`nl-BE` falls back to `nl`, then to `en`. So a Flemish file only needs the words
that differ from Dutch — it does not need the other five hundred.

The same applies to `en-GB` and `en-US`. English is written in `en.json` in
British spelling; an American file would be a handful of keys, not a copy.

## What to look out for

**Length.** A button that says "Save" in English and "Instellingen opslaan" in
Dutch still has to fit. The admin wraps rather than clips, but a nine-word button
looks like a mistake.

**Tone.** Pluck addresses people directly and plainly — "Your account cannot
delete files", not "Insufficient privileges". Languages with a formal and an
informal you should pick the informal one unless that reads as rude: this is a
tool somebody uses alone, not a letter from a bank.

**Error messages say what to do.** "The session folder is not writable" is a
fact; "Give the web server write access to data/cache" is help. Where the English
does the second, do not translate it back into the first.

**Do not translate:** placeholders in braces, HTML tags inside a value, file
paths, `ext-intl` and other technical names, and the keys themselves.

## Dates

Dates are formatted by `ext-intl` where it exists, which knows your language's
conventions without being told. Where it does not exist — and it is missing from
a lot of shared hosting — dates fall back to `2026-08-03`, in every language. If
your translation reads oddly around dates, that is why, and the fix belongs on
the server rather than in the file.

## Languages that exist

| | |
|---|---|
| `en` | English — the source |
| `nl` | Dutch |
| `pl` | Polish, contributed |

## Sending it back

A language file is one JSON file. Open a pull request on `pluck-cms/pluck`, or
send the file. Run `php bin/lang <code>` first — if it says 100%, it is complete,
and if it does not, say which parts you left.

Translations are more useful than they look. Pluck exists so somebody can run
their own site on the cheapest hosting there is, and the people that matters most
for are frequently not reading English.
