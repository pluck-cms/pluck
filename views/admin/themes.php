<?php
/**
 * Appearance: which theme, and what it has been told.
 *
 * @var list<string> $themes
 * @var \Pluck\Theme\Theme $active
 * @var array<string,array{label:string,default:string,help:string}> $declared
 * @var array<string,string> $values
 * @var list<string> $problems
 * @var list<string> $media
 * @var string $logo
 * @var string $tagline
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<h1><?= $view->t('theme.title.appearance') ?></h1>

<?php if ($problems !== []): ?>
<div class="notice">
	<p><?= $view->t('theme.problems.heading') ?></p>
	<ul>
<?php foreach ($problems as $problem): ?>
		<li><?= e($problem) ?></li>
<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>

<form method="post" action="<?= e(Controller::url('theme.save')) ?>">
	<?= $view->csrfField() ?>

	<div class="field">
		<label for="theme"><?= $view->t('theme.label.theme') ?></label>
		<span class="hint"><?= $view->t('theme.help.theme') ?></span>
		<select id="theme" name="theme">
<?php foreach ($themes as $name): ?>
			<option value="<?= e($name) ?>"<?= $name === $active->name ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
		</select>
	</div>

	<div class="field">
		<label for="site_logo"><?= $view->t('settings.label.logo') ?></label>
		<span class="hint"><?= $view->t('settings.help.logo') ?></span>
		<select id="site_logo" name="site_logo">
			<option value=""><?= $view->t('settings.label.no_logo') ?></option>
<?php foreach ($media as $name): ?>
			<option value="<?= e($name) ?>"<?= $name === $logo ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
		</select>
	</div>

	<div class="field">
		<label for="site_tagline"><?= $view->t('settings.label.tagline') ?></label>
		<span class="hint"><?= $view->t('settings.help.tagline') ?></span>
		<input id="site_tagline" type="text" name="site_tagline" maxlength="120" value="<?= e($tagline) ?>">
	</div>

<?php if ($declared === []): ?>
	<p class="muted"><?= $view->t('theme.params.none') ?></p>
<?php else: ?>
	<h2><?= $view->t('theme.label.parameters') ?></h2>
	<p class="muted"><?= $view->t('theme.help.parameters') ?></p>

	<table class="params">
		<thead>
			<tr>
				<th scope="col"><?= $view->t('theme.column.parameter') ?></th>
				<th scope="col"><?= $view->t('theme.column.value') ?></th>
				<th scope="col"><span class="visually-hidden"><?= $view->t('theme.column.actions') ?></span></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ($declared as $name => $spec): ?>
			<tr>
				<th scope="row">
					<label for="param-<?= e($name) ?>"><?= e($spec['label']) ?></label>
					<code class="params__name"><?= e($name) ?></code>
<?php if ($spec['help'] !== ''): ?>
					<span class="hint"><?= e($spec['help']) ?></span>
<?php endif; ?>
				</th>
				<td>
					<input id="param-<?= e($name) ?>" name="param_<?= e($name) ?>" type="text"
					       value="<?= e($values[$name] ?? '') ?>" maxlength="500">
<?php if ($spec['default'] !== '' && ($values[$name] ?? '') !== $spec['default']): ?>
					<span class="hint"><?= $view->t('theme.help.default_is') ?> <?= e($spec['default']) ?></span>
<?php endif; ?>
				</td>
				<td class="params__action">
					<!--
						The reset is its own form, outside this one. A form inside a
						form is not nested HTML — the browser closes the first at the
						second, and everything after it stops being submitted.
					-->
					<button class="btn-quiet" type="submit" form="reset-<?= e($name) ?>"
					        name="name" value="<?= e($name) ?>"><?= $view->t('theme.action.reset') ?></button>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

	<div class="actions">
		<button type="submit"><?= $view->t('theme.action.save') ?></button>
		<a class="btn-quiet" href="<?= e(Controller::url('stylesheet')) ?>"><?= $view->t('theme.action.edit_stylesheet') ?></a>
	</div>
</form>

<?php /* The reset forms, after the main one and never inside it. */ ?>
<?php foreach ($declared as $name => $spec): ?>
<form id="reset-<?= e($name) ?>" method="post" action="<?= e(Controller::url('theme.reset')) ?>" hidden>
	<?= $view->csrfField() ?>
</form>
<?php endforeach; ?>
