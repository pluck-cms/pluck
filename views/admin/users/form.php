<?php
/**
 * @var \Pluck\Model\User|null $user
 * @var array<string,string> $roles
 * @var bool $isNew
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<h1><?= $isNew ? $view->t('ui.users.form.add_someone') : 'Edit ' . e($user->username) ?></h1>

<form method="post" action="<?= e(Controller::url('user.save')) ?>">
	<?= $view->csrfField() ?>
	<input type="hidden" name="id" value="<?= e($isNew ? '' : $user->id) ?>">

	<div class="card">
		<div class="field">
			<label for="username"><?= $view->t('ui.users.form.username') ?></label>
			<span class="hint"><?= $view->t('ui.users.form.used_to_sign_in_letters_digits_dot_dash_or_u') ?></span>
			<input id="username" name="username" type="text" required minlength="3" maxlength="32"
			       autocomplete="off" value="<?= e($isNew ? '' : $user->username) ?>">
		</div>

		<div class="field">
			<label for="display_name"><?= $view->t('ui.users.form.name_shown_in_the_admin') ?></label>
			<input id="display_name" name="display_name" type="text" maxlength="80"
			       value="<?= e($isNew ? '' : $user->displayName) ?>">
		</div>

		<div class="field">
			<label for="email"><?= $view->t('ui.users.form.email') ?></label>
			<span class="hint"><?= $view->t('ui.users.form.where_update_notices_and_password_resets_go') ?></span>
			<input id="email" name="email" type="email" maxlength="190"
			       value="<?= e($isNew ? '' : $user->email) ?>">
		</div>

		<div class="field">
			<label for="password"><?= $isNew ? 'Password' : $view->t('ui.users.form.set_password') ?></label>
			<span class="hint"><?= $isNew ? 'At least 12 characters.' : 'Leave empty to keep the current one.' ?></span>
			<input id="password" name="password" type="password" autocomplete="new-password"
			       <?= $isNew ? 'required minlength="12"' : 'minlength="12"' ?>>
		</div>

		<div class="field">
			<label for="role">Role</label>
			<select id="role" name="role">
				<?php foreach ($roles as $value => $label): ?>
					<option value="<?= e($value) ?>"<?= !$isNew && $user->role->value === $value ? ' selected' : '' ?>>
						<?= e($label) ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php if ($isNew): ?>
			<label class="choice">
				<input type="checkbox" name="must_change" value="1" checked>
				<span><b><?= $view->t('ui.users.form.make_them_choose_their_own_password') ?></b><span class="muted"><?= $view->t('ui.users.form.they_will_be_asked_on_first_sign_in') ?></span></span>
			</label>
		<?php else: ?>
			<label class="choice">
				<input type="checkbox" name="active" value="1"<?= $user->active ? ' checked' : '' ?>>
				<span><b><?= $view->t('ui.users.form.account_is_active') ?></b><span class="muted"><?= $view->t('ui.users.form.switching_this_off_blocks_sign_in_without_de') ?></span></span>
			</label>
		<?php endif; ?>
	</div>

	<div class="actions">
		<button type="submit"><?= $isNew ? $view->t('ui.users.form.add_account') : $view->t('ui.users.form.save_account') ?></button>
		<a class="btn-quiet" href="<?= e(Controller::url('users')) ?>"><?= $view->t('ui.users.form.cancel') ?></a>
	</div>
</form>

<?php if (!$isNew): ?>
	<div class="card card-danger">
		<h2><?= $view->t('ui.users.form.remove_this_account') ?></h2>
		<p class="muted"><?= $view->t('ui.users.form.pages_they_wrote_stay_where_they_are') ?></p>
		<form method="post" action="<?= e(Controller::url('user.delete')) ?>"
		      data-confirm="Remove <?= e($user->username) ?>?">
			<?= $view->csrfField() ?>
			<input type="hidden" name="id" value="<?= e($user->id) ?>">
			<button class="btn-danger" type="submit"><?= $view->t('ui.users.form.remove_account') ?></button>
		</form>
	</div>
<?php endif; ?>
