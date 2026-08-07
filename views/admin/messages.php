<?php
/**
 * Messages from the contact form.
 *
 * Everything here is text a stranger typed, so everything here goes through e().
 *
 * @var list<array<string,mixed>> $messages
 * @var int $unread
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
use Pluck\Support\Dates;
?>
<div class="head">
	<h1><?= $view->t('messages.title.messages') ?><?= $unread > 0 ? ' (' . e((string) $unread) . ')' : '' ?></h1>
	<form method="post" action="<?= e(Controller::url('messages.tidy')) ?>">
		<?= $view->csrfField() ?>
		<button class="btn-quiet" type="submit"><?= $view->t('messages.action.tidy') ?></button>
	</form>
</div>

<?php if ($messages === []): ?>
	<div class="card"><p><?= $view->t('messages.empty') ?></p></div>
<?php else: ?>
<?php foreach ($messages as $message): ?>
	<div class="card<?= ($message['read'] ?? false) ? ' is-read' : '' ?>">
		<div class="head">
			<h2><?= e((string) ($message['subject'] ?? '')) !== '' ? e((string) $message['subject']) : $view->t('messages.no_subject') ?></h2>
			<span class="row-meta">
				<?= e(Dates::long((string) ($message['received_at'] ?? ''), $locale ?? 'en')) ?>
			</span>
		</div>

		<p class="row-meta">
			<?= e((string) ($message['name'] ?? '')) ?>
<?php if (($message['email'] ?? '') !== ''): ?>
			&middot; <a href="mailto:<?= e((string) $message['email']) ?>"><?= e((string) $message['email']) ?></a>
<?php endif; ?>
		</p>

		<p class="message-body"><?= nl2br(e((string) ($message['message'] ?? '')), false) ?></p>

		<span class="row-actions">
			<form method="post" action="<?= e(Controller::url('messages.read')) ?>">
				<?= $view->csrfField() ?>
				<input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
				<button class="btn-quiet" type="submit">
					<?= ($message['read'] ?? false) ? $view->t('messages.action.mark_unread') : $view->t('messages.action.mark_read') ?>
				</button>
			</form>

			<form method="post" action="<?= e(Controller::url('messages.delete')) ?>"
			      data-confirm="<?= e($view->t('messages.confirm.delete')) ?>">
				<?= $view->csrfField() ?>
				<input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
				<button class="btn-icon" type="submit" aria-label="<?= e($view->t('messages.action.delete')) ?>">×</button>
			</form>
		</span>
	</div>
<?php endforeach; ?>
<?php endif; ?>
