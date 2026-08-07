<?php
/**
 * Reactions, across every post.
 *
 * One list rather than a tab per post: moderation is a sweep through whatever
 * came in, not a per-post errand. The status buttons only mean anything when
 * moderation is switched on, and the notice says so rather than leaving someone
 * to wonder why approving changed nothing.
 *
 * @var list<array<string,mixed>> $reactions
 * @var ?\Pluck\Module\ReactionStatus $filter
 * @var list<\Pluck\Module\ReactionStatus> $statuses
 * @var array{moderate_reactions:bool} $settings
 * @var array<string,string> $postTitles
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
use Pluck\Support\Dates;
?>
<div class="head">
	<h1><?= $view->t('blog.title.reactions') ?></h1>
	<a class="btn-quiet" href="<?= e(Controller::url('module.blog.index')) ?>"><?= $view->t('blog.action.back_to_posts') ?></a>
</div>

<?php if (!$settings['moderate_reactions']): ?>
	<div class="card"><p class="muted"><?= $view->t('blog.help.moderation_is_off') ?></p></div>
<?php endif; ?>

<nav class="tabs">
	<a<?= $filter === null ? ' class="is-current"' : '' ?> href="<?= e(Controller::url('module.blog.reactions')) ?>"><?= $view->t('blog.filter.all') ?></a>
<?php foreach ($statuses as $status): ?>
	<a<?= $filter === $status ? ' class="is-current"' : '' ?>
	   href="<?= e(Controller::url('module.blog.reactions', ['status' => $status->value])) ?>"><?= $view->t($status->labelKey()) ?></a>
<?php endforeach; ?>
</nav>

<?php if ($reactions === []): ?>
	<div class="card"><p><?= $view->t('blog.empty.no_reactions') ?></p></div>
<?php else: ?>
	<ul class="rows">
<?php foreach ($reactions as $reaction): ?>
		<li class="row">
			<div class="row-main">
				<span class="row-title"><?= e((string) $reaction['name']) ?></span>
				<span class="row-meta">
					<?= $view->t($reaction['status']->labelKey()) ?>
					<?php if ((string) $reaction['posted_at'] !== ''): ?>
						· <?= e(Dates::long((string) $reaction['posted_at'], $locale ?? 'en')) ?>
					<?php endif; ?>
					· <?= e($postTitles[$reaction['post']] ?? (string) $reaction['post']) ?>
				</span>
				<div class="row-body"><?= $reaction['message'] ?></div>
			</div>

			<span class="row-actions">
<?php foreach ($statuses as $status): ?>
<?php if ($status !== $reaction['status']): ?>
				<form method="post" action="<?= e(Controller::url('module.blog.reaction.status')) ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="post" value="<?= e((string) $reaction['post']) ?>">
					<input type="hidden" name="id" value="<?= e(substr((string) $reaction['key'], strrpos((string) $reaction['key'], ':') + 1)) ?>">
					<input type="hidden" name="status" value="<?= e($status->value) ?>">
					<button class="btn-quiet" type="submit"><?= $view->t($status->labelKey()) ?></button>
				</form>
<?php endif; ?>
<?php endforeach; ?>
				<form method="post" action="<?= e(Controller::url('module.blog.reaction.delete')) ?>"
				      data-confirm="<?= $view->t('blog.confirm.delete_reaction') ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="post" value="<?= e((string) $reaction['post']) ?>">
					<input type="hidden" name="id" value="<?= e(substr((string) $reaction['key'], strrpos((string) $reaction['key'], ':') + 1)) ?>">
					<button class="btn-icon" type="submit" aria-label="<?= $view->t('blog.action.delete_reaction') ?>">×</button>
				</form>
			</span>
		</li>
<?php endforeach; ?>
	</ul>
<?php endif; ?>
