<?php
/**
 * The blog post list.
 *
 * Drafts sort to the top and are marked, because a draft is the thing with
 * something outstanding. Everything else is newest first, which is the order the
 * site shows them in.
 *
 * @var list<array<string,mixed>> $posts
 * @var array<string,string> $categories
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $pendingCount
 * @var array{posts_per_page:int,allow_reactions:bool,moderate_reactions:bool} $settings
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
use Pluck\Support\Dates;
?>
<div class="head">
	<h1><?= $view->t('blog.title.posts') ?></h1>
	<a class="btn" href="<?= e(Controller::url('module.blog.new')) ?>"><?= $view->t('blog.action.new_post') ?></a>
</div>

<nav class="tabs">
	<a class="is-current" href="<?= e(Controller::url('module.blog.index')) ?>"><?= $view->t('blog.title.posts') ?></a>
	<a href="<?= e(Controller::url('module.blog.categories')) ?>"><?= $view->t('blog.title.categories') ?></a>
	<a href="<?= e(Controller::url('module.blog.reactions')) ?>">
		<?= $view->t('blog.title.reactions') ?><?php if ($pendingCount > 0): ?> (<?= e((string) $pendingCount) ?>)<?php endif; ?>
	</a>
</nav>

<?php if ($posts === []): ?>
	<div class="card"><p><?= $view->t('blog.empty.no_posts_yet') ?></p></div>
<?php else: ?>
	<ul class="rows">
		<?php foreach ($posts as $post): ?>
			<li class="row">
				<a class="row-main" href="<?= e(Controller::url('module.blog.edit', ['slug' => $post['slug']])) ?>">
					<span class="row-title"><?= e((string) $post['title']) ?></span>
					<span class="row-meta">
						<?php if (!$post['published']): ?>
							<b><?= $view->t('blog.label.draft') ?></b> ·
						<?php elseif (($post['published_at'] ?? null) !== null): ?>
							<?= e(Dates::long((string) $post['published_at'], $locale ?? 'en')) ?> ·
						<?php endif; ?>
						/blog/<?= e((string) $post['slug']) ?>
						<?php if (($post['category'] ?? '') !== ''): ?>
							· <?= e($categories[$post['category']] ?? (string) $post['category']) ?>
						<?php endif; ?>
						<?php if ($post['reactions'] > 0): ?>
							· <?= $view->t('blog.reactions', ['count' => $post['reactions']], (int) $post['reactions']) ?>
						<?php endif; ?>
					</span>
				</a>

				<span class="row-actions">
					<form method="post" action="<?= e(Controller::url('module.blog.delete')) ?>"
					      data-confirm="<?= $view->t('blog.confirm.delete_post', ['title' => (string) $post['title']]) ?>">
						<?= $view->csrfField() ?>
						<input type="hidden" name="slug" value="<?= e((string) $post['slug']) ?>">
						<button class="btn-icon" type="submit"
						        aria-label="<?= $view->t('blog.action.delete_named', ['title' => (string) $post['title']]) ?>">×</button>
					</form>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php if ($pages > 1): ?>
		<nav class="pager">
			<?php if ($page > 1): ?>
				<a href="<?= e(Controller::url('module.blog.index', ['p' => $page - 1])) ?>"><?= $view->t('blog.action.previous') ?></a>
			<?php endif; ?>
			<span><?= $view->t('blog.page_of', ['current' => $page, 'total' => $pages]) ?></span>
			<?php if ($page < $pages): ?>
				<a href="<?= e(Controller::url('module.blog.index', ['p' => $page + 1])) ?>"><?= $view->t('blog.action.next') ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
<?php endif; ?>

<form class="card" method="post" action="<?= e(Controller::url('module.blog.settings')) ?>">
	<?= $view->csrfField() ?>
	<h2><?= $view->t('blog.title.settings') ?></h2>

	<label class="field">
		<span><?= $view->t('blog.label.posts_per_page') ?></span>
		<input type="number" name="posts_per_page" min="1" max="100" value="<?= e((string) $settings['posts_per_page']) ?>">
	</label>

	<label class="field">
		<span><?= $view->t('blog.label.truncate_posts') ?></span>
		<input type="number" name="truncate_posts" min="0" max="20000" value="<?= e((string) $settings['truncate_posts']) ?>">
		<span class="muted"><?= $view->t('blog.help.truncate_posts') ?></span>
	</label>

	<label class="choice">
		<input type="checkbox" name="reverse_posts" value="1"<?= $settings['reverse_posts'] ? ' checked' : '' ?>>
		<span><b><?= $view->t('blog.label.reverse_posts') ?></b><span class="muted"><?= $view->t('blog.help.reverse_posts') ?></span></span>
	</label>

	<label class="field">
		<span><?= $view->t('blog.label.post_date') ?></span>
		<input type="text" name="post_date" maxlength="40" value="<?= e((string) $settings['post_date']) ?>"
		       placeholder="<?= $view->t('blog.help.date_follows_language') ?>">
		<span class="muted"><?= $view->t('blog.help.post_date') ?></span>
	</label>

	<label class="field">
		<span><?= $view->t('blog.label.post_time') ?></span>
		<input type="text" name="post_time" maxlength="40" value="<?= e((string) $settings['post_time']) ?>"
		       placeholder="<?= $view->t('blog.help.time_hidden') ?>">
		<span class="muted"><?= $view->t('blog.help.post_time') ?></span>
	</label>

	<label class="choice">
		<input type="checkbox" name="allow_reactions" value="1"<?= $settings['allow_reactions'] ? ' checked' : '' ?>>
		<span><b><?= $view->t('blog.label.allow_reactions') ?></b><span class="muted"><?= $view->t('blog.help.allow_reactions') ?></span></span>
	</label>

	<label class="choice">
		<input type="checkbox" name="moderate_reactions" value="1"<?= $settings['moderate_reactions'] ? ' checked' : '' ?>>
		<span><b><?= $view->t('blog.label.moderate_reactions') ?></b><span class="muted"><?= $view->t('blog.help.moderate_reactions') ?></span></span>
	</label>

	<button class="btn" type="submit"><?= $view->t('blog.action.save_settings') ?></button>
</form>
