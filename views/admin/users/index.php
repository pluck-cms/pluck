<?php
/**
 * @var list<\Pluck\Model\User> $users
 * @var bool $canCreate
 * @var \Pluck\Model\User|null $me
 */

use Pluck\Admin\Controller;
?>
<div class="head">
	<h1><?= $view->t('nav.people') ?></h1>
	<?php if ($canCreate): ?>
		<a class="btn" href="<?= e(Controller::url('user.new')) ?>"><?= $view->t('ui.users.index.add_someone') ?></a>
	<?php endif; ?>
</div>

<ul class="rows">
	<?php foreach ($users as $user): ?>
		<li class="row">
			<a class="row-main" href="<?= e(Controller::url('user.edit', ['id' => $user->id])) ?>">
				<span class="row-title">
					<?= e($user->displayName) ?>
					<?php if ($me !== null && $me->id === $user->id): ?><span class="tag">you</span><?php endif; ?>
					<?php if (!$user->active): ?><span class="tag tag-off">switched off</span><?php endif; ?>
					<?php if ($user->totpSecret !== null && $user->totpSecret !== ''): ?><span class="tag">2FA</span><?php endif; ?>
				</span>
				<span class="row-meta">
					<?= e($user->username) ?> · <?= e($user->role->label()) ?>
					<?php if ($user->lastLoginAt !== null): ?>
						· last in <?= e(substr($user->lastLoginAt, 0, 10)) ?>
					<?php else: ?>
						· never signed in
					<?php endif; ?>
				</span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>

<div class="card">
	<h2><?= $view->t('ui.users.index.what_the_roles_mean') ?></h2>
	<ul class="checks">
		<li class="pass"><span class="glyph">·</span><span><span class="name"><?= $view->t('ui.users.index.owner') ?></span> <span class="detail"><?= $view->t('ui.users.index.everything_including_updates_and_other_owner') ?></span></span></li>
		<li class="pass"><span class="glyph">·</span><span><span class="name"><?= $view->t('ui.users.index.administrator') ?></span> <span class="detail"><?= $view->t('ui.users.index.everything_except_owner_accounts_and_removin') ?></span></span></li>
		<li class="pass"><span class="glyph">·</span><span><span class="name"><?= $view->t('ui.users.index.editor') ?></span> <span class="detail"><?= $view->t('ui.users.index.all_content_and_module_settings_no_site_sett') ?></span></span></li>
		<li class="pass"><span class="glyph">·</span><span><span class="name"><?= $view->t('ui.users.index.author') ?></span> <span class="detail"><?= $view->t('ui.users.index.their_own_pages_only') ?></span></span></li>
	</ul>
</div>
