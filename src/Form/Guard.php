<?php
declare(strict_types=1);

namespace Pluck\Form;

use Pluck\I18n\Translator;
use Pluck\Security\Escaper;
use Pluck\Security\Session;
use Pluck\Storage\StorageDriver;

/**
 * What stands between a public form and the internet.
 *
 * Every form a visitor can submit goes through this — the reaction form and the
 * contact form both, and anything added later. Writing it once is the point: a
 * second form that grows its own protection is a second form with its own gaps.
 *
 * Four layers, and they are deliberately different in kind:
 *
 * **A honeypot.** A field nobody sees and a bot fills in. Costs a visitor
 * nothing, costs a person using a screen reader nothing, and catches the
 * automated submissions that make up most of the volume. Always on.
 *
 * **Timing.** A form fetched and returned within a couple of seconds was not
 * read. Always on. The timestamp is signed, so it cannot simply be edited.
 *
 * **A rate limit.** Per address, in storage rather than in the session, since a
 * bot that discards cookies has no session to count against. Always on.
 *
 * **A challenge** — a sum, or reCAPTCHA if somebody turns it on. This is the
 * only optional layer, and the only one that costs a visitor anything.
 *
 * The first three stay whatever the fourth is set to. That matters: reCAPTCHA
 * hands every visitor to Google, and a site owner who turns it off to avoid that
 * should not lose the protection that has no such cost.
 *
 * None of this stops somebody typing spam by hand. That is what moderation is
 * for, and it exists.
 */
final class Guard
{
	/** The field a person never sees. Named to look worth filling in. */
	public const HONEYPOT = 'website_url';

    public const TIMESTAMP = 'form_time';

	public const ANSWER = 'form_answer';

	public const QUESTION = 'form_question';

	/** Anything returned faster than this was not read by a person. */
	private const MINIMUM_SECONDS = 3;

	/** And anything slower than this is a form left open overnight. */
	private const MAXIMUM_SECONDS = 7200;

	public const CHALLENGE_NONE = 'none';

	public const CHALLENGE_SUM = 'sum';

	public const CHALLENGE_RECAPTCHA = 'recaptcha';

	public function __construct(
		private readonly StorageDriver $storage,
		private readonly Session $session,
		private readonly ?Translator $translator = null,
	) {
	}

	// ---- rendering ------------------------------------------------------

	/**
	 * The hidden fields every protected form needs.
	 *
	 * Returned as markup because a template that has to assemble this itself is a
	 * template that will one day forget a piece of it.
	 */
	public function fields(): string
	{
		$now = time();

		$html = '<input type="hidden" name="' . self::TIMESTAMP . '" value="' . Escaper::html($this->signTime($now)) . '">';

		// Off-screen rather than display:none. A bot that skips hidden fields is
		// a bot that reads CSS, and most do not; this way the field is still in
		// the form for the ones that fill in everything they find. aria-hidden and
		// tabindex keep it away from anyone using a keyboard or a screen reader.
		$html .= '<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden">'
			. '<label for="' . self::HONEYPOT . '">' . Escaper::html($this->t('form.honeypot_label')) . '</label>'
			. '<input type="text" id="' . self::HONEYPOT . '" name="' . self::HONEYPOT . '" tabindex="-1" autocomplete="off" value="">'
			. '</div>';

		return $html;
	}

	/**
	 * The visible challenge, or an empty string when there is none.
	 *
	 * @return array{html:string,label:string} label is empty when nothing is asked
	 */
	public function challenge(): array
	{
		return match ($this->challengeKind()) {
			self::CHALLENGE_SUM => $this->sumChallenge(),
			self::CHALLENGE_RECAPTCHA => $this->recaptchaWidget(),
			default => ['html' => '', 'label' => ''],
		};
	}

	/**
	 * A sum in words.
	 *
	 * Words rather than digits, because "3 + 4" is trivial to read out of the
	 * page and "drie plus vier" needs the language. Small numbers, so the answer
	 * is never ambiguous and never hard.
	 *
	 * @return array{html:string,label:string}
	 */
	private function sumChallenge(): array
	{
		$left = random_int(1, 5);
		$right = random_int(1, 4);

		// The expected answer is signed into the form rather than kept in the
		// session: a visitor with two tabs open would otherwise answer the
		// question from one of them and be told they were wrong.
		$token = $this->sign((string) ($left + $right));

		$question = $this->t('form.sum_question', [
			'left' => $this->word($left),
			'right' => $this->word($right),
		]);

		return [
			'label' => $question,
			'html' => '<input type="hidden" name="' . self::QUESTION . '" value="' . Escaper::html($token) . '">'
				. '<input type="text" name="' . self::ANSWER . '" required autocomplete="off" inputmode="numeric" size="8">',
		];
	}

