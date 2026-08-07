<?php
declare(strict_types=1);

/*
 * Pluck 5 ships with its own PSR-4 autoloader so that a plain unzip-and-go
 * install needs no Composer. Composer's autoloader is used when present
 * (development, tests, or installs that pull in optional packages).
 */

require __DIR__ . '/Support/functions.php';

$composer = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composer)) {
	require $composer;
	return;
}

spl_autoload_register(static function (string $class): void {
	$prefix = 'Pluck\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}

	$relative = substr($class, strlen($prefix));
	$path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

	if (is_file($path)) {
		require $path;
	}
});
