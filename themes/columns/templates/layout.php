<?php
/**
 * Columns — a header, a menu, a page, a footer.
 *
 * Rebuilt from a Pluck 4 theme that used the 5grid framework: jQuery, a
 * JavaScript viewport switcher and an off-canvas menu, about 300 kB of it. None
 * of that is here, and nothing is lost — media queries do the layout, and the
 * menu opens with a checkbox.
 *
 * That is not only tidiness. 5grid blocked touchmove while its menu was open and
 * only cleared the flag in an animation callback, so an interrupted animation
 * left scrolling dead until the page was reloaded. A menu with no JavaScript
 * cannot have that bug.
 *
 * The logo is whatever you upload: put a file in the media library and name it
 * in Settings, or leave it and the site title is used.
 *
 * @var \Pluck\Site\Urls $urls
 * @var \Pluck\Site\Menu $menu
 * @var \Pluck\View\Raw $content
 */
$tagline = $tagline ?? '';
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

<div id="header-wrapper">
	<header id="header">
		<div class="layout">
			<div id="logo">
<?php if (($logo ?? '') !== ''): ?>
				<a href="<?= e($urls->to('')) ?>"><img src="<?= e($urls->media($logo)) ?>" alt="<?= e($siteTitle) ?>"></a>
<?php else: ?>
				<h1><a href="<?= e($urls->to('')) ?>"><?= e($siteTitle) ?></a></h1>
<?php endif; ?>
<?php if ($tagline !== ''): ?>
				<p class="tagline"><?= e($tagline) ?></p>
<?php endif; ?>
			</div>
		</div>

<?php if (!$menu->isEmpty()): ?>
		<div id="menu-wrapper">
			<div class="layout">
				<!--
					The checkbox is the menu button. It comes before the nav so CSS can
					reach the nav with a sibling selector, which is the whole trick: no
					JavaScript, so nothing can leave the menu stuck open and scrolling
					dead — which is exactly what the framework this replaces did.
				-->
				<input type="checkbox" id="menu-open" class="menu-toggle">
				<label for="menu-open" class="menu-button">
					<span aria-hidden="true">☰</span>
					<span class="visually-hidden"><?= $view->t('site.main_menu') ?></span>
				</label>

				<nav id="menu" aria-label="<?= $view->t('site.main_menu') ?>">
					<?= $view->partial('partials/menu', ['items' => $menu->items()]) ?>
				</nav>
			</div>
		</div>
<?php endif; ?>
	</header>
</div>

<?php if ($trail !== [] || ($moduleBreadcrumbs ?? []) !== []): ?>
<nav class="breadcrumbs layout" aria-label="<?= $view->t('site.breadcrumbs') ?>">
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

<div id="page-wrapper">
	<main id="content" class="layout">
<?= $content ?>
	</main>
</div>

<footer id="footer">
	<div class="layout">
		<p><?= e($siteTitle) ?><?php if (($siteDescription ?? '') !== ''): ?> &middot; <?= e($siteDescription) ?><?php endif; ?></p>
		<p class="muted"><?= $view->t('site.powered_by') ?></p>
	</div>
</footer>

</body>
</html>
