<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Support\Slug;

final class SlugTest extends TestCase
{
	public function run(): void
	{
		$this->group('letters other alphabets fold to', fn () => $this->folding());
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
}
