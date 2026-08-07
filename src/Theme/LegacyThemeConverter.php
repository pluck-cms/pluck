<?php
declare(strict_types=1);

namespace Pluck\Theme;

use Pluck\Support\Path;
use RuntimeException;

/**
 * Turning a Pluck 4 theme into a Pluck 5 one.
 *
 * This looked impossible at first and turned out not to be. A 4.x theme is PHP
 * that runs inside the page, which is exactly the shape v5 refuses — but reading
 * the thirty themes in `pluck-cms/themes` shows the PHP is not arbitrary. The
 * entire vocabulary across all of them is eight functions and a handful of
 * variables. That is a small language, and a small language can be translated.
 *
 * What that means in practice: the markup, the stylesheet and the images come
 * across intact, and the holes where Pluck 4 injected something become the v5
 * equivalent. What cannot come across is anything that has no counterpart —
 * module spaces above all, since v5 mounts modules at addresses rather than
 * dropping them into slots a theme declares.
 *
 * Nothing is dropped silently. Anything unrecognised is left in place as an HTML
 * comment and named in the report, because a theme that quietly loses a feature
 * is worse than one that says which line needs a person.
 */
final class LegacyThemeConverter
{
	/** @var list<string> */
	private array $notes = [];

	/** @var list<string> */
	private array $needsAttention = [];

