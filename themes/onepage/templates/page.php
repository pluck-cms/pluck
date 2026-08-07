<?php
/**
 * One content page.
 *
 * @var ?\Pluck\Model\Page $page
 * @var string $title
 * @var \Pluck\View\Raw $content
 */
?>
<article class="page">
	<h1><?= e($title) ?></h1>
	<?= $content ?>
</article>