	/** @return array{html:string,label:string} */
	private function recaptchaWidget(): array
	{
		$key = (string) $this->storage->getSetting('recaptcha_site_key', '');
		if ($key === '') {
			return ['html' => '', 'label' => ''];
		}

		// The script tag is the whole reason this is not the default: from here on
		// every visitor to the site is also a visitor to Google.
		return [
			'label' => '',
			'html' => '<div class="g-recaptcha" data-sitekey="' . Escaper::html($key) . '"></div>'
				. '<script src="https://www.google.com/recaptcha/api.js" async defer></script>',
		];
	}

	// ---- checking -------------------------------------------------------

	/**
	 * Whether this submission may proceed.
	 *
	 * @param array<string,mixed> $post
	 */
	public function check(array $post, string $ip, string $formName): FormResult
	{
		// The honeypot first: it is the cheapest check and catches the most.
		if (trim((string) ($post[self::HONEYPOT] ?? '')) !== '') {
			// Reported as accepted. A bot told it failed learns which field gave
			// it away; a bot told it succeeded goes away satisfied.
			return FormResult::silentlyDiscarded();
		}

		$when = $this->readTime((string) ($post[self::TIMESTAMP] ?? ''));
		if ($when === null) {
			return FormResult::refused('form.error.start_again');
		}

		$age = time() - $when;
		if ($age < self::MINIMUM_SECONDS) {
			return FormResult::refused('form.error.too_fast');
		}
		if ($age > self::MAXIMUM_SECONDS) {
			return FormResult::refused('form.error.too_slow');
		}

		if (!$this->withinRate($ip, $formName)) {
			return FormResult::refused('form.error.too_many');
		}

		return $this->checkChallenge($post);
	}

	/** @param array<string,mixed> $post */
	private function checkChallenge(array $post): FormResult
	{
		return match ($this->challengeKind()) {
			self::CHALLENGE_SUM => $this->checkSum($post),
			self::CHALLENGE_RECAPTCHA => $this->checkRecaptcha($post),
			default => FormResult::accepted(),
		};
	}

	/** @param array<string,mixed> $post */
	private function checkSum(array $post): FormResult
	{
		$expected = $this->readSigned((string) ($post[self::QUESTION] ?? ''));
		$given = trim((string) ($post[self::ANSWER] ?? ''));

		if ($expected === null) {
			return FormResult::refused('form.error.start_again');
		}

		// Digits or the word, because somebody will type "zeven".
		if ($given !== $expected && mb_strtolower($given) !== mb_strtolower($this->word((int) $expected))) {
			return FormResult::refused('form.error.wrong_answer');
		}

		return FormResult::accepted();
	}

	/** @param array<string,mixed> $post */
	private function checkRecaptcha(array $post): FormResult
	{
		$secret = (string) $this->storage->getSetting('recaptcha_secret', '');
		$response = (string) ($post['g-recaptcha-response'] ?? '');

		if ($secret === '') {
			// Configured badly rather than maliciously. Letting it through would
			// mean an unprotected form nobody knows is unprotected.
			return FormResult::refused('form.error.captcha_not_configured');
		}

		if ($response === '') {
			return FormResult::refused('form.error.captcha_missing');
		}

		$verified = $this->askGoogle($secret, $response);

		return $verified ? FormResult::accepted() : FormResult::refused('form.error.captcha_failed');
	}

