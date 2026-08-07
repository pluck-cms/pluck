<?php
/**
 * The album list.
 *
 * @var array<string,array{title:string,description:string,count:int,cover:?string}> $albums
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;

$slugs = array_keys($albums);
?>
<div class="head">
	<h1><?= $view->t('albums.title.albums') ?></h1>
</div>

<?php if ($albums === []): ?>
	<div class="card"><p><?= $view->t('albums.empty.no_albums_yet') ?></p></div>
<?php else: ?>
	<ul class="rows">
<?php foreach ($albums as $slug => $album): ?>
		<li class="row">
			<a class="row-main" href="<?= e(Controller::url('module.albums.edit', ['slug' => $slug])) ?>">
				<span class="row-title"><?= e($album['title']) ?></span>
				<span class="row-meta">
					<?= $view->t('albums.count', ['count' => $album['count']], $album['count']) ?> · /albums/<?= e($slug) ?>
				</span>
			</a>

			<span class="row-actions">
<?php if ($slug !== $slugs[0]): ?>
				<form method="post" action="<?= e(Controller::url('module.albums.move')) ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="slug" value="<?= e($slug) ?>">
					<input type="hidden" name="direction" value="up">
					<button class="btn-icon" type="submit" aria-label="<?= $view->t('albums.action.move_up', ['title' => $album['title']]) ?>">↑</button>
				</form>
<?php endif; ?>
<?php if ($slug !== end($slugs)): ?>
				<form method="post" action="<?= e(Controller::url('module.albums.move')) ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="slug" value="<?= e($slug) ?>">
					<input type="hidden" name="direction" value="down">
					<button class="btn-icon" type="submit" aria-label="<?= $view->t('albums.action.move_down', ['title' => $album['title']]) ?>">↓</button>
				</form>
<?php endif; ?>
				<form method="post" action="<?= e(Controller::url('module.albums.delete')) ?>"
				      data-confirm="<?= $view->t('albums.confirm.delete_album', ['title' => $album['title']]) ?>">
					<?= $view->csrfField() ?>
					<input type="hidden" name="slug" value="<?= e($slug) ?>">
					<button class="btn-icon" type="submit" aria-label="<?= $view->t('albums.action.delete_named', ['title' => $album['title']]) ?>">×</button>
				</form>
			</span>
		</li>
<?php endforeach; ?>
	</ul>
<?php endif; ?>

<form class="card" method="post" action="<?= e(Controller::url('module.albums.save')) ?>">
	<?= $view->csrfField() ?>
	<h2><?= $view->t('albums.action.new_album') ?></h2>
	<label class="field">
		<span><?= $view->t('albums.label.album_name') ?></span>
		<input type="text" name="title" required maxlength="120">
	</label>
	<button class="btn" type="submit"><?= $view->t('albums.action.add_album') ?></button>
</form>
