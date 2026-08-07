<?php
/**
 * Sign-in. Rendered without the admin layout: there is no navigation to show and
 * nothing about the site should leak before the password is right.
 *
 * @var \Pluck\View\View $view
 * @var string $stage  'password' or 'totp'
 * @var string $error @var string $username @var string $next @var string $siteTitle
 */

use Pluck\Admin\Controller;
use Pluck\Bootstrap;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title>Sign in · <?= e($siteTitle) ?></title>
<link rel="stylesheet" href="assets/admin/pluck.css">
</head>
<body>
<div class="shell shell-narrow">
	<div class="wordmark"><b>pluck</b><span><?= e(Bootstrap::VERSION) ?></span></div>

	<div class="card">
		<?php if ($error !== ''): ?>
			<p class="notice notice-stop" role="alert"><?= e($error) ?></p>
		<?php endif; ?>

		<?php if ($stage === 'totp'): ?>
			<h1><?= $view->t('ui.signin.one_more_step') ?></h1>
			<p class="muted"><?= $view->t('ui.signin.enter_the_six_digit_code_from_your_authentic') ?></p>

			<form method="post" action="<?= e(Controller::url('signin.totp')) ?>">
				<?= $view->csrfField() ?>
				<input type="hidden" name="next" value="<?= e($next) ?>">
				<div class="field">
					<label for="code">Code</label>
					<input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
					       pattern="[0-9]*" maxlength="6" required autofocus>
				</div>
				<button type="submit"><?= $view->t('ui.signin.sign_in') ?></button>
			</form>

			<p class="muted spaced"><a href="<?= e(Controller::url('signout')) ?>"><?= $view->t('ui.signin.start_over') ?></a></p>
		<?php else: ?>
			<h1><?= $view->t('ui.signin.sign_in') ?></h1>

			<form method="post" action="<?= e(Controller::url('signin.submit')) ?>">
				<?= $view->csrfField() ?>
				<input type="hidden" name="next" value="<?= e($next) ?>">
				<div class="field">
					<label for="username"><?= $view->t('ui.signin.username') ?></label>
					<input id="username" name="username" type="text" autocomplete="username"
					       value="<?= e($username) ?>" required autofocus>
				</div>
				<div class="field">
					<label for="password"><?= $view->t('ui.signin.password') ?></label>
					<input id="password" name="password" type="password" autocomplete="current-password" required>
				</div>
				<button type="submit"><?= $view->t('ui.signin.sign_in') ?></button>
			</form>
		<?php endif; ?>
	</div>

	<p class="muted centered"><?= $view->t('ui.signin.managing') ?><?= e($siteTitle) ?></p>
</div>
</body>
</html>
