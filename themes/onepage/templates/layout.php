<?php
/**
 * One page, made of several.
 *
 * Every top-level page becomes a section on one continuous document, and the
 * menu links to anchors rather than to addresses. The editor keeps working the
 * way it always did — separate pages, one at a time — while a visitor gets a
 * single scrolling page.
 *
 * Why it works without anything being added to Pluck: `$menu` already carries
 * the full Page objects, content and all, because `allPages()` loads them. So
 * the theme has everything it needs to lay the whole site out at once.
 *
 * What that costs, and it is worth knowing before you use this on a big site:
 * every request renders every page. On the handful of pages a one-pager is for,
 * that is nothing. On two hundred pages it would be a slow site, and this is the
 * wrong theme for one.
 *
 * Sub-pages are not stacked. They stay ordinary pages at their own addresses,
 * which is what keeps this from turning into the entire site on one screen.
 *
 * @var \Pluck\Site\Urls $urls
 * @var \Pluck\Site\Menu $menu
 * @var \Pluck\View\Raw $content
 * @var ?\Pluck\Model\Page $page
 */

$sections = $menu->items();

/*
 * A module or a search result is not part of the stack: it is one thing the
 * visitor asked for. Those get the plain layout, with the menu still pointing
 * home so there is a way back into the one-pager.
 */
$standalone = $page === null;
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
<?php if ($noindex ?? false): ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>
<?php if (!$standalone && $page !== null): ?>
<!--
	A section has one address, and it is the anchor. Without this, every section
	would also exist at its own URL with the whole page behind it, and a search
	engine would see the same content a dozen times over.
-->
<link rel="canonical" href="<?= e($urls->to('')) ?>#<?= e($page->path) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= e($themeAssets) ?>/style.css">
</head>
<body>

<a class="skip" href="#sections"><?= $view->t('site.skip_to_content') ?></a>

<header id="header">
	<div class="layout">
		<div id="logo">
<?php if (($logo ?? '') !== ''): ?>
			<a href="<?= e($urls->to('')) ?>"><img src="<?= e($urls->media($logo)) ?>" alt="<?= e($siteTitle) ?>"></a>
<?php else: ?>
			<h1><a href="<?= e($urls->to('')) ?>"><?= e($siteTitle) ?></a></h1>
<?php endif; ?>
<?php if (($tagline ?? '') !== ''): ?>
			<p class="tagline"><?= e($tagline) ?></p>
<?php endif; ?>
		</div>

<?php if (!$menu->isEmpty()): ?>
		<input type="checkbox" id="menu-open" class="menu-toggle">
		<label for="menu-open" class="menu-button">
			<span aria-hidden="true">☰</span>
			<span class="visually-hidden"><?= $view->t('site.main_menu') ?></span>
		</label>

		<nav id="menu" aria-label="<?= $view->t('site.main_menu') ?>">
			<ul>
<?php foreach ($sections as $item): ?>
				<!--
					An anchor on the front page, not the section's own address. The
					section is on this document already, so linking to its URL would
					reload the whole thing to arrive at the same place.
				-->
				<li><a href="<?= e($urls->to('')) ?>#<?= e($item->path()) ?>"><?= e($item->title()) ?></a></li>
<?php endforeach; ?>
			</ul>
		</nav>
<?php endif; ?>
	</div>
</header>

<?php if ($standalone): ?>
<main id="sections" class="layout standalone">
<?= $content ?>
</main>
<?php else: ?>
<main id="sections">
<?php foreach ($sections as $index => $item): ?>
	<section id="<?= e($item->path()) ?>" class="section<?= $index % 2 === 1 ? ' section--alt' : '' ?>">
		<div class="layout">
			<h2><?= e($item->title()) ?></h2>
			<?php // Sanitised when the page was saved, exactly as $content is. ?>
			<?= $item->page->content ?>

<?php if ($item->hasChildren()): ?>
			<!--
				Sub-pages stay ordinary pages at their own addresses. Stacking those
				too would put the whole site on one screen, which is the thing a
				one-pager is trying not to be.
			-->
			<ul class="section-links">
<?php foreach ($item->children as $child): ?>
				<li><a href="<?= e($urls->to($child->path())) ?>"><?= e($child->title()) ?></a></li>
<?php endforeach; ?>
			</ul>
<?php endif; ?>
		</div>
	</section>
<?php endforeach; ?>
</main>
<?php endif; ?>

<footer id="footer">
	<div class="layout">
		<p><?= e($siteTitle) ?></p>
		<p class="muted"><?= $view->t('site.powered_by') ?></p>
	</div>
</footer>

</body>
</html>
