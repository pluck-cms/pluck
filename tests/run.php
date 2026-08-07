<?php
declare(strict_types=1);

/**
 * Test runner. No dependencies:  php tests/run.php
 */

/*
 * The suite writes and deletes temporary trees, and creates a symlink to prove
 * the path sanitiser refuses one. A mistake anywhere in that machinery is a lost
 * temp directory when it runs unprivileged, and a lost system when it runs as
 * root. There is no reason for these tests to have that power, so they refuse
 * it. Set PLUCK_ALLOW_ROOT_TESTS=1 if a container genuinely offers no other
 * user; read tests/TestCase.php::removeTree() first.
 */
if (function_exists('posix_getuid') && posix_getuid() === 0 && getenv('PLUCK_ALLOW_ROOT_TESTS') !== '1') {
	fwrite(STDERR, "Refusing to run the test suite as root.\n");
	fwrite(STDERR, "Run it as an unprivileged user, or set PLUCK_ALLOW_ROOT_TESTS=1 if you are sure.\n");
	exit(2);
}

require dirname(__DIR__) . '/src/autoload.php';

spl_autoload_register(static function (string $class): void {
	$prefix = 'Pluck\\Tests\\';
	if (str_starts_with($class, $prefix)) {
		$file = __DIR__ . '/' . substr($class, strlen($prefix)) . '.php';
		if (is_file($file)) {
			require $file;
		}
	}
});

printf("Pluck 5 test suite - PHP %s\n\n", PHP_VERSION);

$suites = [
	Pluck\Tests\SecurityTest::class,
	Pluck\Tests\SlugTest::class,
	Pluck\Tests\PathTest::class,
	Pluck\Tests\StorageParityTest::class,
	Pluck\Tests\SchemaUpgradeTest::class,
	Pluck\Tests\I18nTest::class,
	Pluck\Tests\CatalogueTest::class,
	Pluck\Tests\InstallerTest::class,
	Pluck\Tests\SessionOrderTest::class,
	Pluck\Tests\AuthTest::class,
	Pluck\Tests\RouteAccessTest::class,
	Pluck\Tests\CsrfSurfaceTest::class,
	Pluck\Tests\LegacyFileTest::class,
	Pluck\Tests\EntryPolicyTest::class,
	Pluck\Tests\SafeZipTest::class,
	Pluck\Tests\UploadNamingTest::class,
	Pluck\Tests\MigrateTest::class,
	Pluck\Tests\SiteRouteTest::class,
	Pluck\Tests\ThemeTest::class,
	Pluck\Tests\ModuleAdminTest::class,
	Pluck\Tests\BlogAdminTest::class,
	Pluck\Tests\AlbumsAdminTest::class,
	Pluck\Tests\AccessListTest::class,
	Pluck\Tests\EmbedTest::class,
	Pluck\Tests\SearchTest::class,
	Pluck\Tests\BackupTest::class,
	Pluck\Tests\UpdateTest::class,
	Pluck\Tests\FormGuardTest::class,
	Pluck\Tests\RedirectTest::class,
	Pluck\Tests\AdminScreenTest::class,
	Pluck\Tests\LegacyThemeTest::class,
];

$ok = true;
foreach ($suites as $suite) {
	/** @var Pluck\Tests\TestCase $instance */
	$instance = new $suite();
	$ok = $instance->execute() && $ok;
}

echo "\n", $ok ? "All suites passed.\n" : "Failures above.\n";
exit($ok ? 0 : 1);
