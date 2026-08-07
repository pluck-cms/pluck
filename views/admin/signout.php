<?php
/**
 * Signing out is a state change, so it needs a POST with a token like every other
 * one. A bare link to admin.php?p=signout therefore cannot do the work itself —
 * it asks here instead. That closes the one-click sign-out an attacker could
 * trigger by getting a signed-in admin to follow a link.
 *
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<h1><?= $view->t('nav.sign_out') ?></h1>

<div class="card">
	<p><?= $view->t('ui.signout.you_are_still_signed_in_confirm_and_this_ses') ?></p>

	<form method="post" action="<?= e(Controller::url('signout')) ?>">
		<?= $view->csrfField() ?>
		<button type="submit"><?= $view->t('nav.sign_out') ?></button>
	</form>

	<p class="muted">
		<a href="<?= e(Controller::url('dashboard')) ?>"><?= $view->t('nav.stay_signed_in') ?></a>
	</p>
</div>