	/**
	 * Convert $source into a v5 theme at $target.
	 *
	 * @return array{name:string,title:string,files:int,notes:list<string>,attention:list<string>}
	 */
	public function convert(string $source, string $target): array
	{
		$this->notes = [];
		$this->needsAttention = [];

		$layout = $this->find($source, ['theme.php']);
		if ($layout === null) {
			throw new RuntimeException('This does not look like a Pluck theme: there is no theme.php.');
		}

		[$name, $title] = $this->identify($source, basename($source));

		Path::ensureDir($target . '/templates');
		Path::ensureDir($target . '/assets');

		$files = 0;

		file_put_contents(
			$target . '/templates/layout.php',
			$this->translate((string) file_get_contents($layout), $title),
		);
		$files++;

		// v5 wants a page template as well as a layout. A 4.x theme has no such
		// split — the content hole is inside theme.php — so this is the smallest
		// thing that satisfies the contract and lets the layout keep doing the work.
		file_put_contents($target . '/templates/page.php', $this->pageTemplate());
		$files++;

		$files += $this->copyAssets($source, $target . '/assets');

		file_put_contents(
			$target . '/theme.json',
			(string) json_encode([
				'name' => $title,
				'version' => '1.0.0',
				'description' => 'Converted from a Pluck 4 theme. Check the layout before using it on a live site.',
				'converted_from' => basename($source),
			], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
		);
		$files++;

		return [
			'name' => $name,
			'title' => $title,
			'files' => $files,
			'notes' => $this->notes,
			'attention' => $this->needsAttention,
		];
	}

	// ---- the translation ------------------------------------------------

	/**
	 * Rewrite the 4.x holes into their v5 equivalents.
	 *
	 * Done with patterns rather than by parsing PHP, and that is a deliberate
	 * limit: this handles the calls the published themes actually make, and marks
	 * everything else for a person. Parsing PHP properly to translate it would be
	 * a compiler, and a compiler that gets a theme subtly wrong is worse than a
	 * comment saying "look at this line".
	 */
	private function translate(string $php, string $title): string
	{
		// Leftovers are found *first*, while every PHP block in the file is still
		// 4.x code. Doing it afterwards means telling my own generated foreach
		// loops apart from the theme's own logic, and the exception that needs
		// would let a 4.x `if` block through — which is how the first version of
		// this left executable code in a v5 template.
		$out = $this->flagLeftovers($php);

		// The head. 4.x had one call that wrote the title, the meta tags and the
		// stylesheet link; v5 splits those, so this expands into all of them.
		$out = $this->replaceCall($out, 'theme_meta', function (): string {
			return '<meta charset="utf-8">' . "\n"
				. '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
				. '<title><?= e($documentTitle) ?></title>' . "\n"
				. '<?php if ($description !== \'\'): ?>' . "\n"
				. '<meta name="description" content="<?= e($description) ?>">' . "\n"
				. '<?php endif; ?>' . "\n"
				. '<?php if ($canonical !== null): ?>' . "\n"
				. '<link rel="canonical" href="<?= e($canonical) ?>">' . "\n"
				. '<?php endif; ?>' . "\n"
				. '<link rel="stylesheet" href="<?= e($themeAssets) ?>/style.css">';
		});

		$out = $this->replaceCall($out, 'theme_sitetitle', static fn (): string => '<?= e($siteTitle) ?>');
		$out = $this->replaceCall($out, 'theme_pagetitle', static fn (): string => '<?= e($title) ?>');
		$out = $this->replaceCall($out, 'theme_content', static fn (): string => '<?= $content ?>');

		$out = $this->replaceCall($out, 'theme_menu', fn (string $args): string => $this->menu($args));
		$out = $this->replaceCall($out, 'theme_submenu', function (): string {
			$this->attention('theme_submenu() has no direct equivalent; the sub-pages of the open branch are listed instead.');

			return $this->menuFromTags('ul', 'li', 'active', true);
		});

		// Module spaces are the one thing that genuinely does not carry over.
		foreach (['theme_module', 'theme_area'] as $call) {
			$out = $this->replaceCall($out, $call, function (string $args) use ($call): string {
				$space = trim($args, "'\" \t");
				$this->attention(sprintf(
					'%s(%s) removed: Pluck 5 has no module spaces. A module lives at its own address, and a page shows one with [module:name] in its text.',
					$call,
					$space === '' ? '' : "'" . $space . "'",
				));

				return sprintf('<!-- was %s(\'%s\') - see [module:name] in a page -->', $call, $space);
			});
		}

		// The loose variables, all of which have a v5 counterpart.
		$out = preg_replace(
			[
				'/<\?php\s*echo\s+SITE_URL\s*;?\s*\?>/i',
				'/<\?php\s*echo\s+LANG\s*;?\s*\?>/i',
				'/<\?php\s*echo\s+\$site_(?:title|name)\s*;?\s*\?>/i',
				'/<\?php\s*echo\s+"?\$site_theme"?\s*;?\s*\?>/i',
			],
			[
				'<?= e($urls->base()) ?>',
				'<?= e($locale) ?>',
				'<?= e($siteTitle) ?>',
				'<?= e($themeAssets) ?>',
			],
			$out,
		) ?? $out;

		// 4.x signed in at login.php.
		$out = str_replace(['"login.php"', "'login.php'"], ['"admin.php"', "'admin.php'"], $out);

		return $this->header($title) . $out;
	}

	/**
	 * `theme_menu('<li><a href="#file">#title</a></li>', '...active...')`
	 *
	 * The templates use `#file` and `#title` as holes. Both forms appear in the
	 * published themes: one argument, or one plus a variant for the current page.
	 * An older four-argument form exists too and is not worth translating — those
	 * themes get the standard partial and a note.
	 */
	private function menu(string $args): string
	{
		$templates = $this->stringArguments($args);

		// By far the commoner form in the published themes:
		// theme_menu('ul', 'li', 'active', 1) — a wrapper tag, an item tag, the
		// class for the current page, and whether to include sub-pages. All four
		// have an exact counterpart here, so this converts cleanly.
		if (count($templates) >= 2 && preg_match('/^[a-z]{1,10}$/', $templates[0]) === 1 && preg_match('/^[a-z]{1,10}$/', $templates[1]) === 1) {
			return $this->menuFromTags(
				$templates[0],
				$templates[1],
				$templates[2] ?? 'active',
				str_contains($args, ', 1') || str_contains($args, ',1') || str_contains($args, ', true'),
			);
		}

		if (count($templates) < 1 || !str_contains($templates[0], '#file')) {
			$this->attention('theme_menu() was called in a form this does not translate; a plain list was used instead.');

			// Inline, not a partial: a converted theme has to stand on its own, and
			// referring to partials/menu made six of the thirty published themes
			// fail to render at all because that file lives in the bundled theme.
			return $this->menuFromTags('ul', 'li', 'active', false);
		}

		$normal = $this->menuTemplate($templates[0]);
		$active = $this->menuTemplate($templates[1] ?? $templates[0]);

		$this->note('theme_menu() kept its own markup, including the class it used for the current page.');

		return "<?php foreach (\$menu->items() as \$item): ?>\n"
			. "<?php if (\$item->active): ?>{$active}<?php else: ?>{$normal}<?php endif; ?>\n"
			. '<?php endforeach; ?>';
	}

	/**
	 * The tag-and-class form, rendered inline.
	 *
	 * Inline rather than through the shared partial because the whole point of
	 * keeping a converted theme is that it looks like it did — and the wrapper
	 * tag, the item tag and the class the stylesheet targets are exactly what
	 * makes that true.
	 */
	private function menuFromTags(string $wrapper, string $item, string $activeClass, bool $withSubmenu): string
	{
		$class = $activeClass === '' ? '' : ' class="' . htmlspecialchars($activeClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';

		$this->note(sprintf(
			'theme_menu() kept its <%s>/<%s> markup%s.',
			$wrapper,
			$item,
			$activeClass === '' ? '' : ' and the "' . $activeClass . '" class for the current page',
		));

		$sub = '';
		if ($withSubmenu) {
			$this->note('Sub-pages are shown for the open branch, as the fourth argument asked.');
			$sub = "\n<?php if (\$item->hasChildren() && \$item->open): ?>\n"
				. "<{$wrapper}>\n"
				. "<?php foreach (\$item->children as \$child): ?>\n"
				. "<{$item}><a href=\"<?= e(\$urls->to(\$child->path())) ?>\"><?= e(\$child->title()) ?></a></{$item}>\n"
				. "<?php endforeach; ?>\n"
				. "</{$wrapper}>\n"
				. '<?php endif; ?>';
		}

		return "<{$wrapper}>\n"
			. "<?php foreach (\$menu->items() as \$item): ?>\n"
			. "<{$item}<?= \$item->active ? '{$class}' : '' ?>>"
			. "<a href=\"<?= e(\$urls->to(\$item->path())) ?>\"><?= e(\$item->title()) ?></a>{$sub}</{$item}>\n"
			. "<?php endforeach; ?>\n"
			. "</{$wrapper}>";
	}

	/** One menu entry template, with the holes filled by escaped values. */
	private function menuTemplate(string $template): string
	{
		return str_replace(
			['#file', '#title'],
			['<?= e($urls->to($item->path())) ?>', '<?= e($item->title()) ?>'],
			$template,
		);
	}

	/**
	 * Anything still holding PHP after the translation.
	 *
	 * Commented out rather than left to run: a v5 template is rendered with the
	 * translator and the URL builder in scope and nothing else, so a leftover
	 * 4.x call would be a fatal error on every page. A comment is a theme that
	 * loads with a gap in it, which somebody can see and fix.
	 */
	private function flagLeftovers(string $html): string
	{
		return preg_replace_callback(
			'/<\?php(.*?)\?>/s',
			function (array $m): string {
				$code = trim($m[1]);

				// A block that is nothing but calls this converter understands is
				// not a leftover; it is the next step's work.
				if (preg_match('/^(?:\s*(?:echo\s+)?theme_[a-z]+\s*\(.*?\)\s*;?)+$/s', $code) === 1) {
					return $m[0];
				}

				// The handful of bare variables and constants, likewise.
				if (preg_match('/^echo\s+"?\$?(?:site_title|site_name|site_theme|SITE_URL|LANG)"?\s*;?$/i', $code) === 1) {
					return $m[0];
				}
				$this->attention('Left for a person to look at: ' . mb_substr(preg_replace('/\s+/', ' ', $code) ?? $code, 0, 90));

				return '<!-- Pluck 4 code, needs a person: ' . htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' -->';
			},
			$html,
		) ?? $html;
	}

	private function header(string $title): string
	{
		return "<?php\n"
			. "/**\n"
			. " * {$title}, converted from a Pluck 4 theme.\n"
			. " *\n"
			. " * The markup is the original. What changed is the holes: where Pluck 4 called\n"
			. " * a theme_* function, this now prints an escaped value or loops over the menu.\n"
			. " *\n"
			. " * Worth reading before you put it on a live site. Anything the converter could\n"
			. " * not translate is an HTML comment saying so, and module spaces are gone by\n"
			. " * design — a module lives at its own address in Pluck 5, and a page shows one\n"
			. " * with [module:name] in its text.\n"
			. " *\n"
			. " * @var \\Pluck\\Site\\Urls \$urls\n"
			. " * @var \\Pluck\\Site\\Menu \$menu\n"
			. " * @var \\Pluck\\View\\Raw \$content\n"
			. " */\n"
			. "?>\n";
	}

	private function pageTemplate(): string
	{
		return "<?php\n"
			. "/**\n"
			. " * One page.\n"
			. " *\n"
			. " * A Pluck 4 theme had no separate page template — the content hole was inside\n"
			. " * theme.php, which is now layout.php. This is the smallest thing that keeps\n"
			. " * that arrangement working; move markup here if you want pages to differ from\n"
			. " * whatever else the layout wraps.\n"
			. " */\n"
			. "?>\n"
			. "<?= \$content ?>\n";
	}

	// ---- files ----------------------------------------------------------

	private function copyAssets(string $source, string $target): int
	{
		$copied = 0;

		foreach ($this->assetFiles($source) as $relative) {
			$from = $source . '/' . $relative;
			$to = $target . '/' . $relative;

			Path::ensureDir(dirname($to));

			if (@copy($from, $to)) {
				$copied++;
			}
		}

		if ($copied === 0) {
			$this->attention('No stylesheet or images were found, so the converted theme has no styling of its own.');
		}

		return $copied;
	}

	/**
	 * Stylesheets and images, and nothing executable.
	 *
	 * A theme archive is something somebody downloaded. Copying only the file
	 * types a theme needs means a converted theme cannot carry a PHP file into a
	 * directory the web server serves.
	 *
	 * @return list<string>
	 */
	private function assetFiles(string $dir, string $prefix = '', int $depth = 0): array
	{
		if ($depth > 6) {
			return [];
		}

		$allowed = ['css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'woff', 'woff2', 'ttf'];
		$found = [];

		foreach (scandir($dir . ($prefix === '' ? '' : '/' . $prefix)) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$relative = $prefix === '' ? $entry : $prefix . '/' . $entry;
			$full = $dir . '/' . $relative;

			if (is_link($full)) {
				continue;
			}

			if (is_dir($full)) {
				foreach ($this->assetFiles($dir, $relative, $depth + 1) as $nested) {
					$found[] = $nested;
				}
				continue;
			}

			if (in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $allowed, true)) {
				$found[] = $relative;
			}
		}

		return $found;
	}

	/**
	 * The theme's name, from info.php where there is one.
	 *
	 * @return array{0:string,1:string}
	 */
	private function identify(string $source, string $fallback): array
	{
		$name = preg_replace('/[^a-z0-9_-]/', '', strtolower($fallback)) ?: 'converted';
		$title = ucfirst($fallback);

		$info = $this->find($source, ['info.php']);
		if ($info !== null) {
			$contents = (string) file_get_contents($info);

			if (preg_match('/\$themename\s*=\s*[\'"]([^\'"]+)/', $contents, $m) === 1) {
				$title = trim($m[1]);
			}
			if (preg_match('/\$themedir\s*=\s*[\'"]([^\'"]+)/', $contents, $m) === 1) {
				$name = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($m[1]))) ?: $name;
			}
		}

		return [$name, $title];
	}

