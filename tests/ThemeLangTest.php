<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\I18n\Locale;
use Pluck\I18n\Translator;

/**
 * A theme can bring wording of its own.
 *
 * Translator always took several sources; the theme simply never got to be one.
 * A theme with words of its own therefore had two options: put them in Pluck's
 * catalogue, which is everybody's file, or write them into its templates in one
 * language — and then a Polish reader gets Dutch.
 *
 * Read after Pluck's, so a theme can also replace a word of Pluck's without
 * anybody editing lang/nl.json.
 */
final class ThemeLangTest extends TestCase
{
	public function run(): void
	{
		$this->group('a theme adds and overrides', fn () => $this->sources());
	}

	private function sources(): void
	{
		$dir = $this->tempDir('pluck-theme-lang');
		@mkdir($dir . '/core', 0o755, true);
		@mkdir($dir . '/theme', 0o755, true);

		file_put_contents($dir . '/core/nl.json', json_encode([
			'site.home' => 'Home',
			'site.skip_to_content' => 'Naar de inhoud',
		]));
		file_put_contents($dir . '/theme/nl.json', json_encode([
			'wsj.phase.before' => 'Op weg ernaartoe',
			'site.home' => 'Thuisbasis',
		]));

		$translator = new Translator(Locale::tryFrom('nl') ?? Locale::fallback(), $dir . '/core', $dir . '/theme');

		$this->assertSame(
			'Op weg ernaartoe',
			$translator->get('wsj.phase.before'),
			'a word only the theme has',
		);
		$this->assertSame(
			'Thuisbasis',
			$translator->get('site.home'),
			'and one it replaces, because later sources win',
		);
		$this->assertSame(
			'Naar de inhoud',
			$translator->get('site.skip_to_content'),
			'while the rest of Pluck is untouched',
		);

		// A theme without a lang folder is the normal case and must cost nothing.
		$plain = new Translator(Locale::tryFrom('nl') ?? Locale::fallback(), $dir . '/core', $dir . '/nothing-here');

		$this->assertSame('Home', $plain->get('site.home'), 'a missing source is skipped');
	}
}
