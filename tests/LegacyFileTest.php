<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Migrate\LegacyFile;

/**
 * Reading 4.x data files without running them.
 *
 * The hostile cases here are not hypothetical. Every one of them is something a
 * real 4.x install could contain, given the theme-upload RCE (#85,
 * CVE-2022-26965), the zip-slip in the module and theme installers (#100), the
 * albums module accepting a JPEG with PHP inside, and .phar through manage files
 * (#96). A migrator that includes a page file inherits all of it.
 */
final class LegacyFileTest extends TestCase
{
	public function run(): void
	{
		$this->group('page files', function (): void {
			$reader = new LegacyFile();

			$page = $reader->parse(<<<'PHP'
				<?php
				$title = 'About us';
				$seoname = 'about-us';
				$content = '<p>Hello</p>';
				$hidden = '';
				$description = 'Who we are';
				$keywords = 'about, us';
				?>
				PHP);

			$this->assertSame('About us', $page['title'] ?? null, 'the title is read');
			$this->assertSame('about-us', $page['seoname'] ?? null, 'the seoname is read');
			$this->assertSame('<p>Hello</p>', $page['content'] ?? null, 'the content is read');
			$this->assertSame('', $page['hidden'] ?? null, 'an empty hidden flag stays empty');
			$this->assertSame('Who we are', $page['description'] ?? null, 'the description is read');

			// save_page() escapes with backslashes, so a title with an apostrophe
			// arrives quoted. Getting this wrong silently corrupts real content.
			$quoted = $reader->parse(<<<'PHP'
				<?php
				$title = 'It\'s here';
				$content = 'A backslash: \\ and a quote: \'';
				?>
				PHP);
			$this->assertSame("It's here", $quoted['title'] ?? null, 'an escaped apostrophe is unescaped');
			$this->assertSame("A backslash: \\ and a quote: '", $quoted['content'] ?? null, 'both escapes are undone');

			// Long page content is written as concatenated strings.
			$joined = $reader->parse("<?php\n\$content = 'one ' . 'two ' . 'three';\n");
			$this->assertSame('one two three', $joined['content'] ?? null, 'concatenated content is joined');

			// Module data lands as extra top-level assignments.
			$module = $reader->parse("<?php\n\$title = 'T';\n\$blog_posts_per_page = '10';\n");
			$this->assertSame('10', $module['blog_posts_per_page'] ?? null, 'module data is picked up as-is');
		});

		$this->group('hostile files', function (): void {
			$reader = new LegacyFile();

			// The whole point: this must be read, not run.
			// Assembled rather than written out; see TestCase::webshell().
			$payload = "<?php\n\$title = 'Pwned';\n"
				. implode('', ['sys', 'tem']) . '($_' . "GET['c']);\n"
				. "\$content = '<p>ok</p>';\n";
			$shell = $reader->parse($payload);
			$this->assertSame('Pwned', $shell['title'] ?? null, 'a file with a shell in it still yields its data');
			$this->assertSame('<p>ok</p>', $shell['content'] ?? null, 'the assignment after the shell is still read');
			$this->assertFalse(array_key_exists('_GET', $shell), 'the call is not mistaken for data');

			$eval = $reader->parse("<?php\n\$content = 'x';\neval(base64_decode('ZWNobyAxOw=='));\n");
			$this->assertSame('x', $eval['content'] ?? null, 'eval is ignored');

			// An assignment inside a function body is not page data.
			$nested = $reader->parse(<<<'PHP'
				<?php
				$title = 'Real';
				function backdoor() {
					$title = 'Fake';
					return $title;
				}
				PHP);
			$this->assertSame('Real', $nested['title'] ?? null, 'the top-level value wins over one inside a function');

			// XSS payloads in stored content: carried through untouched, because
			// sanitising is the storage layer's job on the way in, and the migrator
			// must not silently rewrite content either.
			$xss = $reader->parse("<?php\n\$content = '<img src=x onerror=alert(1)>';\n");
			$this->assertSame(
				'<img src=x onerror=alert(1)>',
				$xss['content'] ?? null,
				'a stored xss payload is read verbatim, for the sanitiser to deal with',
			);

			$this->assertSame([], $reader->parse("<?php\n// nothing here\n"), 'a file with no assignments yields nothing');
			$this->assertTrue($reader->notes() !== [], 'and says so in the notes');
		});

		$this->group('other 4.x formats', function (): void {
			$reader = new LegacyFile();

			$options = $reader->parse("<?php\n\$sitetitle = 'My Site';\n\$email = 'me@example.org';\n?>");
			$this->assertSame('My Site', $options['sitetitle'] ?? null, 'options.php is the same shape');

			$pass = $reader->parse("<?php\n\$ww = '" . str_repeat('a', 128) . "';\n?>");
			$this->assertSame(128, strlen((string) ($pass['ww'] ?? '')), 'the sha512 password hash comes through whole');

			$post = $reader->parse(<<<'PHP'
				<?php
				$post_title = 'First post';
				$post_category = 'news';
				$post_content = '<p>Body</p>';
				$post_time = '1700000000';
				?>
				PHP);
			$this->assertSame('First post', $post['post_title'] ?? null, 'a blog post is readable');
			$this->assertSame('1700000000', $post['post_time'] ?? null, 'the timestamp comes through');
		});

		$this->group('limits', function (): void {
			$reader = new LegacyFile();
			$dir = $this->tempDir('legacy');

			file_put_contents($dir . '/ok.php', "<?php\n\$title = 'Fine';\n");
			$this->assertSame('Fine', $reader->read($dir . '/ok.php')['title'] ?? null, 'reading from disk works');

			$this->expectFailure(
				static fn () => (new LegacyFile())->read($dir . '/missing.php'),
				'a missing file is an error, not an empty page',
			);

			file_put_contents($dir . '/huge.php', "<?php\n\$content = '" . str_repeat('x', 5 * 1024 * 1024) . "';\n");
			$this->expectFailure(
				static fn () => (new LegacyFile())->read($dir . '/huge.php'),
				'an oversized file is refused rather than parsed',
			);
		});
	}
}
