<?php
declare(strict_types=1);

namespace Pluck\Site;

use Pluck\Storage\StorageDriver;

/**
 * Where an address used to be.
 *
 * The migration knows perfectly well that `/behandelmethoden/Acupunctuur` became
 * `/behandelmethoden/acupunctuur` — it prints the list. Until now that was all it
 * did, and everything linking to the old address got a 404: search results,
 * newsletters, whatever a customer had bookmarked. Recording it and then not
 * honouring it was the gap.
 *
 * Consulted only when nothing else matched, so a page that exists is never
 * slowed down by this and a rule can never shadow real content. That order also
 * means an old address becomes usable again the moment somebody creates a page
 * there, without anyone having to remember to delete a rule.
 *
 * 4.x addresses come in two shapes and both are handled: the path-style
 * `/pagina/naam` that the SEO module produced, and the query-string
 * `?file=blog&blog=naam` that plain 4.x used.
 */
final class Redirects
{
	public const SETTING = 'legacy_redirects';

	/** @var array<string,string>|null */
	private ?array $map = null;

	public function __construct(private readonly StorageDriver $storage)
	{
	}

	/**
	 * The address $path should be sent to, or null.
	 *
	 * @param array<string,string> $query the request's query string, for the
	 *        `?file=blog&blog=name` form 4.x used before pretty URLs
	 */
	public function to(string $path, array $query = []): ?string
	{
		$map = $this->map();
		if ($map === []) {
			return null;
		}

		foreach ($this->candidates($path, $query) as $candidate) {
			$candidate = mb_strtolower($candidate);

			if (!isset($map[$candidate])) {
				continue;
			}

			// The loop check belongs here rather than in the map: a request for the
			// address a rule points *at* must not be redirected to itself, but the
			// rule is still needed for every other spelling of it.
			if ($map[$candidate] === trim($path, '/')) {
				return null;
			}

			return $map[$candidate];
		}

		return null;
	}

	/**
	 * The 4.x query-string form of a module address.
	 *
	 * `?file=blog&blog=hooikoorts` is not in the redirect map and should not be:
	 * the map records addresses that were *renamed*, and that post kept its name.
	 * What changed is the shape of the address, which is true of every post at
	 * once — writing a rule per post would be a hundred lines saying the same
	 * thing.
	 *
	 * So this is worked out rather than looked up, and then passed through the
	 * map in case the name changed as well.
	 *
	 * @param array<string,string> $query
	 */
	public function fromQuery(array $query): ?string
	{
		$file = (string) ($query['file'] ?? '');

		if (!in_array($file, ['blog', 'albums'], true)) {
			return null;
		}

		$name = trim((string) ($query[$file] ?? ''), '/');
		if ($name === '') {
			// ?file=blog with nothing after it was the index in 4.x too.
			return $file;
		}

		$address = $file . '/' . $name;

		// Renamed as well as reshaped, in which case the map has the answer.
		return $this->map()[mb_strtolower($address)] ?? $address;
	}

	/**
	 * The addresses a request might be known by.
	 *
	 * Case is folded because that is what most of these rules are *about*: Pluck 5
	 * lower-cases a path, so /Acupunctuur and /acupunctuur are the same page here
	 * and were two different ones in 4.x.
	 *
	 * @param array<string,string> $query
	 * @return list<string>
	 */
	private function candidates(string $path, array $query): array
	{
		$path = trim($path, '/');

		$found = [$path];

		// ?file=blog&blog=name, and the album equivalent. Rebuilt into the shape
		// the map stores rather than the map storing every possible spelling.
		$file = $query['file'] ?? '';
		foreach (['blog', 'albums'] as $module) {
			if ($file === $module && ($query[$module] ?? '') !== '') {
				$found[] = $module . '/' . trim((string) $query[$module], '/');
			}
		}

		return array_values(array_unique(array_filter($found, static fn (string $c): bool => $c !== '')));
	}

	/** @return array<string,string> */
	private function map(): array
	{
		if ($this->map !== null) {
			return $this->map;
		}

		$stored = $this->storage->getSetting(self::SETTING, []);
		$clean = [];

		foreach (is_array($stored) ? $stored : [] as $from => $to) {
			if (!is_string($from) || !is_string($to)) {
				continue;
			}

			/*
			 * Checked before trimming, not after.
			 *
			 * trim($to, '/') turns //evil.example/phish into evil.example/phish, so
			 * testing the trimmed value for a leading // finds nothing and the rule
			 * is stored — a browser reads Location: //evil.example as another site.
			 * An open redirect, from one line in the wrong order.
			 */
			if (!is_string($to) || str_contains($to, '://') || str_starts_with($to, '//') || str_contains($to, "\0")) {
				continue;
			}

			// Keys are folded, because case is what most of these rules are about:
			// Pluck 5 lower-cases an address, so the old spelling in somebody's
			// bookmark is the one that has to match.
			$from_original = $from;
			$from = mb_strtolower(trim($from, '/'));
			$to = trim($to, '/');

			// Only an exact self-reference is a loop. A rule whose key and target
			// differ *only* in case is the commonest kind here — it is what
			// Pluck 5 lower-casing an address produces — and folding the key makes
			// it look like a loop when it is the whole point.
			if ($from === '' || trim((string) $from_original, '/') === $to) {
				continue;
			}

			$clean[$from] = $to;
		}

		return $this->map = $clean;
	}

	/**
	 * Record where things moved to.
	 *
	 * @param array<string,string> $map old address => new address
	 */
	public static function remember(StorageDriver $storage, array $map): void
	{
		$existing = $storage->getSetting(self::SETTING, []);
		$existing = is_array($existing) ? $existing : [];

		// Merged rather than replaced: a second migration into the same install is
		// unusual, and losing the first one's rules silently would be worse than
		// carrying a few extra.
		$storage->setSetting(self::SETTING, array_merge($existing, $map));
	}
}
