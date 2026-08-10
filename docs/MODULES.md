# Making a module

A module adds something to a site that pages cannot do on their own: a blog, an
album, a shop, a booking form. Pluck 5 ships three — blog, albums and a contact
form — and they are written against the same interfaces yours will be.

The 4.x word was "plugin". They were PHP files that got included into the admin
and did whatever they liked with the global scope. That is the one thing v5 will
not let you do, and everything below follows from it.

## What a module is

```
modules/mine/
  module.json
  Mine.php
```

`module.json`:

```json
{
	"name": "mine",
	"title": "My module",
	"version": "1.0.0",
	"author": "Your name",
	"license": "GPL-3.0-or-later",
	"requires": "5.0"
}
```

The `name` is used as an address and as a storage prefix, so it is lowercase,
letters and dashes only, and it cannot be `search` — that one is reserved.

## Showing something on the site

Implement `Pluck\Module\SiteModule`:

```php
<?php
declare(strict_types=1);

namespace Pluck\Module\Mine;

use Pluck\Module\ModuleView;
use Pluck\Module\SiteModule;
use Pluck\Security\Escaper;
use Pluck\Site\Urls;
use Pluck\Storage\StorageDriver;

final class Mine implements SiteModule
{
	public function name(): string
	{
		return 'mine';
	}

	/** Everything under /notices belongs to this module. */
	public function mountPath(): string
	{
		return 'notices';
	}

	/**
	 * $path is what came after the mount: '' for /notices, 'first' for
	 * /notices/first. Return null and Pluck renders its own 404.
	 */
	public function render(string $path, array $query, StorageDriver $storage, Urls $urls): ?ModuleView
	{
		if ($path !== '') {
			return null;
		}

		$html = '<ul>';
		foreach ($storage->listModuleData('mine', 'notice:') as $notice) {
			$html .= '<li>' . Escaper::html((string) ($notice['text'] ?? '')) . '</li>';
		}

		return new ModuleView(title: 'Notices', html: $html . '</ul>');
	}

	/** Optional: `[module:mine]` in a page's text renders this. */
	public function embed(array $parameters, StorageDriver $storage, Urls $urls): ?string
	{
		return $this->render('', [], $storage, $urls)?->html;
	}

	/** Optional: your things appear in the site's search. */
	public function search(string $query, StorageDriver $storage): array
	{
		return [];
	}
}
```

## Storing things

`StorageDriver` is the only thing you touch. No files, no database connection, no
`$_SESSION`:

```php
$storage->setModuleData('mine', 'notice:2026-08-03', ['text' => 'Closed on Friday']);
$storage->getModuleData('mine', 'notice:2026-08-03');
$storage->listModuleData('mine', 'notice:');   // everything with that prefix
$storage->deleteModuleData('mine', 'notice:2026-08-03');
```

The first argument is always your module's name, and Pluck keeps you inside it —
you cannot read another module's data by passing its name. Keys are yours to
choose; the convention is `thing:identifier`, because `listModuleData` matches on
prefix and a good prefix is what makes a listing cheap.

This works identically on flat files and on SQLite. Write against the interface
and the site owner's choice of driver stops being your problem.

## An admin screen

Implement `Pluck\Module\AdminModule`. Your controller is given a
`ModuleContext` rather than the run of the admin:

```php
public function handle(string $action, ModuleContext $context): ModuleResponse
{
	if ($action === 'save') {
		$context->requireCsrf();

		$text = $context->post('text', '');
		$context->set('notice:' . gmdate('Ymd-His'), ['text' => $text]);

		return ModuleResponse::back('Saved.');
	}

	return ModuleResponse::view('index', ['notices' => $context->all('notice:')]);
}
```

`ModuleContext` gives you: `get`/`set`/`all`/`delete` for your own data,
`post()` and `query()` for the request, `requireCsrf()`, `addMedia()`, and
`can(ModulePermission::manage('mine'))`. It gives you nothing else, and that is
the point — a module cannot read the user table, cannot write outside its own
data, and cannot reach the filesystem.

Templates live in `modules/mine/views/` and are rendered the same way themes are:
`e()` on everything, `$view->t()` for wording.

## Taking something from a visitor

A form anybody on the internet can submit needs protecting, and you should not
write that protection yourself. Implement `Pluck\Module\PublicForm`:

```php
public function accept(string $path, array $post, StorageDriver $storage, Urls $urls, Guard $guard): array
{
	// The Guard has already run: honeypot, timing, rate limit, and whatever
	// challenge the site has turned on. Do not repeat those checks, and do not
	// skip them — the front controller runs them, not you.
	$name = trim(strip_tags((string) ($post['name'] ?? '')));

	if ($name === '') {
		return ['ok' => false, 'message' => 'form.error.fill_it_in', 'redirect' => null];
	}

	$storage->setModuleData('mine', 'entry:' . gmdate('Ymd-His'), ['name' => $name]);

	return ['ok' => true, 'message' => 'mine.thanks', 'redirect' => $urls->to('notices')];
}
```

Render the form with `$guard->fields()` and `$guard->challenge()`. Writing those
inputs by hand is how a second form ships with one of them missing.

**Emit markup that works with no stylesheet.** A `<label>` is inline, so a form
built out of them lands on one line in any theme that has never heard of your
module — which is every theme but the one you tested on. A paragraph per field,
with the label above the box, stacks on its own:

