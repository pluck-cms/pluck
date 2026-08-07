<?php
/**
 * @var list<array{name:string,size:int,modified:int,isImage:bool}> $files
 * @var bool $canUpload @var int $maxBytes @var string $accept
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;

$human = static fn (int $bytes): string => $bytes >= 1048576
	? round($bytes / 1048576, 1) . ' MB'
	: max(1, (int) round($bytes / 1024)) . ' kB';
?>
<h1><?= $view->t('nav.media') ?></h1>
<p class="muted"><?= $view->t('media.file_count', ['count' => count($files)], count($files)) ?></p>

<?php if ($canUpload): ?>
	<form class="card" method="post" action="<?= e(Controller::url('media.upload')) ?>" enctype="multipart/form-data">
		<?= $view->csrfField() ?>
		<div class="field">
			<label for="file"><?= $view->t('ui.media.index.add_a_file') ?></label>
			<span class="hint"><?= $view->t('ui.media.index.up_to') ?><?= e($human($maxBytes)) ?>. Images, PDF, audio, video, zip and Office documents.</span>
			<input id="file" name="file" type="file" accept="<?= e($accept) ?>" required>
		</div>
		<button type="submit"><?= $view->t('ui.media.index.upload') ?></button>
	</form>
<?php endif; ?>

<?php if (($duplicate ?? '') !== ''): ?>
	<div class="card">
		<h2><?= $view->t('media.title.already_here') ?></h2>
		<p><?= $view->t('media.help.already_here', ['name' => $duplicate]) ?></p>
		<form method="post" action="<?= e(Controller::url('media.keep-both')) ?>">
			<?= $view->csrfField() ?>
			<input type="hidden" name="name" value="<?= e($duplicate) ?>">
			<button class="btn-quiet" type="submit"><?= $view->t('media.action.keep_both') ?></button>
		</form>
	</div>
<?php endif; ?>

<?php if ($files === []): ?>
	<div class="card"><p><?= $view->t('ui.media.index.nothing_here_yet') ?></p></div>
<?php else: ?>
	<ul class="rows">
		<?php foreach ($files as $file): ?>
			<li class="row">
				<a class="row-main" href="media/<?= e(rawurlencode($file['name'])) ?>" target="_blank" rel="noopener">
					<span class="row-title"><?= e($file['name']) ?></span>
					<span class="row-meta">
						<?= e($human($file['size'])) ?> ·
						<?= e(date('j M Y', $file['modified'])) ?>
						<?php if ($file['isImage']): ?> · image<?php endif; ?>
<?php if (($file['hash'] ?? '') !== ''): ?>
						<span class="row-hash" title="<?= e($view->t('media.help.hash')) ?>">sha256 <?= e(substr($file['hash'], 0, 16)) ?>…</span>
<?php endif; ?>
					</span>
				</a>
				<span class="row-actions">
					<form method="post" action="<?= e(Controller::url('media.delete')) ?>"
					      data-confirm="Delete <?= e($file['name']) ?>?">
						<?= $view->csrfField() ?>
						<input type="hidden" name="name" value="<?= e($file['name']) ?>">
						<button class="btn-icon btn-icon-danger" type="submit" aria-label="Delete <?= e($file['name']) ?>">×</button>
					</form>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="muted"><?= $view->t('ui.media.index.use_the_address') ?><code>media/filename</code> in a page to link or embed a file.</p>
<?php endif; ?>
