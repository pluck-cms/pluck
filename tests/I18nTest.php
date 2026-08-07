<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\I18n\Locale;
use Pluck\I18n\Plural;
use Pluck\I18n\Translator;

final class I18nTest extends TestCase
{
	public function run(): void
	{
		$this->locales();
		$this->plurals();
		$this->lookup();
		$this->escaping();
	}

	private function locales(): void
	{
		$this->group('locale codes', function (): void {
			$this->assertSame('nl', Locale::tryFrom('nl')?->code, 'a bare language code');
			$this->assertSame('nl-BE', Locale::tryFrom('nl_BE')?->code, 'underscore form is normalised');
			$this->assertSame('pt-BR', Locale::tryFrom('pt-br')?->code, 'region is upper-cased');

			// The migrator carries 4.x's langpref straight over, and that was a filename.
			$this->assertSame('nl', Locale::tryFrom('nl.php')?->code, '4.x stored the language as a filename');

			// A locale code becomes a path, so these are the ones that matter.
			$this->assertSame(null, Locale::tryFrom('../../etc/passwd'), 'traversal is not a locale');
			$this->assertSame(null, Locale::tryFrom('nl/../en'), 'nor is a path');
			$this->assertSame(null, Locale::tryFrom('en; rm -rf /'), 'nor a command');
			$this->assertSame(null, Locale::tryFrom(''), 'nor an empty string');
			$this->assertSame(null, Locale::tryFrom('englishlanguage'), 'nor an over-long tag');
			$this->assertSame(null, Locale::tryFrom(null), 'nor null');

			$this->assertSame(['nl-BE', 'nl', 'en'], Locale::tryFrom('nl-BE')?->chain(), 'the chain goes specific to general');
			$this->assertSame(['en'], Locale::tryFrom('en')?->chain(), 'english does not repeat itself');

			$available = ['en', 'nl', 'de'];
			$this->assertSame('nl', Locale::negotiate('nl-NL,nl;q=0.9,en;q=0.8', $available)?->code, 'the browser preference is honoured');
			$this->assertSame('de', Locale::negotiate('de-AT', $available)?->code, 'a region falls back to its language');
			$this->assertSame(null, Locale::negotiate('fr-FR,fr;q=0.9', $available)?->code, 'an unavailable language yields nothing');
			$this->assertSame(null, Locale::negotiate('', $available), 'an empty header yields nothing');
		});
	}

	private function plurals(): void
	{
		$this->group('plural categories', function (): void {
			$this->assertSame('one', Plural::category('en', 1), 'english one');
			$this->assertSame('other', Plural::category('en', 0), 'english zero is plural');
			$this->assertSame('other', Plural::category('nl', 2), 'dutch two');

			// French counts zero as singular; a two-form rule gets this wrong.
			$this->assertSame('one', Plural::category('fr', 0), 'french zero is singular');

			// Polish and Russian need three forms. Getting this wrong is the usual
			// reason a translated interface reads as broken.
			$this->assertSame('one', Plural::category('pl', 1), 'polish 1');
			$this->assertSame('few', Plural::category('pl', 3), 'polish 3');
			$this->assertSame('many', Plural::category('pl', 5), 'polish 5');
			$this->assertSame('many', Plural::category('pl', 12), 'polish 12 is not few');
			$this->assertSame('few', Plural::category('pl', 22), 'polish 22 is few');

			$this->assertSame('one', Plural::category('ru', 21), 'russian 21');
			$this->assertSame('many', Plural::category('ru', 11), 'russian 11');

			$this->assertSame('other', Plural::category('ja', 1), 'japanese has one form');
			$this->assertSame('other', Plural::category('xx', 7), 'an unknown language falls back to two forms');
		});
	}

	private function lookup(): void
	{
		$dir = $this->tempDir('lang');
		file_put_contents($dir . '/en.json', json_encode([
			'greeting' => 'Hello',
			'welcome' => 'Welcome, {name}',
			'items' => ['one' => '{count} item', 'other' => '{count} items'],
			'nested' => ['deep' => 'Nested value'],
			'only_english' => 'Only in English',
		]));
		file_put_contents($dir . '/nl.json', json_encode([
			'greeting' => 'Hallo',
			'welcome' => 'Welkom, {name}',
			'items' => ['one' => '{count} item', 'other' => '{count} items'],
		]));

		$this->group('lookup', function () use ($dir): void {
			$nl = new Translator(Locale::tryFrom('nl'), $dir);

			$this->assertSame('Hallo', $nl->get('greeting'), 'the translation is used');
			$this->assertSame('Welkom, Bas', $nl->get('welcome', ['name' => 'Bas']), 'placeholders are filled');

			// Per key, not per file: a half-translated locale is normal and useful.
			$this->assertSame('Only in English', $nl->get('only_english'), 'a missing key falls back to english');
			$this->assertSame('Nested value', $nl->get('nested.deep'), 'a nested file flattens to dotted keys');

			$this->assertSame('1 item', $nl->get('items', [], 1), 'singular');
			$this->assertSame('4 items', $nl->get('items', [], 4), 'plural');
			$this->assertSame('0 items', $nl->get('items', [], 0), 'dutch zero is plural');

			// A missing key shows itself rather than a blank: an unfinished screen
			// should look unfinished, not broken.
			$this->assertSame('no.such.key', $nl->get('no.such.key'), 'a missing key renders as the key');
			$this->assertTrue(in_array('no.such.key', $nl->missing(), true), 'and is reported as missing');
			$this->assertFalse($nl->has('no.such.key'), 'has() agrees');

			$available = $nl->available();
			sort($available);
			$this->assertSame(['en', 'nl'], $available, 'both catalogues are found');
		});

		$this->group('a locale with no file at all', function () use ($dir): void {
			$de = new Translator(Locale::tryFrom('de'), $dir);
			$this->assertSame('Hello', $de->get('greeting'), 'everything falls back to english');
		});
	}

	private function escaping(): void
	{
		$dir = $this->tempDir('lang-hostile');

		// A theme may ship its own lang/*.json, and a theme arrives as an uploaded
		// archive — so a translation is attacker-influenceable in the same way page
		// content is, and has to be treated that way.
		file_put_contents($dir . '/en.json', json_encode([
			'nasty' => '<script>alert(1)</script>',
			'attr' => 'x" onmouseover="alert(1)',
			'hello' => 'Hello, {name}',
		]));

		$this->group('a hostile translation', function () use ($dir): void {
			$translator = new Translator(Locale::fallback(), $dir);
			$view = new \Pluck\View\View(
				dirname(__DIR__) . '/views',
				new \Pluck\Security\Csrf(new \Pluck\Security\Session()),
				new \Pluck\Security\Csp(),
				$translator,
			);

			$this->assertSame(
				'&lt;script&gt;alert(1)&lt;/script&gt;',
				$view->t('nasty'),
				'a script tag in a translation is escaped on output',
			);
			$this->assertSame(
				'x&quot; onmouseover=&quot;alert(1)',
				$view->t('attr'),
				'an attribute breakout is escaped too',
			);

			// Placeholder values are escaped separately, so a value cannot smuggle
			// markup in through a clean translation either.
			$this->assertSame(
				'Hello, &lt;img src=x onerror=alert(1)&gt;',
				$view->t('hello', ['name' => '<img src=x onerror=alert(1)>']),
				'a placeholder value is escaped before substitution',
			);

			// And there is deliberately no raw variant to reach for.
			$this->assertFalse(
				method_exists($view, 'tRaw') || method_exists($translator, 'getRaw'),
				'no raw translation output exists anywhere',
			);
		});
	}
}