	private function askGoogle(string $secret, string $response): bool
	{
		$url = 'https://www.google.com/recaptcha/api/siteverify';
		$body = http_build_query(['secret' => $secret, 'response' => $response]);

		$json = false;

		if (function_exists('curl_init')) {
			$curl = curl_init($url);
			if ($curl !== false) {
				curl_setopt_array($curl, [
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $body,
					CURLOPT_TIMEOUT => 8,
					CURLOPT_SSL_VERIFYPEER => true,
					CURLOPT_SSL_VERIFYHOST => 2,
				]);
				$json = curl_exec($curl);
				curl_close($curl);
			}
		} elseif (ini_get('allow_url_fopen')) {
			$json = @file_get_contents($url, false, stream_context_create([
				'http' => [
					'method' => 'POST',
					'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
					'content' => $body,
					'timeout' => 8,
				],
			]));
		}

		if (!is_string($json)) {
			// Google unreachable. Refused rather than waved through: a form that
			// silently stops checking is worse than one that stops working, because
			// only the second gets reported.
			return false;
		}

		$data = json_decode($json, true);

		return is_array($data) && ($data['success'] ?? false) === true;
	}

	// ---- rate limiting --------------------------------------------------

	/**
	 * At most a handful of submissions an hour from one address.
	 *
	 * Counted in storage rather than the session, because a bot that throws away
	 * cookies has no session to count against — which is exactly the traffic this
	 * is for.
	 */
	private function withinRate(string $ip, string $formName): bool
	{
		$limit = max(1, (int) $this->storage->getSetting('form_hourly_limit', 5));
		$key = 'ratelimit:' . $formName . ':' . substr(hash('sha256', $ip), 0, 16);

		$stored = $this->storage->getModuleData('forms', $key, []);
		$stored = is_array($stored) ? $stored : [];

		$hourAgo = time() - 3600;
		$recent = array_values(array_filter(
			array_map('intval', $stored['at'] ?? []),
			static fn (int $at): bool => $at > $hourAgo,
		));

		if (count($recent) >= $limit) {
			return false;
		}

		$recent[] = time();
		$this->storage->setModuleData('forms', $key, ['at' => $recent]);

		return true;
	}

	/**
	 * Throw away rate-limit records nobody will read again.
	 *
	 * Called from the admin rather than on every submission: a public form should
	 * not be the thing that decides to walk the whole store.
	 */
	public function forget(): int
	{
		$hourAgo = time() - 3600;
		$removed = 0;

		foreach ($this->storage->listModuleData('forms', 'ratelimit:') as $key => $value) {
			$at = is_array($value) ? array_map('intval', $value['at'] ?? []) : [];

			if (array_filter($at, static fn (int $when): bool => $when > $hourAgo) === []) {
				$this->storage->deleteModuleData('forms', $key);
				$removed++;
			}
		}

		return $removed;
	}

	// ---- signing --------------------------------------------------------

	/**
	 * A value the visitor carries and cannot change.
	 *
	 * The same secret the session uses. Nothing here is a secret worth stealing —
	 * the point is only that a timestamp and an expected answer come back as they
	 * were sent.
	 */
	private function sign(string $value): string
	{
		return $value . '.' . hash_hmac('sha256', $value, $this->secret());
	}

	private function readSigned(string $signed): ?string
	{
		$dot = strrpos($signed, '.');
		if ($dot === false) {
			return null;
		}

		$value = substr($signed, 0, $dot);
		$mac = substr($signed, $dot + 1);

		return hash_equals(hash_hmac('sha256', $value, $this->secret()), $mac) ? $value : null;
	}

	private function signTime(int $when): string
	{
		return $this->sign((string) $when);
	}

	private function readTime(string $signed): ?int
	{
		$value = $this->readSigned($signed);

		return $value !== null && ctype_digit($value) ? (int) $value : null;
	}

	private function secret(): string
	{
		$secret = $this->storage->getSetting('form_secret', '');

		if (!is_string($secret) || strlen($secret) < 32) {
			$secret = bin2hex(random_bytes(32));
			$this->storage->setSetting('form_secret', $secret);
		}

		return $secret;
	}

	// ---- small helpers --------------------------------------------------

	public function challengeKind(): string
	{
		$kind = (string) $this->storage->getSetting('form_challenge', self::CHALLENGE_SUM);

		return in_array($kind, [self::CHALLENGE_NONE, self::CHALLENGE_SUM, self::CHALLENGE_RECAPTCHA], true)
			? $kind
			: self::CHALLENGE_SUM;
	}

	private function word(int $number): string
	{
		return $this->t('form.number.' . max(0, min(9, $number)));
	}

	/** @param array<string,string|int> $replacements */
	private function t(string $key, array $replacements = []): string
	{
		return $this->translator?->get($key, $replacements) ?? $key;
	}
}
