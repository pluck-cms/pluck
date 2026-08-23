<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Form\Guard;
use Pluck\I18n\Locale;
use Pluck\I18n\Translator;
use Pluck\Module\BlogModule;
use Pluck\Module\ContactModule;
use Pluck\Security\Session;
use Pluck\Site\Urls;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;

/**
 * What stands between a public form and the internet.
 *
 * The first thing on this site that anybody can send something to, which makes
 * it the first thing worth attacking. The assertions below are mostly about the
 * ways somebody would try to get past it rather than about the ways a visitor
 * uses it.
 */
final class FormGuardTest extends TestCase
{
	public function run(): void
	{
		$this->group('the layers that are always on', fn () => $this->always());
		$this->group('the sum', fn () => $this->sum());
		$this->group('the rate limit', fn () => $this->rate());
		$this->group('what a reaction may contain', fn () => $this->reactions());
		$this->group('what a message may contain', fn () => $this->messages());
		$this->group('the markup a module emits', fn () => $this->formStacks());
	}

	private function always(): void
	{
		[$guard] = $this->site(Guard::CHALLENGE_NONE);

		$fields = $guard->fields();

		$this->assertTrue(str_contains($fields, Guard::HONEYPOT), 'a honeypot field is rendered');
		$this->assertTrue(str_contains($fields, 'left:-9999px'), 'off-screen rather than display:none, so a bot that reads CSS still fills it in');
		$this->assertTrue(str_contains($fields, 'aria-hidden="true"'), 'and hidden from a screen reader');
		$this->assertTrue(str_contains($fields, 'tabindex="-1"'), 'and skipped by the keyboard');

		$filled = $this->submission($guard, [Guard::HONEYPOT => 'http://spam.example']);
		$caught = $guard->check($filled, '1.1.1.1', 'test');

		// Told it succeeded on purpose. A bot told which field gave it away comes
		// back without filling that field in.
		$this->assertTrue($caught->ok, 'a filled honeypot looks like success to whoever sent it');
		$this->assertFalse($caught->stored, 'and is written nowhere');

		// Timing. The form was rendered a moment ago, so this is too fast.
		$this->assertFalse($guard->check($this->submission($guard), '1.1.1.2', 'test')->ok, 'a form returned immediately is refused');

		$this->assertSame(
			'form.error.too_fast',
			$guard->check($this->submission($guard), '1.1.1.3', 'test')->reason,
			'and says why',
		);

		// The timestamp is signed, so it cannot simply be moved back.
		$forged = $this->submission($guard);
		$forged[Guard::TIMESTAMP] = (string) (time() - 60);
		$this->assertSame(
			'form.error.start_again',
			$guard->check($forged, '1.1.1.4', 'test')->reason,
			'an edited timestamp does not pass',
		);

		$stale = $this->submission($guard, [], time() - 90000);
		$this->assertFalse($guard->check($stale, '1.1.1.5', 'test')->ok, 'nor does one from yesterday');

		$this->assertTrue(
			$guard->check($this->submission($guard, [], time() - 30), '1.1.1.6', 'test')->ok,
			'while a form somebody actually read goes through',
		);
	}

	private function sum(): void
	{
		[$guard] = $this->site(Guard::CHALLENGE_SUM);

		$challenge = $guard->challenge();

		$this->assertTrue($challenge['label'] !== '', 'a question is asked');
		$this->assertSame(
			0,
			preg_match('/\d/', $challenge['label']),
			'in words, not digits — digits are trivial to read out of the page',
		);

		[$token, $answer] = $this->solve($challenge);

		$good = $this->submission($guard, [Guard::QUESTION => $token, Guard::ANSWER => $answer], time() - 30);
		$this->assertTrue($guard->check($good, '2.2.2.1', 'test')->ok, 'the right answer passes');

		$words = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
		$inWords = $this->submission($guard, [Guard::QUESTION => $token, Guard::ANSWER => $words[(int) $answer]], time() - 30);
		$this->assertTrue($guard->check($inWords, '2.2.2.2', 'test')->ok, 'and so does the answer written out, because somebody will');

		$wrong = $this->submission($guard, [Guard::QUESTION => $token, Guard::ANSWER => '42'], time() - 30);
		$this->assertSame('form.error.wrong_answer', $guard->check($wrong, '2.2.2.3', 'test')->reason, 'a wrong answer does not');

		// The expected answer travels signed rather than in the session, so two
		// tabs do not fight each other — and cannot be rewritten.
		$forged = $this->submission($guard, [Guard::QUESTION => '99.deadbeef', Guard::ANSWER => '99'], time() - 30);
		$this->assertSame('form.error.start_again', $guard->check($forged, '2.2.2.4', 'test')->reason, 'nor a forged question');

		// reCAPTCHA replaces the sum; it never replaces the rest.
		[$recaptcha, $storage] = $this->site(Guard::CHALLENGE_RECAPTCHA);
		$fields = $recaptcha->fields();
		$this->assertTrue(str_contains($fields, Guard::HONEYPOT), 'with reCAPTCHA on, the honeypot stays');
		$this->assertTrue(str_contains($fields, Guard::TIMESTAMP), 'and so does the timing check');

		// Configured badly is refused rather than waved through: an unprotected
		// form nobody knows is unprotected is the worst of the three outcomes.
		$attempt = $this->submission($recaptcha, ['g-recaptcha-response' => 'x'], time() - 30);
		$this->assertSame(
			'form.error.captcha_not_configured',
			$recaptcha->check($attempt, '2.2.2.5', 'test')->reason,
			'reCAPTCHA without a secret refuses rather than letting everything through',
		);
	}

