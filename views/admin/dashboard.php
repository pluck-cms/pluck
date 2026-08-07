<?php
/**
 * @var list<\Pluck\Model\Page> $recent
 * @var int $pageCount @var int $hiddenCount @var int $userCount
 * @var bool $canSeeUsers @var bool $canSeeSettings @var bool $canCreatePages
 * @var string $storageName @var bool $twoFactorOn @var bool $searchEnabled
 */

use Pluck\Admin\Controller;
?>
<h1><?= $view->t('nav.overview') ?></h1>

<div class="card">
	<h2><?= $view->t('ui.dashboard.where_you_left_off') ?></h2>
	<?php if ($recent === []): ?>
		<?php if ($canCreatePages): ?>
			<p><?= $view->t('ui.dashboard.no_pages_yet') ?><a href="<?= e(Controller::url('page.new')) ?>"><?= $view->t('ui.dashboard.write_the_first_one') ?></a></p>
		<?php else: ?>
			<p><?= $view->t('ui.dashboard.no_pages_yet') ?></p>
		<?php endif; ?>
	<?php else: ?>
		<ul class="rows">
			<?php foreach ($recent as $page): ?>
				<li class="row">
					<a class="row-main" href="<?= e(Controller::url('page.edit', ['path' => $page->path])) ?>">
						<span class="row-title"><?= e($page->title) ?></span>
						<span class="row-meta">
							/<?= e($page->path) ?>
							<?php if ($page->hidden): ?> · hidden<?php endif; ?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>

<div class="card">
	<h2><?= $view->t('ui.dashboard.this_site') ?></h2>
	<ul class="checks">
		<li class="pass"><span class="glyph">·</span><span><span class="name"><?= $view->t('page.count', ['count' => $pageCount], $pageCount) ?></span>
			<span class="detail"><?= $view->t('page.hidden_count', ['count' => $hiddenCount], $hiddenCount) ?></span></span></li>
		<?php if ($canSeeUsers): ?>
			<li class="pass"><span class="glyph">·</span><span><span class="name"><?= $view->t('user.count', ['count' => $userCount], $userCount) ?></span>
				<span class="detail"><a href="<?= e(Controller::url('users')) ?>"><?= $view->t('ui.dashboard.manage_people') ?></a></span></span></li>
		<?php endif; ?>
		<li class="pass"><span class="glyph">·</span><span><span class="name"><?= $view->t('ui.dashboard.storage') ?> <?= e($storageName) ?></span>
			<span class="detail"><?= $view->t('ui.dashboard.chosen_at_install_time') ?></span></span></li>
		<li class="<?= $twoFactorOn ? 'pass' : 'note' ?>"><span class="glyph"><?= $twoFactorOn ? '+' : '!' ?></span><span>
			<span class="name"><?= $view->t('ui.dashboard.two_factor') ?> <?= $twoFactorOn ? $view->t('ui.dashboard.on') : $view->t('ui.dashboard.off') ?></span>
			<span class="detail"><a href="<?= e(Controller::url('account')) ?>"><?= $twoFactorOn ? $view->t('ui.dashboard.your_account') : $view->t('ui.dashboard.turn_it_on') ?></a></span></span></li>
		<li class="<?= $searchEnabled ? 'pass' : 'note' ?>"><span class="glyph">·</span><span>
			<span class="name"><?= $view->t('ui.dashboard.site_search') ?> <?= $searchEnabled ? $view->t('ui.dashboard.on') : $view->t('ui.dashboard.off') ?></span>
			<span class="detail"><?php if ($canSeeSettings): ?><a href="<?= e(Controller::url('settings')) ?>"><?= $view->t('ui.dashboard.change_it_in_settings') ?></a><?php else: ?><?= $view->t('ui.dashboard.an_administrator_switches_this_on') ?><?php endif; ?></span></span></li>
	</ul>
</div>
