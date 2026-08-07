<?php
/** @var string $title @var string $message @var int $status */
?>
<div class="card">
	<p class="eyebrow"><?= e($status) ?></p>
	<h1><?= e($title) ?></h1>
	<p><?= e($message) ?></p>
	<p><a href="<?= e(\Pluck\Admin\Controller::url('dashboard')) ?>"><?= $view->t('ui.errors.admin.back_to_the_overview') ?></a></p>
</div>
