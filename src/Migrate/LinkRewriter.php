<?php
declare(strict_types=1);

namespace Pluck\Migrate;

/**
 * Pointing the content at where things ended up.
 *
 * The migration moved two kinds of thing and, until now, told the content about
 * neither. Uploads went from `images/` and `files/` into `media/` — often under
 * a different name, since a filename with spaces or capitals gets rebuilt — so
 * every `<img src="images/logo.png">` in a page became a broken image. And links
 * between pages were written as `?file=contact`, which Pluck 5 does not answer
 * to at all.
 *
 * Copying the files and rewriting the addresses are two halves of one job, and
 * doing only the first is the kind of migration that looks like it worked.
 *
 * Done with patterns over the markup rather than by parsing it, deliberately: a
 * page has already been through the sanitiser by this point, so what is being
 * matched is markup Pluck itself produced, and an address that does not match is
 * left exactly as it was. Nothing here is a place to be clever — a rewrite that
 * guesses wrong is worse than one that misses.
 */
final class LinkRewriter
{
	/** @var array<string,string> old address => new address */
	private array $map = [];

	/** @var list<string> addresses that were already broken before the migration */
	public array $suspicious = [];

	/**
	 * @param array<string,string> $uploads new media name => old relative path
	 * @param array<string,string> $pages old page path => new page path
	 * @param array<string,string> $modules old module address => new one
	 */
	public function __construct(array $uploads, array $pages, array $modules = [])
	{
		foreach ($uploads as $name => $original) {
			// 4.x kept uploads in two directories and referred to them by that
			// path. Both spellings are recorded, with and without a leading slash,
			// because pages in the wild have both.
			$this->map[$original] = 'media/' . $name;
			$this->map['/' . $original] = 'media/' . $name;
			// The encoded spelling too, for the exact-match path above.
			$this->map[str_replace(' ', '%20', $original)] = 'media/' . $name;
		}

		foreach ($pages as $from => $to) {
			$this->map['?file=' . $from] = $to;
		}

		foreach ($modules as $from => $to) {
			$this->map['?file=' . $from] = $to;
		}
	}

	/**
	 * Rewrite the addresses in a piece of markup.
	 *
	 * @return array{0:string,1:int} the markup, and how many addresses changed
	 */
	public function rewrite(string $html): array
	{
		if ($html === '') {
			return [$html, 0];
		}

		$changed = 0;

		$out = preg_replace_callback(
			'/\b(src|href)="([^"]*)"/i',
			function (array $m) use (&$changed): string {
				$new = $this->address($m[2]);

				if ($new === null) {
					return $m[0];
				}

				$changed++;

				return $m[1] . '="' . $new . '"';
			},
			$html,
		) ?? $html;

		return [$out, $changed];
	}

	/**
	 * Where an address should point now, or null to leave it alone.
	 *
	 * Anything absolute is left alone without looking: an address on another site
	 * is not ours to rewrite, and a mailto: or tel: is not an address at all.
	 */
	private function address(string $address): ?string
	{
		$trimmed = trim($address);

		if ($trimmed === '' || str_contains($trimmed, '://') || str_starts_with($trimmed, '//')) {
			return null;
		}

		foreach (['mailto:', 'tel:', '#', 'data:', 'javascript:'] as $prefix) {
			if (str_starts_with(strtolower($trimmed), $prefix)) {
				return null;
			}
		}

		/*
		 * Addresses that were already broken.
		 *
		 * "www.example.com" without a scheme is a relative path, and
		 * "http:/example.com" with one slash is not an address at all. Both were
		 * broken in 4.x too, so they are not the migration's doing — but a
		 * migration is the one moment somebody reads through a site's links, and
		 * these are exactly the ones nobody ever finds otherwise.
		 *
		 * Reported, never rewritten. Guessing what somebody meant is how a
		 * migration starts changing content rather than moving it.
		 */
		if (preg_match('#^(%20)*(https?:/[^/]|www\.)#i', $trimmed) === 1) {
			if (!in_array($trimmed, $this->suspicious, true)) {
				$this->suspicious[] = $trimmed;
			}

			return null;
		}

		// An exact match first: a page link, or an upload at the path 4.x used.
		if (isset($this->map[$trimmed])) {
			return $this->map[$trimmed];
		}

		// The same path, decoded — a link written with %20 for a file whose name
		// really does have spaces in it.
		$decoded = rawurldecode($trimmed);
		if ($decoded !== $trimmed && isset($this->map[$decoded])) {
			return $this->map[$decoded];
		}

		// A query string with more in it than the page name, which 4.x wrote for
		// blog posts and albums: ?file=blog&blog=hooikoorts.
		if (str_starts_with($trimmed, '?')) {
			$rewritten = $this->query($trimmed);
			if ($rewritten !== null) {
				return $rewritten;
			}
		}

		/*
		 * An upload whose filename was rebuilt. Matched on the basename, because
		 * the directory it sat in is the part that changed and the name is what
		 * makes it findable.
		 *
		 * Decoded first: an editor writes "Electro%20Acupunctuur.jpg" for a file
		 * that is called "Electro Acupunctuur.jpg" on disk, and comparing the two
		 * without decoding finds nothing — which left exactly those pictures
		 * broken while every other one was rewritten.
		 */
		$path = parse_url($trimmed, PHP_URL_PATH);
		$base = mb_strtolower(basename(rawurldecode(is_string($path) ? $path : $trimmed)));

		/*
		 * Compared without regard to case, which is not fussiness.
		 *
		 * An editor writes files/Privacy%20policy%20Tian%20Dao.pdf for a file
		 * called "privacy policy tian dao.pdf" — the capitals came from whoever
		 * typed the link, not from the disk. Matching exactly left that link
		 * pointing at nothing while the file itself sat in media/ perfectly
		 * migrated, which is the worst shape a failure can take: everything
		 * reports success and one page has a dead link on it.
		 *
		 * Two files differing only in case is the thing this could get wrong, and
		 * it is rare enough that a broken link is the likelier harm.
		 */
		foreach ($this->map as $from => $to) {
			if (str_starts_with($to, 'media/') && mb_strtolower(basename($from)) === $base) {
				return $to;
			}
		}

		return null;
	}

	/** `?file=blog&blog=name` and the album equivalent. */
	private function query(string $address): ?string
	{
		parse_str(ltrim($address, '?'), $query);

		$file = is_string($query['file'] ?? null) ? $query['file'] : '';
		if ($file === '') {
			return null;
		}

		foreach (['blog', 'albums'] as $module) {
			if ($file === $module && is_string($query[$module] ?? null) && $query[$module] !== '') {
				$inner = $module . '/' . $query[$module];

				return $this->map['?file=' . $inner] ?? $inner;
			}
		}

		// A plain ?file=pagename that the map did not have — the page may have
		// been renamed, and the map is keyed on the old name, so this is already
		// handled above. Reaching here means the page no longer exists, and
		// pointing at a name Pluck will 404 on is more honest than guessing.
		return $this->map['?file=' . $file] ?? $file;
	}
}
