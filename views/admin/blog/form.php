<?php
/**
 * The post editor.
 *
 * The address field is separate from the title on purpose. An existing post keeps
 * the address it has when its title changes, because renaming on every edit
 * breaks links silently — the kind of failure someone notices months later and
 * cannot trace back.
 *
 * @var ?string $slug
 * @var array<string,mixed> $post
 * @var array<string,string> $categories
 * @var array<string,mixed> $reactions
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;

$published = (bool) ($post['published'] ?? false);
$publishedAt = (string) ($post['published_at'] ?? '');
?>
<div class="head">
	<h1><?= e($title) ?></h1>
	<a class="btn-quiet" href="<?= e(Controller::url('module.blog.index')) ?>"><?= $view->t('blog.action.back_to_posts') ?></a>
</div>

<form method="post" action="<?= e(Controller::url('module.blog.save')) ?>">
	<?= $view->csrfField() ?>
	<input type="hidden" name="original" value="<?= e((string) ($slug ?? '')) ?>">

	<div class="card">
		<label class="field">
			<span><?= $view->t('blog.label.post_title') ?></span>
			<input type="text" name="title" required maxlength="200" value="<?= e((string) ($post['title'] ?? '')) ?>">
		</label>

		<label class="field">
			<span><?= $view->t('blog.label.address') ?></span>
			<input type="text" name="slug" value="<?= e((string) ($slug ?? '')) ?>"
			       placeholder="<?= $view->t('blog.help.address_from_title') ?>">
			<span class="muted"><?= $view->t('blog.help.address') ?></span>
		</label>

		<label class="field">
			<span><?= $view->t('blog.label.category') ?></span>
			<select name="category">
				<option value=""><?= $view->t('blog.label.no_category') ?></option>
<?php foreach ($categories as $value => $label): ?>
				<option value="<?= e($value) ?>"<?= ($post['category'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
			</select>
		</label>
	</div>

	<div class="card">
		<label class="field">
			<span><?= $view->t('blog.label.content') ?></span>
			<textarea name="content" rows="18"><?= e((string) ($post['content'] ?? '')) ?></textarea>
			<span class="muted"><?= $view->t('blog.help.content') ?></span>
		</label>
	</div>

	<div class="card">
		<label class="choice">
			<input type="checkbox" name="published" value="1"<?= $published ? ' checked' : '' ?>>
			<span><b><?= $view->t('blog.label.published') ?></b><span class="muted"><?= $view->t('blog.help.published') ?></span></span>
		</label>

		<label class="choice">
			<input type="checkbox" name="allow_reaction" value="1"<?= ($post['allow_reaction'] ?? true) ? ' checked' : '' ?>>
			<span><b><?= $view->t('blog.label.allow_reaction_here') ?></b><span class="muted"><?= $view->t('blog.help.allow_reaction_here') ?></span></span>
		</label>

		<label class="field">
			<span><?= $view->t('blog.label.published_at') ?></span>
			<input type="text" name="published_at" value="<?= e($publishedAt) ?>"
			       placeholder="<?= $view->t('blog.help.published_at_now') ?>">
			<span class="muted"><?= $view->t('blog.help.published_at') ?></span>
		</label>
	</div>

	<button class="btn" type="submit"><?= $view->t('blog.action.save_post') ?></button>
</form>

<?php if ($reactions !== []): ?>
	<div class="card">
		<h2><?= $view->t('blog.reactions', ['count' => count($reactions)], count($reactions)) ?></h2>
		<p class="muted"><?= $view->t('blog.help.reactions_managed_elsewhere') ?></p>
		<p><a href="<?= e(Controller::url('module.blog.reactions')) ?>"><?= $view->t('blog.title.reactions') ?></a></p>
	</div>
<?php endif; ?>
