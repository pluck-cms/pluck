<?php
/**
 * Output from a module. Identical to page.php here, and separate on purpose: a
 * theme that wants a wider blog than its pages changes this file and nothing
 * else.
 *
 * @var string $title
 * @var \Pluck\View\Raw $content
 */
?>
<div class="module">
	<h1><?= e($title) ?></h1>
	<?= $content ?>
</div>
