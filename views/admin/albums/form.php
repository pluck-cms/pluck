<?php
/**
 * One album: its name, its description, and its pictures.
 *
 * Two ways to add a picture, side by side, because they are genuinely different
 * acts. Uploading puts a new file in the shared media library and this album owns
 * it. Picking references a file that is already there — a photo from another
 * album, or one a page uses — without making a second copy of it.
 *
 * @var string $slug
 * @var array<string,mixed> $album
 * @var list<array{key:string,id:string,file:string,title:string,info:string,owned:bool}> $images
 * @var list<string> $library
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<div class="head">
	<h1><?= e($title) ?></h1>
	<a class="btn-quiet" href="<?= e(Controller::url('module.albums.index')) ?>"><?= $view->t('albums.action.back_to_albums') ?></a>
</div>

<form class="card" method="post" action="<?= e(Controller::url('module.albums.save')) ?>">
	<?= $view->csrfField() ?>
	<input type="hidden" name="original" value="<?= e($slug) ?>">

	<label class="field">
		<span><?= $view->t('albums.label.album_name') ?></span>
		<input type="text" name="title" required maxlength="120" value="<?= e((string) ($album['title'] ?? '')) ?>">
	</label>

	<label class="field">
		<span><?= $view->t('albums.label.description') ?></span>
		<textarea name="description" rows="4"><?= e((string) ($album['description'] ?? '')) ?></textarea>
		<span class="muted"><?= $view->t('albums.help.description') ?></span>
	</label>

	<button class="btn" type="submit"><?= $view->t('albums.action.save_album') ?></button>
</form>

<div class="card">
	<h2><?= $view->t('albums.title.add_picture') ?></h2>

	<form method="post" action="<?= e(Controller::url('module.albums.image.add')) ?>" enctype="multipart/form-data">
		<?= $view->csrfField() ?>
		<input type="hidden" name="album" value="<?= e($slug) ?>">
		<label class="field">
			<span><?= $view->t('albums.label.upload') ?></span>
			<input type="file" name="file" accept="image/*" required>
		</label>
		<button class="btn" type="submit"><?= $view->t('albums.action.upload') ?></button>
	</form>

<?php if ($library !== []): ?>
	<form method="post" action="<?= e(Controller::url('module.albums.image.pick')) ?>">
		<?= $view->csrfField() ?>
		<input type="hidden" name="album" value="<?= e($slug) ?>">
		<label class="field">
			<span><?= $view->t('albums.label.pick_existing') ?></span>
			<select name="file">
<?php foreach ($library as $name): ?>
				<option value="<?= e($name) ?>"><?= e($name) ?></option>
<?php endforeach; ?>
			</select>
			<span class="muted"><?= $view->t('albums.help.pick_existing') ?></span>
		</label>
		<button class="btn-quiet" type="submit"><?= $view->t('albums.action.pick') ?></button>
	</form>
<?php endif; ?>
</div>

<?php if ($images === []): ?>
	<div class="card"><p><?= $view->t('albums.empty.album_has_no_pictures') ?></p></div>
<?php else: ?>
<?php foreach ($images as $index => $image): ?>
	<div class="card">
		<img class="album-thumb" src="media/<?= e(rawurlencode($image['file'])) ?>" alt="<?= e($image['title']) ?>">

		<form method="post" action="<?= e(Controller::url('module.albums.image.save')) ?>">
			<?= $view->csrfField() ?>
			<input type="hidden" name="album" value="<?= e($slug) ?>">
			<input type="hidden" name="id" value="<?= e($image['id']) ?>">

			<label class="field">
				<span><?= $view->t('albums.label.caption') ?></span>
				<input type="text" name="title" maxlength="200" value="<?= e($image['title']) ?>">
			</label>

			<label class="field">
				<span><?= $view->t('albums.label.caption_text') ?></span>
				<textarea name="info" rows="2"><?= e($image['info']) ?></textarea>
			</label>

			<button class="btn-quiet" type="submit"><?= $view->t('albums.action.save_caption') ?></button>
			<span class="muted"><?= e($image['file']) ?></span>
		</form>

		<span class="row-actions">
<?php if ($index > 0): ?>
			<form method="post" action="<?= e(Controller::url('module.albums.image.move')) ?>">
				<?= $view->csrfField() ?>
				<input type="hidden" name="album" value="<?= e($slug) ?>">
				<input type="hidden" name="id" value="<?= e($image['id']) ?>">
				<input type="hidden" name="direction" value="up">
				<button class="btn-icon" type="submit" aria-label="<?= $view->t('albums.action.move_picture_up') ?>">↑</button>
			</form>
<?php endif; ?>
<?php if ($index < count($images) - 1): ?>
			<form method="post" action="<?= e(Controller::url('module.albums.image.move')) ?>">
				<?= $view->csrfField() ?>
				<input type="hidden" name="album" value="<?= e($slug) ?>">
				<input type="hidden" name="id" value="<?= e($image['id']) ?>">
				<input type="hidden" name="direction" value="down">
				<button class="btn-icon" type="submit" aria-label="<?= $view->t('albums.action.move_picture_down') ?>">↓</button>
			</form>
<?php endif; ?>

			<form method="post" action="<?= e(Controller::url('module.albums.image.remove')) ?>"
			      data-confirm="<?= $view->t('albums.confirm.remove_picture') ?>">
				<?= $view->csrfField() ?>
				<input type="hidden" name="album" value="<?= e($slug) ?>">
				<input type="hidden" name="id" value="<?= e($image['id']) ?>">
<?php if ($image['owned']): ?>
				<label class="choice">
					<input type="checkbox" name="delete_file" value="1">
					<span><?= $view->t('albums.label.also_delete_file') ?></span>
				</label>
<?php else: ?>
				<span class="muted"><?= $view->t('albums.help.picked_file_stays') ?></span>
<?php endif; ?>
				<button class="btn-quiet" type="submit"><?= $view->t('albums.action.remove_picture') ?></button>
			</form>
		</span>
	</div>
<?php endforeach; ?>
<?php endif; ?>
