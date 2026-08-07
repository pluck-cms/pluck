<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Auth\Auth;
use Pluck\Security\Session;
use Pluck\Auth\AttemptResult;
use Pluck\Auth\Throttle;
use Pluck\Auth\Totp;
use Pluck\Http\Request;
use Pluck\Http\Route;
use Pluck\Http\Router;
use Pluck\Model\Role;
use Pluck\Model\User;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;

/**
 * Sign-in behaviour.
 *
 * The Auth class itself talks to $_SERVER and to PHP's session, so the cases here
 * cover the parts that can be exercised without a live request: the TOTP maths,
 * the throttle's counting and backoff, and the route table's guards.
 */
final class AuthTest extends TestCase
{
	public function run(): void
	{
		$this->group('totp', function (): void {
			// A published RFC 6238 test vector: the all-ASCII secret "12345678901234567890"
			// is base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ, and at t=59 the SHA-1
			// six-digit code is 287082.
			$secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
			$this->assertSame('287082', Totp::at($secret, 1), 'RFC 6238 vector at counter 1');
			$this->assertSame('081804', Totp::at($secret, 37037036), 'RFC 6238 vector at t=1111111109');

			$this->assertTrue(Totp::verify($secret, '287082', 0, 59), 'exact step verifies');
			$this->assertTrue(Totp::verify($secret, '287082', 1, 89), 'previous step verifies inside the window');
			$this->assertFalse(Totp::verify($secret, '287082', 0, 200), 'a stale code is refused');
			$this->assertFalse(Totp::verify($secret, '000000', 1, 59), 'a wrong code is refused');

			// Formatting a code the way a user types it should not break verification.
			$this->assertTrue(Totp::verify($secret, '287 082', 0, 59), 'spaces in a typed code are ignored');

			$fresh = Totp::secret();
			$this->assertSame(32, strlen($fresh), '20 random bytes encode to 32 base32 characters');
			$this->assertTrue((bool) preg_match('/^[A-Z2-7]+$/', $fresh), 'secret is base32');
			$this->assertTrue(
				Totp::verify($fresh, Totp::at($fresh, intdiv(1_700_000_000, 30)), 0, 1_700_000_000),
				'a generated secret round-trips',
			);

			$uri = Totp::uri($fresh, 'bas', 'Example Site');
			$this->assertTrue(str_starts_with($uri, 'otpauth://totp/Example%20Site:bas?'), 'provisioning URI is labelled');
			$this->assertTrue(str_contains($uri, 'digits=6'), 'provisioning URI states its parameters');

			$this->assertFalse(Totp::verify('not base32 at all!!', '123456'), 'a broken secret verifies nothing');
		});

		$this->group('throttle', function (): void {
			$storage = $this->storage();
			$throttle = new Throttle($storage, maxAttempts: 3, lockSeconds: 600);
			$now = 1_700_000_000;

			$key = $throttle->key('198.51.100.7', 'Bas');
			$this->assertSame($key, $throttle->key('198.51.100.7', 'bas'), 'the key ignores username case');
			$this->assertTrue(
				$key !== $throttle->key('198.51.100.8', 'bas'),
				'a different address is throttled separately',
			);
			$this->assertSame(32, strlen($key), 'the key is a truncated hash, not the username');

			$this->assertFalse($throttle->isLocked($key, $now), 'nothing is locked to begin with');

			$throttle->hit($key, $now);
			$throttle->hit($key, $now);
			$this->assertFalse($throttle->isLocked($key, $now), 'two misses are still allowed');

			$throttle->hit($key, $now);
			$this->assertTrue($throttle->isLocked($key, $now), 'the third miss locks');
			$this->assertSame(600, $throttle->retryAfter($key, $now), 'the wait is the configured window');
			$this->assertFalse($throttle->isLocked($key, $now + 601), 'the lock expires');

			// Backoff: three more misses on top should lock for longer than the first.
			$throttle->hit($key, $now + 700);
			$throttle->hit($key, $now + 700);
			$throttle->hit($key, $now + 700);
			$this->assertTrue(
				$throttle->retryAfter($key, $now + 700) > 600,
				'a second lock lasts longer than the first',
			);

			$throttle->clear($key, $now + 700);
			$this->assertFalse($throttle->isLocked($key, $now + 700), 'a good sign-in clears the counter');

			// The stored value must not contain the username in the clear.
			$stored = json_encode($storage->getSetting('auth.attempts', []));
			$this->assertFalse(str_contains((string) $stored, 'bas'), 'the settings store holds no usernames');
		});

		$this->group('router', function (): void {
			$router = new Router();
			$router->get('pages', 'StubController', 'index', 'page.view');
			$router->post('page.save', 'StubController', 'save', 'page.view');
			$router->get('signin', 'StubController', 'form', guest: true);

			$this->assertTrue($router->find('GET', 'pages') instanceof Route, 'a GET route resolves');
			$this->assertSame(null, $router->find('POST', 'pages'), 'the verb is part of the match');
			$this->assertTrue($router->has('page.save'), 'has() ignores the verb');
			$this->assertFalse($router->has('page.destroy'), 'an unknown name is unknown');

			$signin = $router->find('GET', 'signin');
			$this->assertTrue($signin?->guest === true, 'the sign-in route is reachable without an account');
			$this->assertSame(null, $signin?->permission, 'a guest route carries no permission');

			$pages = $router->find('GET', 'pages');
			$this->assertSame('page.view', $pages?->permission, 'the permission is recorded on the route');
		});

		$this->group('request', function (): void {
			// The route name is the one piece of user input the router trusts, so it
			// is validated on capture rather than by each caller.
			$request = Request::fake('POST', 'page.save', ['title' => "  Trimmed  ", 'hidden' => '1']);
			$this->assertSame('Trimmed', $request->post('title'), 'scalars come back trimmed');
			$this->assertSame('  Trimmed  ', $request->raw('title'), 'raw() preserves whitespace');
			$this->assertTrue($request->postBool('hidden'), 'a checkbox value reads as true');
			$this->assertFalse($request->postBool('missing'), 'an absent checkbox reads as false');
			$this->assertSame('fallback', $request->post('missing', 'fallback'), 'the default is returned');
			$this->assertTrue($request->isPost(), 'the method is recorded');

			// An array where a string is expected used to be how injection got in.
			$hostile = Request::fake('POST', '', ['title' => ['not', 'a', 'string']]);
			$this->assertSame('', $hostile->post('title'), 'an array collapses to the default');
			$this->assertSame(['not', 'a', 'string'], $hostile->postList('title'), 'a list is read explicitly or not at all');
		});

		$this->group('roles', function (): void {
			$author = User::create('writer', 'a-long-enough-password', Role::Author);
			$this->assertTrue($author->can('page.create'), 'an author writes pages');
			$this->assertTrue($author->can('page.edit.own'), 'an author edits their own');
			$this->assertFalse($author->can('page.edit'), 'an author does not edit everyone else\'s');
			$this->assertFalse($author->can('user.view'), 'an author does not manage accounts');
			$this->assertFalse($author->can('settings.edit'), 'an author does not change the site');

			$editor = User::create('editor', 'a-long-enough-password', Role::Editor);
			$this->assertTrue($editor->can('page.edit'), 'an editor edits any page');
			$this->assertFalse($editor->can('user.create'), 'an editor does not add accounts');
			$this->assertFalse($editor->can('settings.edit'), 'an editor does not change the site');

			$admin = User::create('admin', 'a-long-enough-password', Role::Admin);
			$this->assertTrue($admin->can('settings.edit'), 'an administrator changes settings');
			$this->assertTrue($admin->can('user.create'), 'an administrator adds accounts');
			$this->assertFalse($admin->can('user.delete'), 'removing accounts stays with the owner');

			$owner = User::create('owner', 'a-long-enough-password', Role::Owner);
			$this->assertTrue($owner->can('user.delete'), 'the owner can do everything');
			$this->assertTrue($owner->can('anything.at.all'), 'the owner wildcard is a wildcard');
		});

		$this->group('password handling', function (): void {
			$user = User::create('bas', 'correct horse battery staple', Role::Owner);
			$this->assertTrue($user->verify('correct horse battery staple'), 'the right password verifies');
			$this->assertFalse($user->verify('correct horse battery stapl'), 'a near miss does not');
			$this->assertFalse(
				str_contains($user->passwordHash, 'correct'),
				'the plain password is not in the hash',
			);
			$this->assertTrue(
				str_starts_with($user->passwordHash, '$2y$') || str_starts_with($user->passwordHash, '$argon2'),
				'the hash is bcrypt or argon2, never md5',
			);

			$user->setPassword('a completely different password');
			$this->assertFalse($user->verify('correct horse battery staple'), 'the old password stops working');

			/*
			 * Sign-in spends a bcrypt verify even when the username is unknown, so
			 * response time cannot be used to enumerate accounts. That only holds
			 * while the dummy hash is valid and costs the same as a real one: an
			 * invalid string fails in microseconds, and the wrong cost is a tell in
			 * the other direction. This started out as a cost-12 placeholder that
			 * made an unknown username four times slower than a known one.
			 */
			$dummy = password_get_info(Auth::dummyHash());
			$real = password_get_info(password_hash('sample', PASSWORD_DEFAULT));

			/*
			 * Two-factor enforcement, at the level where it is decided. The
			 * controller and the sign-in screen both trust attempt() to say
			 * "TotpRequired" rather than "Ok" — if that ever regresses, a stolen
			 * password is enough again, and no interface test would notice because
			 * the screens would look exactly the same.
			 */
			$this->withoutSessionWarnings(function (): void {
				$storage = $this->storage();
				$account = User::create('twofa', 'a-decent-long-password', Role::Owner);
				$account->totpSecret = Totp::secret();
				$storage->saveUser($account);

				$auth = new Auth($storage, new Session(), new Throttle($storage));

				$this->assertSame(
					AttemptResult::TotpRequired,
					$auth->attempt('twofa', 'a-decent-long-password'),
					'the right password alone is not a sign-in when 2FA is on',
				);
				$this->assertSame(
					AttemptResult::TotpInvalid,
					$auth->attempt('twofa', 'a-decent-long-password', '000000'),
					'a wrong code is refused',
				);
				$this->assertSame(
					AttemptResult::BadCredentials,
					$auth->attempt('twofa', 'wrong-password', Totp::at($account->totpSecret, intdiv(time(), 30))),
					'a valid code does not make up for a wrong password',
				);
				$this->assertSame(
					AttemptResult::Ok,
					$auth->attempt('twofa', 'a-decent-long-password', Totp::at($account->totpSecret, intdiv(time(), 30))),
					'password plus a current code signs in',
				);

				$plain = User::create('noplain', 'a-decent-long-password', Role::Editor);
				$storage->saveUser($plain);
				$this->assertSame(
					AttemptResult::Ok,
					$auth->attempt('noplain', 'a-decent-long-password'),
					'an account without 2FA still signs in on a password',
				);
			});

			$this->assertFalse(password_verify('', Auth::dummyHash()), 'the dummy hash matches nothing');
			$this->assertSame($real['algo'], $dummy['algo'], 'the dummy hash uses the current default algorithm');
			$this->assertSame(
				$real['options']['cost'] ?? null,
				$dummy['options']['cost'] ?? null,
				'the dummy hash costs what a real one costs',
			);
		});
	}

	private function storage(): StorageDriver
	{
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $this->tempDir('pluck-auth'));
		$storage->install();

		return $storage;
	}
}
