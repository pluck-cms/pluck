<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\I18n\Translates;
use Pluck\Form\Guard;
use Pluck\I18n\Translator;
use Pluck\Security\Escaper;
use Pluck\Site\Search;
use Pluck\Site\SearchResult;
use Pluck\Site\Urls;
use Pluck\Storage\StorageDriver;

/**
 * A contact form.
 *
 * Mounted at `/contact-form` rather than `/contact`, because almost every site
 * already has a page called Contact and taking that name would hide it. The
 * form belongs on that page anyway, put there with `[module:contact-form]`, so
 * the mount is really only somewhere for the submission to go.
 *
 * Messages are kept as well as e-mailed. `mail()` on shared hosting fails
 * quietly and often — wrong sender domain, a full queue, silent spam filtering —
 * and a contact form that loses a message because the mail did not arrive is
 * worse than one that never sent mail at all. The admin screen shows what came
 * in whether or not it was delivered.
 */
final class ContactModule implements SiteModule, PublicForm
{
	use Translates;

	public function __construct(private readonly ?Translator $translator = null)
	{
	}

	public function name(): string
	{
		return 'contact-form';
	}

	public function mountPath(): string
	{
		return 'contact-form';
	}

	public function render(string $path, array $query, StorageDriver $storage, Urls $urls): ?ModuleView
	{
		if ($path !== '') {
			return null;
		}

		// Reachable on its own so a submission has somewhere to land, and so a
		// site that would rather link to a form than embed one still can.
		return new ModuleView(
			title: $this->t('contact.title'),
			html: $this->form($storage, $urls, $query),
		);
	}

	/**
	 * The form, for putting in a page with `[module:contact-form]`.
	 *
	 * @param array<string,string> $parameters
	 */
	public function embed(array $parameters, StorageDriver $storage, Urls $urls): ?string
	{
		return $this->form($storage, $urls, []);
	}

	/** Messages are not public, so there is nothing here to find. */
	public function search(string $query, StorageDriver $storage): array
	{
		return [];
	}

	// ---- the form -------------------------------------------------------

	/** @param array<string,string> $query */
	private function form(StorageDriver $storage, Urls $urls, array $query): string
	{
		$guard = new Guard($storage, new \Pluck\Security\Session(), $this->translator);

		// A message that came back with ?sent=1 has been dealt with; showing the
		// form again with the fields still filled in reads as though it failed.
		if (($query['sent'] ?? '') === '1') {
			return '<div class="form-done"><p>' . Escaper::html($this->t('contact.thanks')) . '</p></div>';
		}

		$error = ($query['error'] ?? '') !== ''
			? '<p class="form-error">' . Escaper::html($this->t('form.error.' . preg_replace('/[^a-z_]/', '', $query['error']))) . '</p>'
			: '';

		$challenge = $guard->challenge();

		$html = '<form class="contact-form" method="post" action="' . Escaper::html($urls->to('contact-form')) . '">';
		$html .= $error;
		$html .= $guard->fields();

		/*
		 * A paragraph per field, with the label above the box.
		 *
		 * A <label> is inline, so a form built out of them lands on one line in
		 * any theme that has no opinion about forms — which is every theme that
		 * did not know this module existed. A <p> is block-level and stacks with
		 * no stylesheet at all, and a theme that wants more can style .field.
		 *
		 * The markup a module emits has to be usable on its own. Depending on the
		 * theme to make it legible is depending on something a module cannot see.
		 */
		foreach ([
			['name', 'text', true],
			['email', 'email', true],
			['subject', 'text', false],
		] as [$field, $type, $required]) {
			$html .= '<p class="field">'
				. '<label for="contact-' . $field . '">' . Escaper::html($this->t('contact.label.' . $field)) . '</label><br>'
				. '<input type="' . $type . '" id="contact-' . $field . '" name="' . $field . '" maxlength="200"'
				. ($required ? ' required' : '') . '></p>';
		}

		$html .= '<p class="field">'
			. '<label for="contact-message">' . Escaper::html($this->t('contact.label.message')) . '</label><br>'
			. '<textarea id="contact-message" name="message" rows="7" maxlength="5000" required></textarea></p>';

		if ($challenge['html'] !== '') {
			$html .= '<p class="field"><label>' . Escaper::html($challenge['label']) . '</label><br>' . $challenge['html'] . '</p>';
		}

		$html .= '<p><button type="submit">' . Escaper::html($this->t('contact.action.send')) . '</button></p>';

		return $html . '</form>';
	}

