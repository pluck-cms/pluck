<?php
/** @var list<array{level:string,message:string}> $flashes */
foreach ($flashes as $flash):
	$class = match ($flash['level']) {
		'ok' => 'notice notice-ok',
		'stop' => 'notice notice-stop',
		default => 'notice notice-warn',
	};
	?>
	<p class="<?= e($class) ?>" role="status"><?= e($flash['message']) ?></p>
<?php endforeach; ?>
