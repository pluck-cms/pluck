<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Theme\LegacyThemeConverter;
use Pluck\Theme\Theme;

/**
 * Converting a Pluck 4 theme.
 *
 * This looked impossible and turned out to be a translation problem. A 4.x theme
 * is PHP that runs inside the page — the shape v5 exists to refuse — but across
 * the thirty themes in pluck-cms/themes the PHP is eight functions and a handful
 * of variables. A small language can be translated; arbitrary code cannot.
 *
 * The assertions that matter are about what must *not* survive: no PHP left
 * running in a v5 template, nothing unrecognised dropped without saying so.
 */
final class LegacyThemeTest extends TestCase
{
	public function run(): void
	{
		$this->group('a plain theme', fn () => $this->plain());
		$this->group('the menu', fn () => $this->menu());
		$this->group('what has no equivalent', fn () => $this->noEquivalent());
		$this->group('what must not survive', fn () => $this->mustNotSurvive());
	}

	private function plain(): void
	{
		$source = $this->legacyTheme([
			'info.php' => "<?php\n\$themedir = \"seaside\";\n\$themename = \"Seaside\";\n",
			'theme.php' => '<html><head><?php theme_meta(); ?></head><body>'
				. '<h1><?php theme_sitetitle(); ?></h1>'
				. '<h2><?php theme_pagetitle(); ?></h2>'
				. '<div><?php theme_content(); ?></div>'
				. '<a href="login.php">admin</a></body></html>',
			'style.css' => 'body { color: teal }',
			'images/bg.png' => 'not really a png',
		]);

		$target = $this->tempDir('pluck-converted') . '/seaside';
		$result = (new LegacyThemeConverter())->convert($source, $target);

		$this->assertSame('seaside', $result['name'], 'the name comes out of info.php');
		$this->assertSame('Seaside', $result['title'], 'and so does the title');
		$this->assertSame([], $result['attention'], 'a plain theme needs nobody');

		// It has to load as a v5 theme, which is the only real test of the output.
		$theme = Theme::load(dirname($target), 'seaside');
		$this->assertSame('Seaside', $theme->title(), 'the result loads as a Pluck 5 theme');
		$this->assertTrue($theme->has('layout'), 'with a layout');
		$this->assertTrue($theme->has('page'), 'and a page template');

		$layout = (string) file_get_contents($target . '/templates/layout.php');

		$this->assertTrue(str_contains($layout, 'e($siteTitle)'), 'the site title hole is filled');
		$this->assertTrue(str_contains($layout, 'e($title)'), 'and the page title');
		$this->assertTrue(str_contains($layout, '$content'), 'and the content');
		$this->assertTrue(str_contains($layout, '$themeAssets'), 'the stylesheet is linked from the theme assets');

		// 4.x signed in at login.php.
		$this->assertFalse(str_contains($layout, 'login.php'), 'the old sign-in address is rewritten');
		$this->assertTrue(str_contains($layout, 'admin.php'), 'to this one');

		$this->assertTrue(is_file($target . '/assets/style.css'), 'the stylesheet comes across');
		$this->assertTrue(is_file($target . '/assets/images/bg.png'), 'and the images, in their own folders');
	}

