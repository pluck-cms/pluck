<?php
/**
 * The admin shell.
 *
 * Navigation sits at the bottom of the viewport on a phone and moves to a
 * sidebar from 52rem up. That is the whole responsive story: one column that
 * grows a rail, rather than a desktop layout with things hidden away.
 *
 * @var \Pluck\View\View $view
 * @var \Pluck\View\Raw  $content
 * @var string           $title
 */

use Pluck\Admin\Controller;
use Pluck\Bootstrap;

/** @var \Pluck\Model\User|null $currentUser */
$currentUser = $currentUser ?? null;
$flashes = $flashes ?? [];
$activeRoute = $activeRoute ?? '';

$nav = [];
foreach ([
	['pages', 'nav.pages', 'page.view'],
	['media', 'nav.media', 'file.view'],
	['settings', 'nav.settings', 'settings.view'],
	['users', 'nav.people', 'user.view'],
	['access', 'nav.permissions', 'user.view'],
	['messages', 'nav.messages', 'page.view'],
	['backups', 'nav.backups', 'user.view'],
	['stylesheet', 'nav.stylesheet', 'theme.view'],
	['diagnostics', 'nav.diagnostics', 'settings.view'],
	// The badge is part of the label rather than a separate element, so a theme
	// that restyles the navigation cannot lose it.
	['updates', ($updateAvailable ?? false) ? 'nav.updates_available' : 'nav.updates', 'update.run'],
] as [$route, $label, $permission]) {
	// Through $can, not $currentUser->can(): the access list is what decides, and
	// a menu entry leading to a screen you are refused is worse than no entry.
	if ($currentUser !== null && ($can ?? static fn (): bool => false)($permission)) {
		// The key, not the translation. Translating here and escaping at the point
		// of printing escapes twice — "Pagina's" arrives as "Pagina&#039;s" — and
		// storing the finished string is what hides that from whoever writes the
		// echo.
		$nav[] = ['route' => $route, 'label' => $label];
	}
}

/*
 * Modules go after the core sections, in the order the registry gave them.
 * ModuleRegistry::navigation() has already dropped the ones this account may not
 * manage, so nothing here has to know about permissions — a menu entry leading to
 * a screen you are refused is worse than no entry.
 */
foreach ($moduleNav ?? [] as $item) {
	$nav[] = ['route' => $item['route'], 'label' => $item['label']];
}
?>
<!DOCTYPE html>
<html lang="<?= e($locale ?? 'en') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title><?= e($title) ?> · <?= e($siteTitle ?? 'Pluck') ?></title>
<link rel="stylesheet" href="assets/admin/pluck.css?v=<?= e(\Pluck\Bootstrap::VERSION) ?>">
</head>
<body class="app">

<header class="topbar">
	<a class="wordmark" href="<?= e(Controller::url('dashboard')) ?>">
		<b>pluck</b><span><?= e(Bootstrap::VERSION) ?></span>
	</a>
	<?php if ($currentUser !== null): ?>
		<div class="whoami">
			<a href="<?= e(Controller::url('account')) ?>"><?= e($currentUser->displayName) ?></a>
			<span class="role"><?= e($currentUser->role->label()) ?></span>

			<!--
				Signing out belongs next to the name it signs out of. It was at the
				bottom of the menu, below everything, which is where you put a thing
				nobody should find.
			-->
			<form method="post" action="<?= e(Controller::url('signout')) ?>">
				<?= $view->csrfField() ?>
				<!-- A link, not a button: signing out is not the thing anybody came
				     here to do, and a green button says otherwise. -->
				<button class="btn-link" type="submit"><?= $view->t('nav.sign_out') ?></button>
			</form>
		</div>


	<?php endif; ?>
</header>

<div class="layout">
	<?php if ($nav !== []): ?>
		<nav class="nav" aria-label="<?= $view->t('nav.sections') ?>">
			<a class="nav-link<?= $activeRoute === 'dashboard' ? ' is-current' : '' ?>" href="<?= e(Controller::url('dashboard')) ?>"><?= $view->t('nav.overview') ?></a>
			<?php foreach ($nav as $item): ?>
				<a class="nav-link<?= str_starts_with($activeRoute, $item['route']) ? ' is-current' : '' ?>"
				   href="<?= e(Controller::url($item['route'])) ?>"><?= $view->t($item['label']) ?></a>
			<?php endforeach; ?>
			<a class="nav-link nav-link-site" href="index.php" target="_blank" rel="noopener"><?= $view->t('nav.view_site') ?></a>

			<!--
				Dark, light, or the machine's own setting. One quiet control at the
				foot of the menu: three buttons at the top of the screen competed
				with the page for attention, which is a lot of noise for something
				somebody sets once.
			-->
			<button class="nav-link nav-appearance" type="button" data-appearance-cycle>
				<span data-appearance-label
				      data-system="<?= e($view->t('appearance.system')) ?>"
				      data-light="<?= e($view->t('appearance.light')) ?>"
				      data-dark="<?= e($view->t('appearance.dark')) ?>"><?= $view->t('appearance.system') ?></span>
			</button>
		</nav>
	<?php endif; ?>

	<main class="main">
		<?= $view->partial('admin/partials/flash', ['flashes' => $flashes]) ?>
		<?= $content ?>
	</main>
</div>



<script src="assets/admin/pluck.js?v=<?= e(\Pluck\Bootstrap::VERSION) ?>" nonce="<?= e($nonce) ?>" defer></script>
<!-- After pluck.js, and only where there is something to edit: the editor
     listens for the insert event that file dispatches. -->
<?php if (($activeRoute ?? '') === 'page.edit' || ($activeRoute ?? '') === 'page.new'): ?>
<script src="assets/admin/editor.js?v=<?= e(\Pluck\Bootstrap::VERSION) ?>" nonce="<?= e($nonce) ?>" defer></script>
<?php endif; ?>
</body>
</html>
