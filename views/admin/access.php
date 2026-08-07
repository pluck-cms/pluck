<?php
/**
 * The permission grid.
 *
 * Rows are permissions, columns are roles. A box that an owner has actually
 * decided about is marked, so an untouched grid and a grid someone filled in
 * identically do not look the same — otherwise nobody can tell whether a setting
 * is deliberate or simply the role's default showing through.
 *
 * Owner is not a column. An owner may do everything, and a row of ticked,
 * disabled boxes teaches people that some boxes lie.
 *
 * @var list<\Pluck\Model\Role> $roles
 * @var list<array{group:string,permission:string,label:string,cells:array<string,array{ticked:bool,explicit:bool}>}> $rows
 * @var list<string> $groups
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<div class="head">
	<h1><?= $view->t('acl.title.permissions') ?></h1>
</div>

<div class="card">
	<p class="muted"><?= $view->t('acl.help.intro') ?></p>
	<p class="muted"><?= $view->t('acl.help.owner_not_listed') ?></p>
</div>

<form method="post" action="<?= e(Controller::url('access.save')) ?>">
	<?= $view->csrfField() ?>

<?php foreach ($groups as $group): ?>
	<div class="card">
		<h2><?= $view->t($group) ?></h2>

		<table class="grid">
			<thead>
				<tr>
					<th scope="col"><?= $view->t('acl.column.permission') ?></th>
<?php foreach ($roles as $role): ?>
					<th scope="col"><?= e($role->label()) ?></th>
<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
<?php foreach ($rows as $row): ?>
<?php if ($row['group'] !== $group) { continue; } ?>
				<tr>
					<th scope="row">
						<?= $view->t($row['label']) ?>
						<span class="muted"><?= e($row['permission']) ?></span>
					</th>
<?php foreach ($roles as $role): $cell = $row['cells'][$role->value]; ?>
					<td>
						<label class="grid-box<?= $cell['explicit'] ? ' is-set' : '' ?>">
							<input type="checkbox"
							       name="permissions_<?= e($role->value) ?>[]"
							       value="<?= e($row['permission']) ?>"
							       <?= $cell['ticked'] ? 'checked' : '' ?>>
							<span class="visually-hidden">
								<?= $view->t('acl.label.role_may', ['role' => $role->label()]) ?>
							</span>
						</label>
					</td>
<?php endforeach; ?>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endforeach; ?>

	<button class="btn" type="submit"><?= $view->t('acl.action.save') ?></button>
</form>

<form class="card" method="post" action="<?= e(Controller::url('access.reset')) ?>"
      data-confirm="<?= $view->t('acl.confirm.reset') ?>">
	<?= $view->csrfField() ?>
	<h2><?= $view->t('acl.title.back_to_defaults') ?></h2>
	<p class="muted"><?= $view->t('acl.help.reset') ?></p>
	<button class="btn-quiet" type="submit"><?= $view->t('acl.action.reset') ?></button>
</form>
