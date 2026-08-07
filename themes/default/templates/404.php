<?php
/**
 * Nothing at this address.
 *
 * The requested path is deliberately not printed. Reflecting it lets anyone put
 * text of their choosing on your site by sending someone a link, which is a
 * phishing surface for no gain — the visitor already knows what they typed.
 *
 * @var string $title
 * @var \Pluck\View\Raw $content
 * @var \Pluck\Site\Urls $urls
 */
?>
<article class="page page--404">
	<h1><?= e($title) ?></h1>
	<?= $content ?>
	<p><a href="<?= e($urls->to('')) ?>"><?= $view->t('site.back_home') ?></a></p>
</article>