	private function menu(): void
	{
		// The form twenty-six of the thirty published themes use.
		$source = $this->legacyTheme([
			'theme.php' => '<html><body><?php theme_menu(\'ul\', \'li\', \'current\', 0); ?>'
				. '<?php theme_content(); ?></body></html>',
			'style.css' => '',
		]);

		$target = $this->tempDir('pluck-converted') . '/tagform';
		$result = (new LegacyThemeConverter())->convert($source, $target);
		$layout = (string) file_get_contents($target . '/templates/layout.php');

		$this->assertSame([], $result['attention'], 'the tag form converts cleanly');
		$this->assertTrue(str_contains($layout, '<ul>'), 'the wrapper tag is kept');
		$this->assertTrue(str_contains($layout, 'class="current"'), 'and the class the stylesheet targets');
		$this->assertTrue(str_contains($layout, '$menu->items()'), 'over the real menu');
		$this->assertTrue(str_contains($layout, 'e($urls->to('), 'with addresses built rather than pasted');

		// The template form, where the theme supplied its own markup.
		$source = $this->legacyTheme([
			'theme.php' => '<?php theme_menu(\'<li><a href="#file">#title</a></li>\', \'<li class="here"><a href="#file">#title</a></li>\'); ?>',
			'style.css' => '',
		]);

		$target = $this->tempDir('pluck-converted') . '/templateform';
		(new LegacyThemeConverter())->convert($source, $target);
		$layout = (string) file_get_contents($target . '/templates/layout.php');

		$this->assertTrue(str_contains($layout, 'class="here"'), 'the theme\'s own active markup is kept');
		$this->assertTrue(str_contains($layout, '$item->active'), 'and chosen on the current page');
		$this->assertFalse(str_contains($layout, '#file'), 'the placeholders are gone');
	}

	private function noEquivalent(): void
	{
		$source = $this->legacyTheme([
			'theme.php' => '<?php theme_content(); ?><?php theme_module(\'footer\'); ?>',
			'style.css' => '',
		]);

		$target = $this->tempDir('pluck-converted') . '/spaces';
		$result = (new LegacyThemeConverter())->convert($source, $target);

		// Module spaces are the one thing that genuinely cannot come across: v5
		// mounts a module at an address rather than dropping it into a slot a
		// theme declares.
		$this->assertSame(1, count($result['attention']), 'a module space is reported');
		$this->assertTrue(
			str_contains($result['attention'][0], '[module:name]'),
			'and the report says what to do instead',
		);

		$layout = (string) file_get_contents($target . '/templates/layout.php');
		$this->assertTrue(str_contains($layout, '<!-- was theme_module'), 'with a comment left where it was');
	}

	private function mustNotSurvive(): void
	{
		$source = $this->legacyTheme([
			'theme.php' => '<?php theme_content(); ?>'
				. '<?php if (function_exists("something")) { echo custom_thing(); } ?>',
			'style.css' => '',
			// A theme archive is something somebody downloaded, so a converted
			// theme must not carry executable files into a served directory.
			'evil.php' => $this->webshell(),
			'images/shell.php' => $this->webshell(),
		]);

		$target = $this->tempDir('pluck-converted') . '/leftovers';
		$result = (new LegacyThemeConverter())->convert($source, $target);
		$layout = (string) file_get_contents($target . '/templates/layout.php');

		// Left running, that call would be a fatal error on every page: a v5
		// template has the translator and the URL builder in scope and nothing
		// else. Commented out, the theme loads with a visible gap.
		// The text is still there, inside the comment, and should be: that is how
		// somebody sees what was removed. What must be gone is it being *code*.
		$this->assertFalse(
			(bool) preg_match('/<\?php(?!\s*\/\*\*).*custom_thing/s', $layout),
			'unrecognised PHP does not stay executable',
		);
		$this->assertTrue(str_contains($layout, 'custom_thing'), 'but is visible in the comment, so it can be put right');
		$this->assertTrue(str_contains($layout, 'needs a person'), 'it becomes a comment saying so');
		$this->assertTrue(
			count(array_filter($result['attention'], static fn (string $a): bool => str_contains($a, 'Left for a person'))) > 0,
			'and is named in the report rather than dropped quietly',
		);

		$this->assertFalse(is_file($target . '/assets/evil.php'), 'a php file in the theme is not copied across');
		$this->assertFalse(is_file($target . '/assets/images/shell.php'), 'not even hidden among the images');
	}

	/** @param array<string,string> $files */
	private function legacyTheme(array $files): string
	{
		$dir = $this->tempDir('pluck-legacy-theme');

		foreach ($files as $relative => $contents) {
			$path = $dir . '/' . $relative;
			@mkdir(dirname($path), 0o755, true);
			file_put_contents($path, $contents);
		}

		return $dir;
	}
}