	// ---- receiving ------------------------------------------------------

	/**
	 * Keep the message, then try to send it.
	 *
	 * That order matters. Written first, mailed second: if `mail()` fails — and on
	 * shared hosting it does, quietly — the message is still on the site rather
	 * than gone.
	 *
	 * @param array<string,mixed> $post
	 * @return array{ok:bool,message:string,redirect:?string}
	 */
	public function accept(string $path, array $post, StorageDriver $storage, Urls $urls, Guard $guard): array
	{
		$name = $this->clean($post['name'] ?? '', 200);
		$email = $this->clean($post['email'] ?? '', 200);
		$subject = $this->clean($post['subject'] ?? '', 200);
		// The only field that keeps its line breaks, because it is the only one
		// that goes in a mail body rather than a mail header.
		$message = $this->clean($post['message'] ?? '', 5000, keepNewlines: true);

		if ($name === '' || $message === '') {
			return ['ok' => false, 'message' => 'form.error.fill_it_in', 'redirect' => null];
		}

		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			return ['ok' => false, 'message' => 'form.error.bad_email', 'redirect' => null];
		}

		$id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));

		$storage->setModuleData('contact-form', 'message:' . $id, [
			'name' => $name,
			'email' => $email,
			'subject' => $subject,
			'message' => $message,
			'received_at' => gmdate('c'),
			// Kept for the rare case of having to answer "who kept sending this",
			// hashed because a contact form has no business holding addresses in
			// the clear once the message has been read.
			'from' => substr(hash('sha256', $this->ip()), 0, 16),
			'read' => false,
		]);

		$this->email($storage, $name, $email, $subject, $message);

		return ['ok' => true, 'message' => 'contact.thanks', 'redirect' => $urls->to('contact-form') . '?sent=1'];
	}

	private function email(StorageDriver $storage, string $name, string $email, string $subject, string $message): void
	{
		$to = (string) $storage->getSetting('contact_email', '');

		/*
		 * No address means nothing is sent, and that is worth knowing.
		 *
		 * The message is stored either way and appears under Berichten, so it is
		 * not lost — but somebody who set up a contact form and never gets a mail
		 * has no way to find out why. A line in the diagnostics is where somebody
		 * looks when something is not happening.
		 */
		if ($to === '' || !function_exists('mail')) {
			return;
		}

		$site = (string) $storage->getSetting('site_title', 'Pluck');

		$body = implode("\n", [
			$this->t('contact.label.name') . ': ' . $name,
			$this->t('contact.label.email') . ': ' . ($email !== '' ? $email : '-'),
			'',
			$message,
		]);

		// The From is the site's own domain, never the visitor's address: sending
		// as somebody else is what gets a server's mail rejected outright. The
		// visitor goes in Reply-To, where a mail client will use it.
		$headers = 'From: ' . $this->sender($to) . "\r\n";
		if ($email !== '') {
			$headers .= 'Reply-To: ' . $this->header($email) . "\r\n";
		}
		$headers .= "Content-Type: text/plain; charset=utf-8";

		@mail(
			$to,
			$this->header(($subject !== '' ? $subject : $this->t('contact.subject_fallback')) . ' - ' . $site),
			$body,
			$headers,
		);
	}

	// ---- helpers --------------------------------------------------------

	/**
	 * Control characters out.
	 *
	 * Carriage return and line feed included, unless the caller says otherwise —
	 * a newline in a name or a subject is what turns one mail header into two, and
	 * that is the oldest thing anybody tries on a contact form. Only the message
	 * body keeps them, and a body cannot be split into headers.
	 */
	private function clean(mixed $value, int $length, bool $keepNewlines = false): string
	{
		$text = is_scalar($value) ? (string) $value : '';

		$pattern = $keepNewlines
			? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'
			: '/[\x00-\x1F\x7F]/u';

		$text = preg_replace($pattern, '', $text) ?? '';

		return mb_substr(trim($text), 0, $length);
	}

	/** A value safe to put in a mail header: no newlines, ever. */
	private function header(string $value): string
	{
		return str_replace(["\r", "\n"], '', $value);
	}

	private function sender(string $to): string
	{
		$at = strrpos($to, '@');

		return 'pluck@' . ($at === false ? 'localhost' : substr($to, $at + 1));
	}

	private function ip(): string
	{
		return is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';
	}

	/** @param array<string,string|int> $replacements */
}
