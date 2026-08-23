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
 * Standalone means: not part of the stack.
 *
 * A module or a search result is one thing the visitor asked for, and so is any
 * page the stack does not contain — a sub-page, or a page hidden from the menu.
 *
 * This used to be `$page === null` alone, which is a narrower question than the
 * one that matters. The stack is built from $menu->items(); a sub-page or a
 * hidden page is not in there, so it fell into the stacked branch and the loop
 * never reached it. The visitor got the front page back with none of the content
 * they asked for, at ?page= and at a readable address alike — which reads like a
 * routing fault rather than a theme one, and sent at least one person looking in
 * the wrong place.
 *
 * Asked of the stack rather than guessed from the address: a slash in the path
 * finds sub-pages and misses hidden pages, and it is the stack that decides.
 */
$inStack = false;

if ($page !== null) {
	foreach ($sections as $item) {
		if ($item->path() === $page->path) {
			$inStack = true;
		}
	}
}

$standalone = $page === null || !$inStack;

/*
 * The page above a sub-page, for the way back.
 *
 * Found in the menu tree rather than by asking storage: $sections is already
 * built, and a theme that reaches for the storage driver is doing the renderer's
 * job. Null when the parent is itself hidden — no link is better than one that
 * lands somewhere the visitor cannot use.
 */
$parent = null;

if ($page !== null && str_contains($page->path, '/')) {
	$parentPath = substr($page->path, 0, (int) strrpos($page->path, '/'));

	foreach ($sections as $item) {
		if ($item->path() === $parentPath) {
			$parent = $item;
		}
	}
}
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
<?php elseif ($page !== null): ?>
<!--
	A page outside the stack is its own address and says so.

	It used to get the anchor above, which tells a search engine "I am really the
	front page" — so a sub-page explaining what somebody actually does was not
	indexed at all. Exactly the page people search for.
-->
<link rel="canonical" href="<?= e($urls->to($page->path)) ?>">
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
<?php if ($parent !== null): ?>
	<?php /* The way back, above the text rather than under it: whoever wants it
	         wants it before they have read the page again. It points at the
	         anchor, because that is where the parent's content lives — its own
	         address would reload the front page and land at the top. */ ?>
	<p class="section__back">
		<a href="<?= e($urls->to('')) ?>#<?= e($parent->path()) ?>">&larr; <?= e($parent->title()) ?></a>
	</p>
<?php endif; ?>
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
