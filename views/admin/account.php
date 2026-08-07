<?php
/**
 * @var \Pluck\Model\User $user
 * @var string|null $pendingSecret
 * @var string|null $pendingUri
 * @var list<string> $languages
 * @var string $siteLanguage
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
use Pluck\Auth\Totp;

$twoFactorOn = $user->totpSecret !== null && $user->totpSecret !== '';
?>
<h1><?= $view->t('ui.account.your_account') ?></h1>

<?php if ($user->mustChangePassword): ?>
	<p class="notice notice-warn"><?= $view->t('ui.account.choose_your_own_password_before_you_carry_on') ?></p>
<?php endif; ?>

<form class="card" method="post" action="<?= e(Controller::url('account.profile')) ?>">
	<?= $view->csrfField() ?>
	<h2><?= $view->t('ui.account.who_you_are') ?></h2>

	<div class="field">
		<label><?= $view->t('ui.account.username') ?></label>
		<p class="muted"><code><?= e($user->username) ?></code> · <?= e($user->role->label()) ?></p>
	</div>

	<div class="field">
		<label for="display_name"><?= $view->t('ui.account.name_shown_in_the_admin') ?></label>
		<input id="display_name" name="display_name" type="text" maxlength="80" value="<?= e($user->displayName) ?>">
	</div>

	<?php if (count($languages) > 1): ?>
	<div class="field">
		<label for="language"><?= $view->t('account.language') ?></label>
		<span class="hint"><?= $view->t('account.language_hint') ?></span>
		<select id="language" name="language">
			<option value=""<?= $user->language === null ? ' selected' : '' ?>>
				<?= $view->t('account.language_follow_site') ?> (<?= e($siteLanguage) ?>)
			</option>
<?php foreach ($languages as $code): ?>
			<option value="<?= e($code) ?>"<?= $user->language === $code ? ' selected' : '' ?>><?= e($code) ?></option>
<?php endforeach; ?>
		</select>
	</div>
	<?php endif; ?>

	<div class="field">
		<label for="email"><?= $view->t('ui.account.email') ?></label>
		<input id="email" name="email" type="email" maxlength="190" value="<?= e($user->email) ?>">
	</div>

	<button type="submit">Save</button>
</form>

<form class="card" method="post" action="<?= e(Controller::url('account.password')) ?>">
	<?= $view->csrfField() ?>
	<h2><?= $view->t('ui.account.password') ?></h2>

	<div class="field">
		<label for="current_password"><?= $view->t('ui.account.current_password') ?></label>
		<input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
	</div>

	<div class="field">
		<label for="new_password"><?= $view->t('ui.account.new_password') ?></label>
		<span class="hint">At least 12 characters. A short sentence beats a short scramble.</span>
		<input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="12" required>
	</div>

	<div class="field">
		<label for="confirm_password"><?= $view->t('ui.account.new_password_again') ?></label>
		<input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="12" required>
	</div>

	<button type="submit"><?= $view->t('ui.account.change_password') ?></button>
</form>

<div class="card">
	<h2><?= $view->t('ui.account.two_factor_sign_in') ?></h2>

	<?php if ($twoFactorOn): ?>
		<p><?= $view->t('ui.account.on_you_are_asked_for_a_code_from_your_authen') ?></p>

		<form method="post" action="<?= e(Controller::url('account.2fa.off')) ?>"
		      data-confirm="<?= e($view->t('ui.account.switch_two_factor_off')) ?>">
			<?= $view->csrfField() ?>
			<div class="field">
				<label for="off_password"><?= $view->t('ui.account.your_password') ?></label>
				<input id="off_password" name="current_password" type="password" autocomplete="current-password" required>
			</div>
			<button class="btn-danger" type="submit"><?= $view->t('ui.account.switch_off') ?></button>
		</form>

	<?php elseif ($pendingSecret !== null): ?>
		<p><?= $view->t('ui.account.add_this_key_to_your_authenticator_app_then_') ?></p>

		<p class="secret"><code><?= e(Totp::readable($pendingSecret)) ?></code></p>
		<p class="muted"><a href="<?= e($pendingUri ?? '') ?>"><?= $view->t('ui.account.open_in_an_authenticator_app') ?></a> — or type the key in by hand.</p>

		<form method="post" action="<?= e(Controller::url('account.2fa.confirm')) ?>">
			<?= $view->csrfField() ?>
			<div class="field">
				<label for="code"><?= $view->t('ui.account.code_from_the_app') ?></label>
				<input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*"
				       maxlength="6" autocomplete="one-time-code" required>
			</div>
			<button type="submit"><?= $view->t('ui.account.finish_setup') ?></button>
		</form>

	<?php else: ?>
		<p><?= $view->t('ui.account.off_turning_it_on_means_a_stolen_password_is') ?></p>

		<form method="post" action="<?= e(Controller::url('account.2fa.start')) ?>">
			<?= $view->csrfField() ?>
			<button type="submit"><?= $view->t('ui.account.set_up_two_factor') ?></button>
		</form>
	<?php endif; ?>
</div>