	private function rate(): void
	{
		[$guard, $storage] = $this->site(Guard::CHALLENGE_NONE);
		$storage->setSetting('form_hourly_limit', 3);

		$passed = 0;
		for ($i = 0; $i < 6; $i++) {
			if ($guard->check($this->submission($guard, [], time() - 30), '3.3.3.3', 'test')->ok) {
				$passed++;
			}
		}

		$this->assertSame(3, $passed, 'the limit is what it says');

		// Counted per address, in storage rather than the session — a bot that
		// throws away cookies has no session to count against, and that is exactly
		// the traffic this exists for.
		$this->assertTrue(
			$guard->check($this->submission($guard, [], time() - 30), '4.4.4.4', 'test')->ok,
			'and a different address is unaffected',
		);

		$this->assertTrue(
			$guard->check($this->submission($guard, [], time() - 30), '3.3.3.3', 'other-form')->ok,
			'as is a different form',
		);
	}

	private function reactions(): void
	{
		[$guard, $storage] = $this->site(Guard::CHALLENGE_NONE);

		$storage->setModuleData('blog', 'settings', ['allow_reactions' => true, 'moderate_reactions' => true]);
		$storage->setModuleData('blog', 'post:hay', ['title' => 'Hay', 'content' => '<p>x</p>', 'published' => true]);

		$blog = new BlogModule();
		$urls = new Urls('/', true);

		$blog->accept('hay', [
			'name' => 'Piet',
			'reaction' => '<script>alert(1)</script>Nice piece <b>indeed</b>',
			'website' => 'javascript:alert(1)',
		], $storage, $urls, $guard);

		$stored = array_values($storage->listModuleData('blog', 'reaction:hay:'))[0] ?? [];

		// Plain text, not sanitised markup. A comment field that accepts HTML is a
		// comment field that accepts a link farm, and "no markup at all" is a much
		// easier promise to keep than "only safe markup".
		$this->assertFalse(str_contains((string) $stored['reaction'], '<script'), 'a script in a reaction does not survive');
		$this->assertFalse(str_contains((string) $stored['reaction'], '<b>'), 'nor does any other markup');
		$this->assertTrue(str_contains((string) $stored['reaction'], 'Nice piece'), 'while the words are kept');

		$this->assertSame('', $stored['website'], 'a javascript: address is dropped rather than stored');
		$this->assertSame('pending', $stored['status'], 'a moderated site holds it back');

		// Closed means closed, whatever is posted at it.
		$storage->setModuleData('blog', 'post:shut', ['title' => 'Shut', 'published' => true, 'allow_reaction' => false]);
		$refused = $blog->accept('shut', ['name' => 'A', 'reaction' => 'B'], $storage, $urls, $guard);
		$this->assertFalse($refused['ok'], 'a closed post takes nothing');

		$draft = $blog->accept('nothing-here', ['name' => 'A', 'reaction' => 'B'], $storage, $urls, $guard);
		$this->assertFalse($draft['ok'], 'and neither does a post that does not exist');
	}

	/**
	 * The form is legible with no stylesheet at all.
	 *
	 * A <label> is inline, so a form built out of them lands on a single line in
	 * any theme that has no opinion about forms — which is every theme that did
	 * not know this module existed. It reached a real site that way.
	 */
	private function formStacks(): void
	{
		$form = (new ContactModule())->embed([], $this->emptyStore(), new Urls('/', true)) ?? '';

		$this->assertTrue(
			substr_count($form, '<p class="field"') >= 4,
			'each field is a block-level paragraph rather than an inline label',
		);
		$this->assertTrue(
			str_contains($form, '</label><br>'),
			'and the label sits above its box without needing CSS to put it there',
		);
	}

