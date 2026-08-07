<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Support\Slug;

final class SlugTest extends TestCase
{
	public function run(): void
	{
		$this->group('letters other alphabets fold to', fn () => $this->folding());
		$this->group('the same address on every server', fn () => $this->deterministic());
		$this->group('one implementation of the rule', fn () => $this->onlyOne());
		$this->assertSame('about-us', Slug::make('About Us'), 'spaces become dashes, case is lowered');
		$this->assertSame('a-b', Slug::make('a  ---  b'), 'runs of separators collapse');
		$this->assertSame('cafe', Slug::make('Café'), 'accents are folded, with or without intl');
		$this->assertSame('page', Slug::make('!!!'), 'punctuation-only titles fall back');
		$this->assertSame('its-here', Slug::make("It's here"), 'apostrophes are dropped, not turned into dashes');
		$this->assertTrue(Slug::make('Привет мир') !== 'page', 'a cyrillic title yields a real slug, not a fallback');
		$this->assertTrue(mb_strlen(Slug::make(str_repeat('long ', 100))) <= 96, 'slugs are capped');

		$this->assertSame('about/team', Slug::path('About/Team'), 'each path segment is slugified');
		$this->assertSame('about/team', Slug::path('/about//team/'), 'empty segments are dropped');
		$this->assertSame('a/b/c/d', Slug::path('a/b/c/d/e/f'), 'depth is capped at four');
		$this->assertSame('', Slug::path('///'), 'an empty path stays empty');
		$this->assertSame('about', Slug::path('../about'), 'traversal segments cannot survive slugging');

		$taken = ['about' => true, 'about-2' => true];
		$this->assertSame('about-3', Slug::unique('about', static fn (string $p): bool => isset($taken[$p])), 'unique bumps past taken slugs');
		$this->assertSame('free', Slug::unique('free', static fn (string $p): bool => isset($taken[$p])), 'a free slug is returned as is');
	}

	/**
	 * The fold map runs whether or not the transliterator did.
	 *
	 * Latin-ASCII depends on the ICU the server was built against, and older ones
	 * leave characters alone that newer ones fold. Polish ł is the one that gets
	 * reported, because a site whose pages are named in Polish meets it on the
	 * first page — and an address is not something to have twice.
	 */
	private function folding(): void
	{
		foreach ([
			'Łódź' => 'lodz',
			'Wpłaty' => 'wplaty',
			'Zażółć gęślą jaźń' => 'zazolc-gesla-jazn',
			'Straße' => 'strasse',
			'Ærø' => 'aero',
			'Œuvre' => 'oeuvre',
			'Ĳsselmeer' => 'ijsselmeer',
		] as $title => $want) {
			$this->assertSame($want, Slug::make($title), $title . ' becomes an address');
		}

		// Nothing left that a URL cannot carry.
		$this->assertSame(
			1,
			preg_match('/^[a-z0-9-]+$/', Slug::make('Łódź — Zażółć!')),
			'and an address is only characters an address may hold',
		);
	}

	/**
	 * A Latin title gives the same address with ICU and without it.
	 *
	 * A slug is an address. If two servers disagree about one, then moving a site
	 * changes its URLs and every link anybody made to it breaks — silently, during
	 * a migration, when nobody is looking at slugs. That is worse than a character
	 * coming out wrong.
	 *
	 * So the table runs first and ICU is asked only about what is left. Asserted
	 * on the order, because this machine has ICU and cannot be made not to have it
	 * inside one process.
	 */
	private function deterministic(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/src/Support/Slug.php');

		$fold = strpos($source, 'strtr(mb_strtolower');
		$icu = strpos($source, 'transliterator_transliterate(');

		$this->assertTrue($fold !== false && $icu !== false, 'both stages are there');
		$this->assertTrue(
			$fold < $icu,
			'the table folds before the transliterator is asked, so a Latin title does not depend on ICU',
		);

		// And ICU is only asked when something is left for it.
		$this->assertTrue(
			str_contains($source, "preg_match('/[^\\x00-\\x7F]/'"),
			'and only when the string still has something outside ASCII in it',
		);
	}

	/**
	 * The browser does not fold titles itself.
	 *
	 * It used to, with NFD: split a letter from its accent, drop the accent, keep
	 * what is left. That agrees with Slug::make() across most of Europe and fails
	 * completely on Polish ł, which has no accent to split off — it is one
	 * indivisible letter, and the next step threw it away. A page called Łódź got
	 * the address "odz".
	 *
	 * Worse than the wrong answer was where it happened: the field was filled in
	 * before the form was submitted, so the server used the browser's answer and
	 * never saw the title. Slug::make() was correct the whole time and never
	 * reached. Two implementations of one rule, and the one without the table won.
	 */
	private function onlyOne(): void
	{
		$js = (string) file_get_contents(dirname(__DIR__) . '/assets/admin/pluck.js');

		$this->assertFalse(
			str_contains($js, "normalize('NFD')"),
			'the editor does not fold a title in the browser',
		);
		$this->assertTrue(
			str_contains($js, 'page.slug'),
			'it asks the server, which has the table',
		);

		// And the server answers with what Slug::make would give.
		$this->assertSame('lodz', Slug::make('Łódź'), 'Łódź is lodz, wherever it is asked');
	}
}