	/** @param list<string> $names */
	private function find(string $dir, array $names): ?string
	{
		foreach ($names as $name) {
			if (is_file($dir . '/' . $name)) {
				return $dir . '/' . $name;
			}
		}

		return null;
	}

	// ---- small helpers --------------------------------------------------

	/**
	 * Replace `<?php name(args); ?>` and `<?php echo name(args); ?>`.
	 *
	 * @param callable(string):string $with receives the raw argument text
	 */
	private function replaceCall(string $html, string $name, callable $with): string
	{
		$pattern = '/<\?php\s*(?:echo\s+)?' . preg_quote($name, '/') . '\s*\((.*?)\)\s*;?\s*\?>/s';

		return preg_replace_callback($pattern, static fn (array $m): string => $with($m[1]), $html) ?? $html;
	}

	/**
	 * The quoted strings in an argument list.
	 *
	 * @return list<string>
	 */
	private function stringArguments(string $args): array
	{
		preg_match_all('/([\'"])(.*?)(?<!\\\\)\1/s', $args, $matches, PREG_SET_ORDER);

		$strings = [];
		foreach ($matches as $match) {
			$strings[] = $match[2];
		}

		return $strings;
	}

	private function note(string $text): void
	{
		if (!in_array($text, $this->notes, true)) {
			$this->notes[] = $text;
		}
	}

	private function attention(string $text): void
	{
		if (!in_array($text, $this->needsAttention, true)) {
			$this->needsAttention[] = $text;
		}
	}
}
