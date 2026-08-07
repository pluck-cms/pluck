<?php
/**
 * The page tree.
 *
 * Indentation carries the hierarchy, and the up/down buttons are ordinary form
 * posts rather than drag handles: reordering has to work with a thumb on a phone
 * and with JavaScript switched off. Depth is a class rather than an inline custom
 * property because the admin CSP allows no inline styles at all.
 *
 * @var list<array{page:\Pluck\Model\Page,depth:int,isFirst:bool,isLast:bool,canEdit:bool,canDelete:bool}> $tree
 * @var bool $canCreate
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<div class="head">
	<h1><?= $view->t('nav.pages') ?></h1>
	<?php if ($canCreate): ?>
		<a class="btn" href="<?= e(Controller::url('page.new')) ?>"><?= $view->t('ui.pages.index.new_page') ?></a>
	<?php endif; ?>
</div>

<?php if ($tree === []): ?>
	<div class="card"><p><?= $view->t('ui.pages.index.no_pages_yet') ?></p></div>
<?php else: ?>
	<ul class="rows tree">
		<?php foreach ($tree as $row): $page = $row['page']; ?>
			<li class="row depth-<?= min(3, (int) $row['depth']) ?>">
				<?php if ($row['canEdit']): ?>
					<a class="row-main" href="<?= e(Controller::url('page.edit', ['path' => $page->path])) ?>">
				<?php else: ?>
					<span class="row-main is-locked">
				<?php endif; ?>
					<span class="row-title"><?= e($page->title) ?></span>
					<span class="row-meta">/<?= e($page->path) ?><?php if ($page->hidden): ?> · hidden<?php endif; ?></span>
				<?php if ($row['canEdit']): ?></a><?php else: ?></span><?php endif; ?>

				<span class="row-actions">
					<?php if ($row['canEdit'] && !$row['isFirst']): ?>
						<form method="post" action="<?= e(Controller::url('page.move')) ?>">
							<?= $view->csrfField() ?>
							<input type="hidden" name="path" value="<?= e($page->path) ?>">
							<input type="hidden" name="direction" value="up">
							<button class="btn-icon" type="submit" aria-label="Move <?= e($page->title) ?> up">↑</button>
						</form>
					<?php endif; ?>
					<?php if ($row['canEdit'] && !$row['isLast']): ?>
						<form method="post" action="<?= e(Controller::url('page.move')) ?>">
							<?= $view->csrfField() ?>
							<input type="hidden" name="path" value="<?= e($page->path) ?>">
							<input type="hidden" name="direction" value="down">
							<button class="btn-icon" type="submit" aria-label="Move <?= e($page->title) ?> down">↓</button>
						</form>
					<?php endif; ?>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
