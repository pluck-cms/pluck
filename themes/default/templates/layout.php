<?php
/**
 * The frame around every page.
 *
 * Everything printed here goes through e() except $content, which is either page
 * content the sanitiser has already cleaned or markup a module built. That is
 * the whole escaping rule; if you copy this theme, keep it.
 *
 * @var \Pluck\Site\Urls $urls
 * @var \Pluck\Site\Menu $menu
 * @var string $documentTitle
 * @var string $siteTitle
 * @var string $themeAssets
 * @var string $locale
 * @var \Pluck\View\Raw $content
 * @var string $description
 * @var ?string $canonical
 */
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($documentTitle) ?></title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if (($keywords ?? '') !== ''): ?>
<meta name="keywords" content="<?= e($keywords) ?>">
<?php endif; ?>
<?php if ($canonical !== null): ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<?php if ($noindex ?? false): ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>
<!-- Pluck's own, before the theme's: a theme overrules any of it by saying
     the rule again, because later rules win. -->
<link rel="stylesheet" href="<?= e($siteAssets) ?>/colours.css">
<link rel="stylesheet" href="<?= e($themeAssets) ?>/style.css">
</head>
<body>

<a class="skip" href="#content"><?= $view->t('site.skip_to_content') ?></a>

<header class="site-header">
	<p class="site-title"><a href="<?= e($urls->to('')) ?>"><?= e($siteTitle) ?></a></p>

<?php if (!$menu->isEmpty()): ?>
	<nav class="site-nav" aria-label="<?= $view->t('site.main_menu') ?>">
		<?= $view->partial('partials/menu', ['items' => $menu->items()]) ?>
	</nav>
<?php endif; ?>

<?php if ($searchEnabled ?? false): ?>
	<form class="site-search" method="get" action="<?= e($urls->to('search')) ?>" role="search">
<?php if (!$urls->isPretty()): ?>
		<input type="hidden" name="page" value="search">
<?php endif; ?>
		<label class="visually-hidden" for="site-q"><?= $view->t('search.label.what') ?></label>
		<input type="search" id="site-q" name="q" maxlength="100"
		       placeholder="<?= $view->t('search.action.search') ?>">
	</form>
<?php endif; ?>
</header>

<?php if ($trail !== [] || ($moduleBreadcrumbs ?? []) !== []): ?>
<nav class="breadcrumbs" aria-label="<?= $view->t('site.breadcrumbs') ?>">
	<ol>
		<li><a href="<?= e($urls->to('')) ?>"><?= $view->t('site.home') ?></a></li>
<?php foreach ($moduleBreadcrumbs ?? [] as $crumb): ?>
		<li><a href="<?= e($urls->to($crumb['path'])) ?>"><?= e($crumb['title']) ?></a></li>
<?php endforeach; ?>
<?php foreach ($trail as $crumb): ?>
<?php if ($crumb->active): ?>
		<li aria-current="page"><?= e($crumb->title()) ?></li>
<?php else: ?>
		<li><a href="<?= e($urls->to($crumb->path())) ?>"><?= e($crumb->title()) ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
	</ol>
</nav>
<?php endif; ?>

<main id="content" class="site-main">
<?= $content ?>
</main>

<footer class="site-footer">
	<p><?= $view->t('site.powered_by') ?></p>
</footer>

</body>
</html>
