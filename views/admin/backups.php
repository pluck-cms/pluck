<?php
/**
 * Backups.
 *
 * The restore form asks for a typed word rather than offering a second button.
 * Restoring is the only thing on this screen that destroys current work, and a
 * person who has typed the word has read the sentence above it.
 *
 * @var list<\Pluck\Backup\Backup> $backups
 * @var int $keep
 * @var int $intervalDays
 * @var bool $compressed
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
use Pluck\Support\Dates;

$human = static function (int $bytes): string {
	foreach ([['GB', 1073741824], ['MB', 1048576], ['kB', 1024]] as [$unit, $step]) {
		if ($bytes >= $step) {
			return round($bytes / $step, 1) . ' ' . $unit;
		}
	}

	return $bytes . ' B';
};
?>
<div class="head">
	<h1><?= $view->t('backup.title.backups') ?></h1>
	<form method="post" action="<?= e(Controller::url('backup.create')) ?>">
		<?= $view->csrfField() ?>
		<button class="btn" type="submit"><?= $view->t('backup.action.make_one') ?></button>
	</form>
</div>

<div class="card">
	<p class="muted"><?= $view->t('backup.help.what_is_in_one') ?></p>
	<p class="muted"><?= $view->t('backup.help.where_they_live') ?></p>
<?php if (!$compressed): ?>
	<p class="muted"><?= $view->t('backup.help.no_zlib') ?></p>
<?php endif; ?>
</div>

<?php if ($backups === []): ?>
	<div class="card"><p><?= $view->t('backup.empty.none_yet') ?></p></div>
<?php else: ?>
	<ul class="rows">
<?php foreach ($backups as $backup): ?>
		<li class="row">
			<div class="row-main">
				<span class="row-title"><?= e(Dates::long($backup->createdAt, $locale ?? 'en')) ?></span>
				<span class="row-meta">
					<?= e($human($backup->bytes)) ?>
<?php if ($backup->isReadable()): ?>
					· <?= e($view->t('backup.reason.' . ($backup->reason() === '' ? 'manual' : str_replace(' ', '_', $backup->reason())))) ?>
					· <?= e($view->t('backup.label.file_count', ['count' => $backup->fileCount()], $backup->fileCount())) ?>
<?php else: ?>
					· <b><?= $view->t('backup.label.unreadable') ?></b>
<?php endif; ?>
					<span class="row-hash"><?= e($backup->name) ?></span>
				</span>
			</div>

			<span class="row-actions">
				<a class="btn-quiet" href="<?= e(Controller::url('backup.download', ['name' => $backup->name])) ?>"><?= $view->t('backup.action.download') ?></a>

				<form method="post" action="<?= e(Controller::url('backup.delete')) ?>"
				      data-confirm="<?= e($view->t('backup.confirm.delete')) ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="name" value="<?= e($backup->name) ?>">
					<button class="btn-icon" type="submit" aria-label="<?= e($view->t('backup.action.delete')) ?>">×</button>
				</form>
			</span>

<?php if ($backup->isReadable()): ?>
			<details class="row-more">
				<summary><?= $view->t('backup.action.restore') ?></summary>
				<form method="post" action="<?= e(Controller::url('backup.restore')) ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="name" value="<?= e($backup->name) ?>">
					<p><?= $view->t('backup.help.restore_does') ?></p>
					<label class="field">
						<span><?= $view->t('backup.label.type_to_confirm', ['word' => $view->t('backup.confirm_word')]) ?></span>
						<input type="text" name="confirm" autocomplete="off" required>
					</label>
					<button class="btn-quiet" type="submit"><?= $view->t('backup.action.restore_now') ?></button>
				</form>
			</details>
<?php endif; ?>
		</li>
<?php endforeach; ?>
	</ul>
<?php endif; ?>

<form class="card" method="post" action="<?= e(Controller::url('backup.settings')) ?>">
	<?= $view->csrfField() ?>
	<h2><?= $view->t('backup.title.settings') ?></h2>

	<label class="field">
		<span><?= $view->t('backup.label.keep') ?></span>
		<input type="number" name="backup_keep" min="1" max="50" value="<?= e((string) $keep) ?>">
		<span class="muted"><?= $view->t('backup.help.keep') ?></span>
	</label>

	<label class="field">
		<span><?= $view->t('backup.label.interval') ?></span>
		<input type="number" name="backup_interval_days" min="0" max="365" value="<?= e((string) $intervalDays) ?>">
		<span class="muted"><?= $view->t('backup.help.interval') ?></span>
	</label>

	<button class="btn" type="submit"><?= $view->t('backup.action.save_settings') ?></button>
</form>
