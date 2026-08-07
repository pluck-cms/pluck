<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Site\Redirects;
use Pluck\Storage\DriverFactory;

/**
 * Where an address used to be.
 *
 * The migration always knew this and only printed it, so everything linking to
 * an old address got a 404 while the report sat there with the answer. These are
 * mostly about the two shapes a 4.x address came in, and about the rules that
 * must never be stored.
 */
final class RedirectTest extends TestCase
{
	public function run(): void
	{
		$this->group('an address that moved', fn () => $this->moved());
		$this->group('the query-string form', fn () => $this->queryForm());
		$this->group('rules that are refused', fn () => $this->refused());
	}

	private function moved(): void
	{
		$redirects = $this->with([
			'behandelmethoden/Acupunctuur' => 'behandelmethoden/acupunctuur',
			'albums/Open dag 2012' => 'albums/open-dag-2012',
		]);

		$this->assertSame(
			'behandelmethoden/acupunctuur',
			$redirects->to('behandelmethoden/Acupunctuur'),
			'a renamed page points at its new address',
		);

		$this->assertSame(
			'albums/open-dag-2012',
			$redirects->to('/albums/Open dag 2012/'),
			'with the slashes around it ignored',
		);

		// Most of these rules exist *because* Pluck 5 lower-cases an address, so
		// matching has to as well or the rule never fires for the spelling people
		// actually have in their bookmarks.
		$this->assertSame(
			'behandelmethoden/acupunctuur',
			$redirects->to('behandelmethoden/ACUPUNCTUUR'),
			'and case is folded, since case is what most of these rules are about',
		);

		$this->assertSame(null, $redirects->to('behandelmethoden/acupunctuur'), 'the new address is not itself redirected');
		$this->assertSame(null, $redirects->to('something-else'), 'and an address nobody moved is left alone');
	}

	private function queryForm(): void
	{
		$redirects = $this->with(['blog/oude-naam' => 'blog/nieuwe-naam']);

		// Worked out rather than looked up. The map holds addresses that were
		// *renamed*; a post that kept its name has no rule and still needs
		// redirecting, because the shape of the address changed for all of them at
		// once.
		$this->assertSame(
			'blog/hooikoorts',
			$redirects->fromQuery(['file' => 'blog', 'blog' => 'hooikoorts']),
			'a post that kept its name still moves from ?file=blog to /blog/name',
		);

		$this->assertSame(
			'blog/nieuwe-naam',
			$redirects->fromQuery(['file' => 'blog', 'blog' => 'oude-naam']),
			'and one that was renamed as well goes to the new name',
		);

		$this->assertSame('blog', $redirects->fromQuery(['file' => 'blog']), '?file=blog alone is the index');
		$this->assertSame(
			'albums/vakantie',
			$redirects->fromQuery(['file' => 'albums', 'albums' => 'vakantie']),
			'albums work the same way',
		);

		// Only the two modules 4.x served this way. Anything else in ?file= is not
		// an address this site ever had, and turning it into one would be a
		// redirect a stranger controls.
		$this->assertSame(null, $redirects->fromQuery(['file' => 'admin']), 'anything else is not a redirect');
		$this->assertSame(null, $redirects->fromQuery([]), 'and neither is a request without one');
	}

	private function refused(): void
	{
		$redirects = $this->with([
			'somewhere' => 'https://evil.example/phish',
			'elsewhere' => '//evil.example/phish',
			'loop' => 'loop',
			'' => 'nowhere',
		]);

		// An open redirect turns a trusted domain into a phishing link, and a
		// migration report is not a place anybody reads carefully enough to catch
		// one. Refused on the way in, so a hand-edited setting cannot introduce it.
		$this->assertSame(null, $redirects->to('somewhere'), 'a rule pointing off this site is not honoured');
		$this->assertSame(null, $redirects->to('elsewhere'), 'nor a protocol-relative one');
		$this->assertSame(null, $redirects->to('loop'), 'nor one pointing at itself');
		$this->assertSame(null, $redirects->to(''), 'and an empty address matches nothing');
	}

	/** @param array<string,string> $map */
	private function with(array $map): Redirects
	{
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $this->tempDir('pluck-redirects'));
		$storage->install();

		Redirects::remember($storage, $map);

		return new Redirects($storage);
	}
}
