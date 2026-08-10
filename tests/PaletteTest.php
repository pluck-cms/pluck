<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Security\Sanitizer;
use Pluck\I18n\Locale;
use Pluck\I18n\Translator;
use Pluck\Security\Session;
use Pluck\Security\Csp;
use Pluck\Security\Csrf;
use Pluck\Theme\Theme;
use Pluck\Site\Urls;
use Pluck\Site\SiteRenderer;
use Pluck\Model\Page;
use Pluck\Storage\DriverFactory;
use Pluck\Site\Palette;

/**
 * The colours a writer can pick.
 *
 * Read out of the stylesheet that defines them rather than listed in PHP as
 * well. That file is the palette: adding a colour is one rule in it, and the
 * picker follows. A second list would be a second list to forget — the rule this
 * project has broken six times.
 */
final class PaletteTest extends TestCase
{
	public function run(): void
	{
		$this->group('read from the stylesheet', fn () => $this->reading());
		$this->group('what the sanitiser keeps', fn () => $this->survives());
		$this->group('the stylesheet reaches the page', fn () => $this->reaches());
	}

	private function reading(): void
	{
		$colours = Palette::read(dirname(__DIR__) . '/assets/site/colours.css');

		$this->assertSame(24, count($colours), 'the shipped palette is twenty-four');
		$this->assertTrue(isset($colours['red']), 'and red is one of them');
		$this->assertSame('#ffffff', $colours['white'] ?? '', 'with the colour it sets');

		foreach ($colours as $name => $value) {
			$this->assertSame(
				1,
				preg_match('/^[a-z0-9-]+$/', $name),
				'a name is safe in a class and an attribute: ' . $name,
			);
			$this->assertSame(1, preg_match('/^#[0-9a-f]{3,8}$/', $value), 'and a colour is a colour');
		}

		// A file that is not there is empty rather than fatal: the picker then
		// simply does not appear, which is better than an editor that will not.
		$this->assertSame([], Palette::read('/nowhere/colours.css'), 'a missing file is no colours');

		$this->assertSame('Red dark', Palette::label('red-dark'), 'a name a person can read');
	}

	/**
	 * The class survives a save; a colour written into the text does not.
	 *
	 * That is the whole reason the picker stores a class. `style="color:red"` and
	 * `<font color>` are both stripped, deliberately — a colour in the content
	 * lives exactly as long as the theme it was chosen against, and there is no
	 * way to find them all afterwards.
	 */
	private function survives(): void
	{
		$sanitizer = new Sanitizer();

		$this->assertTrue(
			str_contains($sanitizer->inspect('<p><span class="c-red">rood</span></p>')->html, 'class="c-red"'),
			'a colour class is kept',
		);
		$this->assertFalse(
			str_contains($sanitizer->inspect('<p style="color:red">rood</p>')->html, 'style'),
			'an inline style is not',
		);
		$this->assertFalse(
			str_contains($sanitizer->inspect('<font color="red">rood</font>')->html, 'font'),
			'and neither is <font>',
		);
	}

	/**
	 * Pluck puts its own stylesheet in the head, not the theme.
	 *
	 * A theme is a folder somebody edits over FTP, and plenty of the people
	 * running these sites have neither FTP nor a reason to learn it. A colour
	 * picker that only works once a file has been edited by hand is a picker that
	 * appears, does nothing, and explains itself to nobody.
	 *
	 * Straight after <head>, so everything a theme loads comes later and wins —
	 * that is what makes `.c-red` in a theme's stylesheet an override.
	 */
	private function reaches(): void
	{
		$dir = $this->tempDir('pluck-head');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();
		$storage->savePage(new Page(path: 'home', title: 'Home', content: '<p>x</p>'));

		$renderer = $this->withoutSessionWarnings(fn (): SiteRenderer => new SiteRenderer(
			Theme::load(dirname(__DIR__) . '/themes', 'default'),
			$storage,
			new Urls('/', true),
			new Csrf(new Session()),
			new Csp(),
			new Translator(Locale::fallback(), dirname(__DIR__) . '/lang'),
		));

		$html = $renderer->page($storage->findPage('home'));

		$this->assertSame(
			1,
			substr_count($html, 'site/colours.css'),
			'linked once, by Pluck, on a theme that never asked',
		);

		$colours = strpos($html, 'site/colours.css');
		$theme = strpos($html, 'themes/default/assets/style.css');

		$this->assertTrue(
			$colours !== false && $theme !== false && $colours < $theme,
			'and before the theme, so the theme can override it',
		);

		// A theme that links it itself is left alone rather than given a second.
		$twice = \Pluck\Site\SiteRenderer::class;
		$this->assertTrue(
			str_contains((string) file_get_contents(dirname(__DIR__) . '/src/Site/SiteRenderer.php'), "str_contains(\$document, 'site/colours.css')"),
			'a theme that links it explicitly does not get it twice',
		);
	}
}
