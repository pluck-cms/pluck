<?php
/**
 * The current theme's stylesheet.
 *
 * @var string $theme
 * @var string $css
 * @var bool $exists
 * @var bool $writable
 * @var bool $hasPrevious
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<div class="head">
	<h1><?= $view->t('stylesheet.title.stylesheet') ?></h1>
	<span class="row-meta"><?= e($view->t('stylesheet.label.for_theme', ['theme' => $theme])) ?></span>
</div>

<div class="card">
	<p class="muted"><?= $view->t('stylesheet.help.what_this_is') ?></p>
<?php if (!$writable): ?>
	<p class="form-error"><?= $view->t('stylesheet.help.not_writable') ?></p>
<?php elseif (!$exists): ?>
	<p class="muted"><?= $view->t('stylesheet.help.will_be_created') ?></p>
<?php endif; ?>
</div>

<form method="post" action="<?= e(Controller::url('stylesheet.save')) ?>">
	<?= $view->csrfField() ?>
	<label class="field">
		<span class="visually-hidden"><?= $view->t('stylesheet.title.stylesheet') ?></span>
		<textarea name="css" id="css" rows="28" spellcheck="false" autocapitalize="off"
		          autocomplete="off" wrap="off"<?= $writable ? '' : ' readonly' ?>><?= e($css) ?></textarea>
	</label>

<?php if ($writable): ?>
	<button class="btn" type="submit"><?= $view->t('stylesheet.action.save') ?></button>
<?php endif; ?>
</form>

<?php if ($hasPrevious && $writable): ?>
<form class="card" method="post" action="<?= e(Controller::url('stylesheet.undo')) ?>"
      data-confirm="<?= e($view->t('stylesheet.confirm.undo')) ?>">
	<?= $view->csrfField() ?>
	<h2><?= $view->t('stylesheet.title.undo') ?></h2>
	<p class="muted"><?= $view->t('stylesheet.help.undo') ?></p>
	<button class="btn-quiet" type="submit"><?= $view->t('stylesheet.action.undo') ?></button>
</form>
<?php endif; ?>
