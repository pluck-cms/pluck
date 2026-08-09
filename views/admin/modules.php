<?php
/**
 * The modules this install has.
 *
 * @var list<array{route:string,label:string}> $modules
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<h1><?= $view->t('nav.modules') ?></h1>

<?php if ($modules === []): ?>
<p class="muted"><?= $view->t('modules.none') ?></p>
<p class="hint"><?= $view->t('modules.help.enable') ?></p>
<?php else: ?>
<ul class="cards">
<?php foreach ($modules as $module): ?>
	<li>
		<a href="<?= e(Controller::url($module['route'])) ?>"><?= $view->t($module['label']) ?></a>
	</li>
<?php endforeach; ?>
</ul>
<p class="hint"><?= $view->t('modules.help.enable') ?></p>
<?php endif; ?>
