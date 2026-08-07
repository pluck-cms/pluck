<?php
/**
 * Categories.
 *
 * Deleting one unfiles its posts rather than deleting them, and the count says
 * how many that will be before you press it. Losing a year of writing because a
 * category was tidied up is not a trade anyone would choose.
 *
 * @var array<string,array{title:string,posts:int}> $categories
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<div class="head">
	<h1><?= $view->t('blog.title.categories') ?></h1>
	<a class="btn-quiet" href="<?= e(Controller::url('module.blog.index')) ?>"><?= $view->t('blog.action.back_to_posts') ?></a>
</div>

<?php if ($categories === []): ?>
	<div class="card"><p><?= $view->t('blog.empty.no_categories_yet') ?></p></div>
<?php else: ?>
	<ul class="rows">
<?php foreach ($categories as $slug => $category): ?>
		<li class="row">
			<form class="row-main" method="post" action="<?= e(Controller::url('module.blog.category.save')) ?>">
				<?= $view->csrfField() ?>
				<input type="hidden" name="original" value="<?= e($slug) ?>">
				<input type="text" name="title" value="<?= e($category['title']) ?>" required maxlength="120"
				       aria-label="<?= $view->t('blog.label.category_name') ?>">
				<button class="btn-quiet" type="submit"><?= $view->t('blog.action.rename') ?></button>
			</form>

			<span class="row-actions">
				<span class="row-meta"><?= $view->t('blog.label.posts_in_category', ['count' => $category['posts']], $category['posts']) ?></span>
				<form method="post" action="<?= e(Controller::url('module.blog.category.delete')) ?>"
				      data-confirm="<?= $view->t('blog.confirm.delete_category', ['title' => $category['title'], 'count' => $category['posts']]) ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="slug" value="<?= e($slug) ?>">
					<button class="btn-icon" type="submit"
					        aria-label="<?= $view->t('blog.action.delete_named', ['title' => $category['title']]) ?>">×</button>
				</form>
			</span>
		</li>
<?php endforeach; ?>
	</ul>
<?php endif; ?>

<form class="card" method="post" action="<?= e(Controller::url('module.blog.category.save')) ?>">
	<?= $view->csrfField() ?>
	<h2><?= $view->t('blog.action.new_category') ?></h2>
	<label class="field">
		<span><?= $view->t('blog.label.category_name') ?></span>
		<input type="text" name="title" required maxlength="120">
	</label>
	<button class="btn" type="submit"><?= $view->t('blog.action.add_category') ?></button>
</form>
