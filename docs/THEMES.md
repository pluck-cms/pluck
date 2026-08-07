# Making a theme

A theme in Pluck 5 is a folder of templates and a stylesheet. There is no build
step, no compiler and nothing to install: you write the files, put the folder in
`themes/`, and pick it under Settings → Appearance.

If you wrote themes for Pluck 4, one thing has changed and it is the important
one. A 4.x theme was PHP that ran as part of the site and could do anything a
plugin could. A v5 template is PHP too, but it renders *given* data and is not
meant to reach for more — no database, no filesystem, no `$_GET`. The rule is not
enforced by the language; it is enforced by not needing to break it, because
everything a page needs is already handed to you.

## The smallest theme that works

```
themes/mine/
  theme.json
  templates/
    layout.php
    page.php
  assets/
    style.css
```

`theme.json`:

```json
{
	"name": "Mine",
	"version": "1.0.0",
	"author": "Your name",
	"license": "GPL-3.0-or-later",
	"description": "One sentence about what it looks like."
}
```

`templates/page.php` — the page itself, with no surroundings:

```php
<h1><?= e($page->title) ?></h1>
<?= $page->content ?>
```

`templates/layout.php` — everything around it:

```php
<!DOCTYPE html>
<html lang="<?= e($locale) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($documentTitle) ?></title>
<link rel="stylesheet" href="<?= e($themeAssets) ?>/style.css">
</head>
<body>
	<h1><?= e($siteTitle) ?></h1>

	<nav>
		<ul>
<?php foreach ($menu->items() as $item): ?>
			<li><a href="<?= e($urls->to($item->path())) ?>"><?= e($item->title()) ?></a></li>
<?php endforeach; ?>
		</ul>
	</nav>

	<main><?= $content ?></main>
</body>
</html>
```

That is a working site. Everything below is detail.

## Escaping

`e()` escapes. Use it on everything except two things: `$content`, and a page's
`content`, which have been through the sanitiser already and are markup on
purpose.

`$view->t('some.key')` returns text that is **already escaped**. Do not wrap it
in `e()` — the result is `Pagina&#039;s` on screen, which is a bug this project
has shipped twice. If a value comes from the translator, print it directly.

## What a template is given

| | |
|---|---|
| `$page` | the `Page` being shown: `->title`, `->content`, `->path` |
| `$content` | the rendered page, for `layout.php` to place |
| `$title`, `$documentTitle` | the page's title, and the one for `<title>` |
| `$description`, `$keywords`, `$canonical`, `$noindex` | for the `<head>` |
| `$menu` | `->items()`, `->isEmpty()`; each item has `->title()`, `->path()`, `->active`, `->children` |
| `$trail` | the breadcrumb trail to the current page |
| `$urls` | `->to('some/path')` and `->media('file.jpg')` — always build addresses with these |
| `$siteTitle`, `$tagline`, `$logo`, `$contactEmail` | from Settings |
| `$themeAssets` | the address of your own `assets/` folder |
| `$locale`, `$view` | the language, and `$view->t()` for wording |

**Always use `$urls->to()`.** A site may be installed in a sub-directory, and it
may or may not have readable addresses switched on. `$urls` knows; a hand-written
`/about` does not, and breaks on every install that is not at the root.

## The other templates

`layout.php` and `page.php` are required. These are optional, and Pluck falls
back to a plain rendering if they are missing:

- `module.php` — a blog post, an album, anything a module renders
- `search.php` — search results
- `404.php` — a page that is not there
- `partials/menu.php` — the menu, if you want it recursive

Put a partial anywhere with `$view->partial('partials/menu', ['items' => $items])`.

## Modules inside a page

Somebody writing a page can put `[module:blog count=3]` in the text. Nothing in
the theme has to handle it — by the time the content reaches you it is already
rendered. Style it with whatever classes the module emits: `.blog-post`,
`.album-list`, `.search-result`.

## JavaScript

None of the bundled themes has any, and that is worth copying. The menu opens
with a checkbox:

```html
<input type="checkbox" id="menu-open" class="menu-toggle">
<label for="menu-open" class="menu-button">☰</label>
<nav class="mainnav">…</nav>
```

```css
.menu-toggle { position: absolute; opacity: 0; }
.menu-button { display: none; }

@media (max-width: 52rem) {
	.menu-button { display: inline-block; }
	.mainnav { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .2s; }
	.mainnav > ul { overflow: hidden; }
	.menu-toggle:checked ~ .mainnav { grid-template-rows: 1fr; }
}
```

This is not minimalism for its own sake. The framework several 4.x themes carried
bound `touchmove` while its menu was open and cleared the flag only when a
closing animation finished — an interrupted animation left the page unscrollable
until it was reloaded. A checkbox has no state to get stuck in.

## Tables

If you want column widths to work, add this. Without it a browser sizes columns
to their contents and treats the widths as advice:

```css
table:has(colgroup) { table-layout: fixed; }
```

## Dark mode

`prefers-color-scheme` and a handful of custom properties is the whole of it:

```css
:root { --paper: #fff; --ink: #1c2320; }

@media (prefers-color-scheme: dark) {
	:root { --paper: #171b19; --ink: #e6ebe7; }
}
```

## Converting a 4.x theme

```sh
php bin/convert-theme /old/site/data/themes/name themes/name
```

It translates the eight `theme_*` functions that thirty surveyed themes actually
used, copies the assets, and turns anything else into an HTML comment so you can
see what it could not do. Of thirty themes tried, all thirty rendered and twenty
came out needing no further work.

Expect to finish it by hand. It is a starting point, not a conversion.

## Testing it

Set the theme, then look at: a page with sub-pages, a blog post, an album, a
search result, a 404, and a page whose title is long enough to wrap. The page
editor's preview renders the whole page through your theme, so most of that can
be done without leaving the admin.

## Sharing it

A theme is a folder. Zip it and send it; whoever gets it unpacks it into
`themes/`. There is no theme installer yet — it is on the roadmap, and the reason
it is not built is that an archive is untrusted input and a template is code the
site executes.