```html
<p class="field"><label for="x">Your name</label><br><input id="x" name="x"></p>
```

A theme can then style `.field` to make it look like the rest of the site, but it
does not have to for the form to be usable. This applies to anything a module
renders: the theme is not yours to assume.

## Wording

No English in your PHP. Put it in `modules/mine/lang/en.json` and reach for it
with `$view->t('mine.some.key')` — the site's own language files and yours are
merged, so a translator can do your module without touching Pluck.

## Saying what can be inserted from your module

The editor's **Pluck** menu lists what a page can hold. Implement `Insertable`
and your module appears there with its own options:

```php
use Pluck\Module\Insertable;

final class RecipesModule implements SiteModule, Insertable
{
	public function embedOptions(StorageDriver $storage): array
	{
		$options = [
			['label' => $this->t('recipes.insert.latest'), 'marker' => '[module:recipes count=5]'],
		];

		foreach ($storage->listModuleData('recipes', 'course:') as $key => $course) {
			$options[] = [
				'label' => (string) $course['name'],
				'marker' => '[module:recipes course=' . substr($key, 7) . ']',
			];
		}

		return $options;
	}
}
```

Optional. A module without it is offered as `[module:name]`, which is what every
module got before this existed.

Two things worth knowing.

**Storage is passed because the useful answers are about content.** "All albums"
is a fair option; "Open dag 2019" is the one somebody is actually looking for.
List what this site has, not what a module could in principle hold.

**Something the writer has to fill in** goes in as a placeholder:

```php
[
	'label' => $this->t('video.insert.url'),
	'marker' => '[module:video id=PLAK-HIER-DE-YOUTUBE-LINK]',
	'select' => 'PLAK-HIER-DE-YOUTUBE-LINK',
],
```

The editor selects that text after inserting, so the next thing typed replaces
it. Write the placeholder as words rather than as `<id>` or `%s`: one that
survives into a saved page has to read on the live site as "somebody did not
finish this", not as markup that looks deliberate. And make sure your module says
something useful when it arrives — a placeholder that renders as a blank space is
worse than one that renders as a sentence explaining itself.

**The editor is told, never asked to work it out.** It has no idea that the blog
takes `show=summary` — it renders labels and markers it was handed. An editor
that knew would be a second place holding your module's parameters, and the two
drift the first time either is touched. There is a test asserting the editor
mentions no module by name.

## The bundled blog's embed

`[module:blog]` takes three parameters:

```
[module:blog count=5]
[module:blog count=5 category=recepten]
[module:blog count=7 show=summary]
```

`show=summary` renders the same summaries the blog's own index uses — title,
byline, excerpt, and the post's first picture as `.blog-post__thumb` — rather
than a list of titles. It exists because a site whose posts *are* the page wants
the pictures and the first lines: a caterer whose weekly menu is one post per
dish is choosing with their eyes, and seven links is not a menu.

A theme that does not style `.blog-post__thumb` shows no picture, so nothing
changes for anybody who liked the older shape.

## Embedding a frame

The sanitiser strips `<iframe>` and should keep doing so: an allow-list that
accepts a frame accepts one pointing anywhere, and a page is text somebody typed.
A module renders on the other side of that line, so a video embed belongs in one.

The CSP still refuses it. `frame-src` falls back to `default-src 'self'`, and a
module cannot widen that on its own — one that could would be one that can point
a frame anywhere. An owner ticks the service under **Settings → Video en kaarten in een pagina**,
which is checked against `Csp::frameHostNames()` rather than taken as written.

Worth doing what the bundled video module does: render a still and a link, and
only put the frame in the page once somebody has clicked. An embed in the markup
means every visitor to the page is a visitor to that service whether they watch
or not, and nobody agreed to that by reading a page.

## What a module cannot do

Deliberately, and these are the ones people ask about:

- reach the filesystem, except through `addMedia()`
- read or write another module's data
- read the user table, or change permissions
- run at every request — a module runs when its address is asked for
- add a `<script>` to the site; the CSP has no nonce for you

If you need something on that list, the honest answer is that Pluck 5 is the
wrong place for it and a small application beside the site is the right one.

## Installing one

Put the folder in `modules/`, then tick it under **Settings → Extra modules**.

Both steps are required, and that is the point. "Drop a folder in and it runs" is
how Pluck 4 worked, and it is exactly what made a compromised install so easy to
keep: a web shell dropped into `data/modules` was a module. A folder here that
nobody has named is inert.

`modules/` is preserved by the updater. Anything in `src/` is not — that
directory is replaced wholesale — so a module of your own belongs here and
nowhere else.

To remove one, delete the folder. Its data stays in `data/` until you remove
that too, which is deliberate: an accidentally deleted module folder should not
take a year of blog posts with it.

## Bringing a 4.x plugin across

```sh
php bin/migrate-module /old/site/data/modules/name
```

It reports what the plugin did — which hooks it used, what it wrote, where it
wrote it — and produces a skeleton against these interfaces. It does not convert
the code, and it is not pretending to: a 4.x plugin's whole shape assumed global
scope, and the interesting part is deciding what it should be instead.

Expect to rewrite it. The report is there so you know what you are rewriting.
