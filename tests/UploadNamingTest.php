<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Support\UploadName;

/**
 * Uploaded filenames are rebuilt, not cleaned.
 *
 * Two historic issues live here. CVE-2018-19420 was authenticated stored XSS
 * "because the character set for filenames is not properly restricted" — the
 * filename itself was the payload, rendered in the file manager. And the whole
 * .phar / double-extension family (#96, and the albums module accepting a JPEG
 * with PHP inside) turns on a name surviving upload intact.
 *
 * The uploader builds the stored name with Support\UploadName, which slugs the
 * stem and puts back the one validated extension, so no dot, quote, angle bracket
 * or slash from the original can reach the disk. These assertions call that class
 * directly - a copy of the rule here would keep passing after the rule changed.
 */
final class UploadNamingTest extends TestCase
{
	public function run(): void
	{
		$stored = UploadName::build(...);

		$this->group('double extensions cannot survive', function () use ($stored): void {
			$this->assertSame('shell-php.txt', $stored('shell.php.txt', 'txt'), 'the inner .php becomes part of the stem');
			$this->assertSame('logo-png.jpg', $stored('logo.png.jpg', 'jpg'), 'any inner extension loses its dot');
			$this->assertSame('a-php-b.css', $stored('a.php.b.css', 'css'), 'several of them too');
			$this->assertFalse(
				str_contains($stored('x.phar.png', 'png'), '.phar'),
				'.phar cannot reach the disk as an extension',
			);
		});

		$this->group('filenames as XSS payloads (CVE-2018-19420)', function () use ($stored): void {
			$this->assertSame(
				'img-src-x-onerror-alert-1.png',
				$stored('<img src=x onerror=alert(1)>.png', 'png'),
				'a tag in the filename is flattened to a slug',
			);
			// pathinfo() treats the slash in "</script>" as a path separator, so only
			// the last segment survives before slugging even starts. Either way
			// nothing executable reaches the name.
			$this->assertSame('script.png', $stored('"><script>alert(1)</script>.png', 'png'), 'so is a breakout attempt');
			foreach (['<', '>', '"', "'", '&', '`', '\\', '/'] as $character) {
				$this->assertFalse(
					str_contains($stored('a' . $character . 'b.png', 'png'), $character),
					'the character ' . $character . ' cannot appear in a stored name',
				);
			}
		});

		$this->group('traversal in a filename', function () use ($stored): void {
			$this->assertSame('passwd.txt', $stored('../../../etc/passwd.txt', 'txt'), 'path parts are dropped entirely');
			$this->assertFalse(str_contains($stored('..%2f..%2fx.png', 'png'), '..'), 'encoded traversal too');
		});

		$this->group('a taken name gets a counter, not a silent overwrite', function (): void {
			$existing = ['holiday-photo.jpg' => true, 'holiday-photo-2.jpg' => true];
			$taken = static fn (string $candidate): bool => isset($existing[$candidate]);

			$this->assertSame(
				'holiday-photo-3.jpg',
				UploadName::unique('Holiday Photo.jpg', 'jpg', $taken),
				'the counter walks past every name already on disk',
			);
			$this->assertSame(
				'holiday-photo.png',
				UploadName::unique('Holiday Photo.png', 'png', $taken),
				'a free name is used as it is',
			);
		});

		$this->group('ordinary names still look like themselves', function () use ($stored): void {
			$this->assertSame('holiday-photo.jpg', $stored('Holiday Photo.jpg', 'jpg'), 'a normal name stays readable');
			$this->assertSame('uber-uns.pdf', $stored('Über uns.pdf', 'pdf'), 'accents fold rather than vanish');
			$this->assertSame('file.png', $stored('....png', 'png'), 'a name of only dots falls back');
			$this->assertTrue(strlen($stored(str_repeat('a', 300) . '.png', 'png')) <= 84, 'long names are capped');
		});
	}
}
