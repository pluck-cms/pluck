<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Archive\EntryPolicy;

/**
 * The archive rules, one case per way Pluck 4's installers were exploited.
 *
 * Themes and modules through a zip upload are the most-exploited part of the
 * project's history. These are not invented cases: each group below names the
 * issue or CVE it comes from, and the test is the record rather than a changelog
 * line.
 */
final class EntryPolicyTest extends TestCase
{
	public function run(): void
	{
		$policy = new EntryPolicy();
		$refuses = function (string $name, string $why, int $size = 0, int $packed = 0, bool $link = false) use ($policy): void {
			$verdict = $policy->judge($name, $size, $packed, $link);
			$this->assertTrue($verdict !== null, $why);
		};
		$accepts = function (string $name, string $why, int $size = 1024, int $packed = 512) use ($policy): void {
			$verdict = $policy->judge($name, $size, $packed);
			$this->assertSame(null, $verdict, $why . ($verdict !== null ? ' — refused with: ' . $verdict : ''));
		};

		$this->group('what a theme may contain', function () use ($accepts): void {
			$accepts('theme.html', 'a template');
			$accepts('style.css', 'a stylesheet');
			$accepts('script.js', 'client-side javascript');
			$accepts('img/logo.png', 'an image in a subfolder');
			$accepts('fonts/plex.woff2', 'a webfont');
			$accepts('my-theme/theme.html', 'a theme wrapped in its own folder');
			$accepts('README.md', 'documentation');
			$accepts('preview.jpg', 'a preview image');
		});

		$this->group('theme upload RCE (#85, CVE-2022-26965)', function () use ($refuses): void {
			$refuses('theme.php', 'the exact payload of the 4.7.16 theme-upload RCE');
			$refuses('shell.php', 'a plain web shell');
			$refuses('info.php5', 'an alternative php extension');
			$refuses('shell.phtml', 'phtml is executed too');
			$refuses('shell.phar', 'the .phar bypass from #96');
			$refuses('shell.pht', 'another handler alias');
			$refuses('theme.inc', 'an include file is still code');

			// The one that a last-extension check misses.
			$refuses('shell.php.css', 'a double extension with php in the middle');
			$refuses('logo.png.php', 'php hidden behind an image extension');
			$refuses('a.php.b.css', 'php anywhere in the dotted name');
		});

		$this->group('re-enabling execution from inside an archive', function () use ($refuses): void {
			$refuses('.htaccess', 'an htaccess would switch php back on where it was off');
			$refuses('sub/.htaccess', 'including in a subfolder');
			$refuses('theme.htaccess', 'and as an extension');
			$refuses('php.ini', 'a php.ini can change the runtime');
			$refuses('.user.ini', 'so can a .user.ini');
		});

		$this->group('zip slip (#100)', function () use ($refuses): void {
			$refuses('../shell.css', 'a single step up');
			$refuses('../../../../../../var/www/html/style.css', 'the evilarc pattern from the report');
			$refuses('theme/../../style.css', 'traversal in the middle');
			$refuses('/etc/cron.d/style.css', 'an absolute path');
			$refuses('C:\\windows\\style.css', 'a windows absolute path');
			$refuses('..\\..\\style.css', 'backslash traversal');
			$refuses("style.css\0.php", 'a null byte truncation attempt');
		});

		$this->group('links and odd names', function () use ($refuses, $accepts): void {
			$refuses('style.css', 'a symlink, whatever it is named', 100, 50, true);
			$refuses('', 'an empty name');
			$refuses('noextension', 'a file with no extension at all');
			$refuses('.hidden', 'a dotfile');
			$refuses('a/b/c/d/e/f/g/h/i/style.css', 'nesting past the depth limit');
			$accepts('a/b/c/style.css', 'reasonable nesting is fine');
		});

		$this->group('archives as denial of service', function () use ($refuses, $accepts): void {
			$refuses('big.png', 'a single file over the per-entry limit', EntryPolicy::MAX_ENTRY_BYTES + 1, 1024);
			$refuses('bomb.txt', 'a 1000x compression ratio', 10_000_000, 10_000);
			$accepts('photo.jpg', 'normal image compression is not a bomb', 900_000, 800_000);
			$accepts('style.css', 'text compresses well and that is normal', 100_000, 3_000);
		});

		$this->group('directories', function () use ($policy): void {
			$this->assertSame(null, $policy->judge('img/'), 'a directory entry is allowed and writes nothing');
			$this->assertSame(null, $policy->judge('my-theme/img/'), 'a nested directory entry too');
			$this->assertTrue($policy->judge('../img/') !== null, 'but not one that traverses');
		});

		$this->group('the never-list covers the allow-list', function (): void {
			// If someone widens THEME_EXTENSIONS one day, this catches the overlap
			// rather than letting the two lists contradict each other silently.
			$overlap = array_intersect(EntryPolicy::THEME_EXTENSIONS, EntryPolicy::NEVER);
			$this->assertSame([], array_values($overlap), 'no extension is both allowed and forbidden');

			// And an explicitly widened policy still cannot be talked into php.
			$wide = new EntryPolicy(array_merge(EntryPolicy::THEME_EXTENSIONS, ['php']));
			$this->assertTrue(
				$wide->judge('shell.php') !== null,
				'even an allow-list containing php refuses php',
			);
		});
	}
}
