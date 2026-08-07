<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Form\Guard;

/**
 * What came in through the contact form.
 *
 * Kept on the site as well as e-mailed, because mail() on shared hosting fails
 * quietly and often. A contact form that loses a message because the mail was
 * silently filtered is worse than one that never sent mail — so this screen is
 * the copy that does not depend on anything working.
 */
final class MessagesController extends Controller
{
	public function index(): never
	{
		$messages = [];

		foreach ($this->c->storage->listModuleData('contact-form', 'message:') as $key => $message) {
			if (is_array($message)) {
				$messages[] = ['id' => substr($key, 8)] + $message;
			}
		}

		usort($messages, static fn (array $a, array $b): int => ($b['received_at'] ?? '') <=> ($a['received_at'] ?? ''));

		$this->render('admin/messages', [
			'title' => $this->t('messages.title.messages'),
			'messages' => $messages,
			'unread' => count(array_filter($messages, static fn (array $m): bool => !($m['read'] ?? false))),
		]);
	}

	public function markRead(): never
	{
		$id = $this->id();
		$message = $this->c->storage->getModuleData('contact-form', 'message:' . $id);

		if (is_array($message)) {
			$message['read'] = !($message['read'] ?? false);
			$this->c->storage->setModuleData('contact-form', 'message:' . $id, $message);
		}

		$this->back('messages');
	}

	public function delete(): never
	{
		$this->c->storage->deleteModuleData('contact-form', 'message:' . $this->id());

		$this->c->flash->ok($this->t('messages.flash.deleted'));
		$this->back('messages');
	}

	/**
	 * Throw away rate-limit records that have expired.
	 *
	 * Here rather than on every submission: a public form should not be the thing
	 * that decides to walk the whole store.
	 */
	public function tidy(): never
	{
		$guard = new Guard($this->c->storage, $this->c->app->session(), null);

		$this->c->flash->ok($this->t('messages.flash.tidied', ['count' => $guard->forget()]));
		$this->back('messages');
	}

	/** A key rebuilt from what arrived, never used as given. */
	private function id(): string
	{
		return preg_replace('/[^0-9a-f-]/', '', $this->c->request->post('id', '')) ?? '';
	}
}
