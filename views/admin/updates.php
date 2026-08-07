<?php
/**
 * Updates.
 *
 * There is no "install" button, and that is the design rather than a gap: see
 * the note on this screen, and Update\Updates for the reasoning. The archive is
 * fetched and its hash shown; a person puts it in place.
 *
 * @var string $current
 * @var ?\Pluck\Update\Release $release
 * @var bool $available
 * @var list<\Pluck\Update\Download> $downloads
 * @var int $lastChecked
 * @var bool $online
 * @var bool $enabled
 * @var string $error
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
use Pluck\Support\Dates;

$human = static function (int $bytes): string {
	foreach ([['MB', 1048576], ['kB', 1024]] as [$unit, $step]) {
		if ($bytes >= $step) {
			return round($bytes / $step, 1) . ' ' . $unit;
		}
	}

	return $bytes . ' B';
};
?>
<div class="head">
	<h1><?= $view->t('update.title.updates') ?></h1>
<?php if ($online && $enabled): ?>
	<a class="btn-quiet" href="<?= e(Controller::url('updates', ['check' => 1])) ?>"><?= $view->t('update.action.check_now') ?></a>
<?php endif; ?>
</div>

<div class="card">
	<p><?= e($view->t('update.label.you_are_running', ['version' => $current])) ?></p>

<?php if (!$enabled): ?>
	<p class="muted"><?= $view->t('update.help.checking_is_off') ?></p>
<?php elseif (!$online): ?>
	<p class="muted"><?= $view->t('update.help.no_internet') ?></p>
<?php elseif ($error !== ''): ?>
	<p class="muted"><?= e($view->t('update.help.check_failed', ['why' => $error])) ?></p>
<?php elseif ($release === null): ?>
	<p class="muted"><?= $view->t('update.help.nothing_known_yet') ?></p>
<?php elseif (!$available): ?>
	<p class="muted"><?= $view->t('update.help.up_to_date') ?></p>
<?php endif; ?>

<?php if ($lastChecked > 0): ?>
	<p class="muted"><?= e($view->t('update.label.last_checked', ['when' => Dates::long($lastChecked, $locale ?? 'en')])) ?></p>
<?php endif; ?>
</div>

<?php if ($release !== null && $available): ?>
<div class="card">
	<h2><?= e($view->t('update.title.new_version', ['version' => $release->normalised()])) ?></h2>

<?php if ($release->prerelease): ?>
	<p class="muted"><b><?= $view->t('update.label.prerelease') ?></b></p>
<?php endif; ?>

<?php if ($release->notes !== ''): ?>
	<pre class="release-notes"><?= e(mb_substr($release->notes, 0, 2000)) ?></pre>
<?php endif; ?>

<?php if ($release->url !== ''): ?>
	<p><a href="<?= e($release->url) ?>" target="_blank" rel="noopener noreferrer"><?= $view->t('update.action.read_on_github') ?></a></p>
<?php endif; ?>

	<form method="post" action="<?= e(Controller::url('update.download')) ?>">
		<?= $view->csrfField() ?>
		<button class="btn" type="submit"><?= $view->t('update.action.download_it') ?></button>
		<span class="muted"><?= $view->t('update.help.download_takes_backup') ?></span>
	</form>
</div>
<?php endif; ?>

<div class="card">
	<h2><?= $view->t('update.title.how_to_install') ?></h2>
	<p><?= $view->t('update.help.button_or_by_hand') ?></p>
	<p class="muted"><?= $view->t('update.help.why_not_automatic') ?></p>
	<ol>
		<li><?= $view->t('update.step.download') ?></li>
		<li><?= $view->t('update.step.unpack') ?></li>
		<li><?= $view->t('update.step.upload') ?></li>
		<li><?= $view->t('update.step.visit') ?></li>
	</ol>
</div>

<?php if ($downloads !== []): ?>
<div class="card">
	<h2><?= $view->t('update.title.downloaded') ?></h2>
	<ul class="rows">
<?php foreach ($downloads as $download): ?>
		<li class="row">
			<div class="row-main">
				<span class="row-title"><?= e($download->version()) ?></span>
				<span class="row-meta">
					<?= e($human($download->bytes)) ?> · <?= e(Dates::long($download->downloadedAt, $locale ?? 'en')) ?>
					<span class="row-hash" title="<?= e($view->t('update.help.hash')) ?>">sha256 <?= e($download->shortHash()) ?>…</span>
				</span>
			</div>
			<span class="row-actions">
				<form method="post" action="<?= e(Controller::url('update.apply')) ?>"
				      data-confirm="<?= e($view->t('update.confirm.apply', ['version' => $download->version()])) ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="name" value="<?= e($download->name) ?>">
					<button class="btn" type="submit"><?= $view->t('update.action.install_it') ?></button>
				</form>
				<a class="btn-quiet" href="<?= e(Controller::url('update.fetch', ['name' => $download->name])) ?>"><?= $view->t('update.action.save_to_my_computer') ?></a>
				<form method="post" action="<?= e(Controller::url('update.delete')) ?>"
				      data-confirm="<?= e($view->t('update.confirm.delete')) ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="name" value="<?= e($download->name) ?>">
					<button class="btn-icon" type="submit" aria-label="<?= e($view->t('update.action.delete')) ?>">×</button>
				</form>
			</span>
		</li>
<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>