	private function emptyStore(): StorageDriver
	{
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $this->tempDir('pluck-form-markup'));
		$storage->install();

		return $storage;
	}

	private function messages(): void
	{
		[$guard, $storage] = $this->site(Guard::CHALLENGE_NONE);

		$contact = new ContactModule();
		$urls = new Urls('/', true);

		// A header injection attempt: the newline is what turns one header into
		// two, and a contact form is where people try it.
		$result = $contact->accept('', [
			'name' => "Jan\r\nBcc: everyone@example.com",
			'email' => 'jan@example.nl',
			'subject' => "Hello\nX-Injected: yes",
			'message' => "Line one\nLine two",
		], $storage, $urls, $guard);

		$this->assertTrue($result['ok'], 'the message is taken');

		$stored = array_values($storage->listModuleData('contact-form', 'message:'))[0] ?? [];

		$this->assertFalse(str_contains((string) $stored['name'], "\n"), 'a newline in a name does not survive');
		$this->assertFalse(str_contains((string) $stored['subject'], "\n"), 'nor in a subject');
		$this->assertTrue(str_contains((string) $stored['message'], "\n"), 'while the message keeps its line breaks');

		// Kept as well as e-mailed, because mail() on shared hosting fails quietly
		// and a lost message is worse than an undelivered one.
		$this->assertTrue(count($storage->listModuleData('contact-form', 'message:')) === 1, 'and it is on the site, not only in an e-mail');

		$this->assertSame(16, strlen((string) $stored['from']), 'the sender address is kept hashed, not in the clear');

		$this->assertFalse(
			$contact->accept('', ['name' => '', 'message' => 'hi'], $storage, $urls, $guard)['ok'],
			'a message without a name is refused',
		);
		$this->assertFalse(
			$contact->accept('', ['name' => 'Jan', 'email' => 'not-an-address', 'message' => 'hi'], $storage, $urls, $guard)['ok'],
			'and so is one with an address that is not one',
		);
	}

	// ---- fixture --------------------------------------------------------

	/**
	 * A valid submission, minus whatever the caller overrides.
	 *
	 * @param array<string,string> $extra
	 * @return array<string,string>
	 */
	private function submission(Guard $guard, array $extra = [], ?int $when = null): array
	{
		$fields = $guard->fields();
		preg_match('/name="' . Guard::TIMESTAMP . '" value="([^"]+)"/', $fields, $m);
		$stamp = $m[1] ?? '';

		if ($when !== null) {
			// Re-signed rather than edited: the point of the timing check is that
			// the value cannot be moved, so a test that moves it has to sign it.
			$stamp = $this->resign($guard, $when);
		}

		// array_merge, not +: the union operator keeps the *left* value for a
		// duplicate key, so overriding the honeypot silently did nothing and the
		// honeypot test was passing against an empty field.
		return array_merge([Guard::TIMESTAMP => $stamp, Guard::HONEYPOT => ''], $extra);
	}

	private function resign(Guard $guard, int $when): string
	{
		// Reaches for the same private signing the Guard uses, so the fixture
		// cannot drift away from the implementation without failing.
		$method = new \ReflectionMethod($guard, 'signTime');

		return (string) $method->invoke($guard, $when);
	}

	/** @return array{0:string,1:string} token and expected answer */
	private function solve(array $challenge): array
	{
		preg_match('/name="' . Guard::QUESTION . '" value="([^"]+)"/', $challenge['html'], $m);
		$token = $m[1] ?? '';

		$words = ['zero' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5];
		preg_match('/is (\w+) plus (\w+)/', $challenge['label'], $w);

		return [$token, (string) (($words[$w[1] ?? ''] ?? 0) + ($words[$w[2] ?? ''] ?? 0))];
	}

	/** @return array{0:Guard,1:StorageDriver} */
	private function site(string $challenge): array
	{
		$dir = $this->tempDir('pluck-forms');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();
		$storage->setSetting('form_challenge', $challenge);

		$translator = new Translator(Locale::fallback(), dirname(__DIR__) . '/lang');

		return [$this->withoutSessionWarnings(fn (): Guard => new Guard($storage, new Session(), $translator)), $storage];
	}
}
