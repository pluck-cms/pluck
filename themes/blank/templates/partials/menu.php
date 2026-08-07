<?php
/**
 * One level of the menu, calling itself for whatever sits underneath.
 *
 * Sub-menus are rendered only for the branch you are actually in. A site with
 * three levels and forty pages would otherwise ship its entire tree on every
 * request, which on the hosting Pluck targets is the difference between a fast
 * page and a slow one.
 *
 * @var list<\Pluck\Site\MenuItem> $items
 * @var \Pluck\Site\Urls $urls
 */
?>
<ul>
<?php foreach ($items as $item): ?>
	<li<?= $item->active ? ' class="is-active"' : ($item->open ? ' class="is-open"' : '') ?>>
		<a href="<?= e($urls->to($item->path())) ?>"<?= $item->active ? ' aria-current="page"' : '' ?>><?= e($item->title()) ?></a>
<?php if ($item->hasChildren() && $item->open): ?>
		<?= $view->partial('partials/menu', ['items' => $item->children]) ?>
<?php endif; ?>
	</li>
<?php endforeach; ?>
</ul>
