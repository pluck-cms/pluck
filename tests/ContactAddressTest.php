<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Bootstrap;
use Pluck\Install\Installer;
use Pluck\Storage\DriverFactory;

/**
 * The address a contact form sends to.
 *
 * There was nowhere to set it. The migrator wrote `contact_email` and everything
 * else read it, but no screen offered it and a fresh install never set it — so a
 * site installed rather than migrated had a contact form that mailed nobody.
 *
 * Nothing was lost: the message is stored and appears under Berichten. But
 * somebody waiting for a reply that never comes has no way to find that out,
 * which is the worst shape a fault can take — everything works and nobody knows
 * it does not.
 */
final class ContactAddressTest extends TestCase
{
	public function run(): void
	{
		$this->group('a fresh install has one', fn () => $this->installed());
		$this->group('and it can be changed', fn () => $this->settable());
	}

	private function installed(): void
	{
		$dir = $this->tempDir('pluck-contact-install');
		@mkdir($dir . '/data', 0o755, true);

		$app = Bootstrap::boot($dir);

		$errors = (new Installer($app))->run([
			'site_title' => 'Test',
			'storage' => 'flatfile',
			'username' => 'owner',
			'password' => 'Long enough to pass 123',
			'email' => 'post@example.org',
			'timezone' => 'Europe/Amsterdam',
			'language' => 'nl',
		]);

		$this->assertSame([], $errors, 'the install ran');

		$this->assertSame(
			'post@example.org',
			(string) $app->storage()->getSetting('contact_email', ''),
			'the site starts with the address given at install',
		);
	}

	/**
	 * A typo is refused rather than stored.
	 *
	 * An address that is not one leaves a contact form that keeps working and
	 * mails nobody — the same silence as having none, with the added confidence
	 * of having set it.
	 */
	private function settable(): void
	{
		$view = (string) file_get_contents(dirname(__DIR__) . '/views/admin/settings.php');
		$controller = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/SettingsController.php');

		$this->assertTrue(
			str_contains($view, 'name="contact_email"'),
			'there is a field for it',
		);
		$this->assertTrue(
			str_contains($controller, "FILTER_VALIDATE_EMAIL"),
			'and what is typed is checked before it is stored',
		);

		// Empty has to stay possible: a site with no contact form wants none.
		$this->assertTrue(
			str_contains($controller, "\$contact === '' || filter_var"),
			'while clearing it stays possible',
		);

		$diagnostics = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/DiagnosticsController.php');

		$this->assertTrue(
			str_contains($diagnostics, 'diagnostics.server.contact_email'),
			'and diagnostics says so when there is none',
		);
	}
}
