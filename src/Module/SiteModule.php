<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Site\SearchResult;
use Pluck\Site\Urls;
use Pluck\Storage\StorageDriver;

/**
 * What a module has to provide to appear on the site.
 *
 * The contract that Pluck 4 never had. There, a module was a PHP file that got
 * included into the page and could do anything: write globals, echo directly,
 * define functions the theme then depended on. That is why a module could break
 * a site by existing, and why the security model had to be a blacklist.
 *
 * Here a module is asked a question and returns a value. It gets read access to
 * storage and a way to build URLs, and it hands back a ModuleView. It does not
 * echo, does not touch the filesystem, does not know what theme is in use, and
 * cannot reach the admin session. A module that throws takes down its own part
 * of the page and nothing else.
 *
 * Modules mount at a path. `blog` owns /blog and everything under it; a page at
 * that path would be shadowed, which the admin warns about rather than allowing
 * silently.
 */
interface SiteModule
{
	/** Machine name, used as the module-data namespace and the lang prefix. */
	public function name(): string;

	/** The path this module owns, without slashes: "blog", "albums". */
	public function mountPath(): string;

	/**
	 * Render whatever lives at $path within this module.
	 *
	 * $path is the remainder after the mount point: '' is the index, 'some-post'
	 * is one item. Returning null means "nothing here", which the front
	 * controller turns into a 404 — a module must not decide that itself,
	 * because it does not know what else might claim the address.
	 *
	 * @param array<string,string> $query already-validated query parameters
	 */
	public function render(string $path, array $query, StorageDriver $storage, Urls $urls): ?ModuleView;

	/**
	 * A view into this module, for embedding in a page with `[module:name]`.
	 *
	 * Deliberately not the same as render(). What belongs on a page is a glimpse
	 * with a way through to the real thing — a few recent posts and a link, not a
	 * paginated index whose "older posts" button leads the reader out of the page
	 * they were on. Returning null means this module has nothing to embed, which
	 * is a perfectly ordinary answer.
	 *
	 * The returned markup is inserted without further escaping, on the same terms
	 * as ModuleView::$html: everything a module stores came through the sanitiser
	 * on the way in, and everything else it prints goes through the escaper.
	 *
	 * @param array<string,string> $parameters from the marker, unvalidated
	 */
	public function embed(array $parameters, StorageDriver $storage, Urls $urls): ?string;

	/**
	 * This module's matches for a site search.
	 *
	 * Paths in the results are addresses within this module's mount, the same
	 * ones render() answers to, so a result always leads somewhere that exists.
	 * A module with nothing searchable returns an empty list, which is an
	 * ordinary answer — not everything a module holds is text.
	 *
	 * No Urls here on purpose: a result carries a path and the search page builds
	 * the link, so a module cannot hand back an address pointing somewhere else.
	 *
	 * @return list<SearchResult>
	 */
	public function search(string $query, StorageDriver $storage): array;
}
