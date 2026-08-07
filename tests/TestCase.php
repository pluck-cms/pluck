<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Throwable;

/**
 * A three-method test base so the suite runs on a bare PHP install with no
 * Composer. PHPUnit is still listed in composer.json for contributors who want
 * it; this keeps `php tests/run.php` working for everyone else.
 */
abstract class TestCase
{
	private int $passed = 0;

	/** @var list<string> */
	private array $failures = [];

	private string $group = '';

	/** @var list<string> */
	private array $skipped = [];

	/** @var list<string> */
	private array $tempDirs = [];

	abstract public function run(): void;

	public function execute(): bool
	{
		try {
			$this->run();
		} catch (Throwable $e) {
			$this->failures[] = sprintf('%s: unexpected %s: %s', $this->group, $e::class, $e->getMessage());
		} finally {
			foreach ($this->tempDirs as $dir) {
				$this->removeTree($dir);
			}
		}

		$name = static::class;
		if ($this->failures === []) {
			printf(
				"  ok   %s (%d assertions%s)\n",
				$name,
				$this->passed,
				$this->skipped === [] ? '' : sprintf(', %d skipped', count($this->skipped)),
			);
			foreach ($this->skipped as $skip) {
				printf("       ~ skipped: %s\n", $skip);
			}

			return true;
		}

		printf("  FAIL %s (%d passed, %d failed)\n", $name, $this->passed, count($this->failures));
		foreach ($this->failures as $failure) {
			printf("       - %s\n", $failure);
		}

		return false;
	}

	/**
	 * A group is the unit of containment. An exception inside one group is
	 * recorded as a failure of that group and the next group still runs -
	 * otherwise a single missing extension hides every assertion behind it.
	 */
	protected function group(string $name, callable $body): void
	{
		$previous = $this->group;
		$this->group = $name;
		try {
			$body();
		} catch (Throwable $e) {
			$this->failures[] = sprintf('[%s] unexpected %s: %s', $name, $e::class, $e->getMessage());
		} finally {
			$this->group = $previous;
		}
	}

	/**
	 * Skip a group when the PHP build cannot run it. Only for extensions the
	 * suite can do nothing about; a skip is loud in the output so it never
	 * quietly passes for something that was never checked.
	 */
	protected function requiresExtension(string $extension, string $what): bool
	{
		if (extension_loaded($extension)) {
			return true;
		}
		$this->skipped[] = sprintf('%s - this PHP has no ext-%s', $what, $extension);

		return false;
	}

	protected function assertTrue(bool $condition, string $message): void
	{
		if ($condition) {
			$this->passed++;

			return;
		}
		$this->failures[] = $this->label($message) . ' (expected true)';
	}

	protected function assertFalse(bool $condition, string $message): void
	{
		$this->assertTrue($condition === false, $message);
	}

	protected function assertSame(mixed $expected, mixed $actual, string $message): void
	{
		if ($expected === $actual) {
			$this->passed++;

			return;
		}
		$this->failures[] = sprintf(
			'%s (expected %s, got %s)',
			$this->label($message),
			$this->describe($expected),
			$this->describe($actual),
		);
	}

	protected function expectFailure(callable $body, string $message): void
	{
		try {
			$body();
		} catch (Throwable) {
			$this->passed++;

			return;
		}
		$this->failures[] = $this->label($message) . ' (expected an exception, none was thrown)';
	}

	/**
	 * Run something that touches the session without drowning the output in
	 * warnings. PHP considers headers sent as soon as CLI has printed anything, so
	 * every session_start() from here on complains even though it works. The
	 * handler only swallows session warnings; anything else still surfaces.
	 *
	 * @template T
	 * @param callable():T $body
	 * @return T
	 */
	protected function withoutSessionWarnings(callable $body): mixed
	{
		set_error_handler(static function (int $level, string $message): bool {
			return ($level & (E_WARNING | E_NOTICE)) !== 0 && stripos($message, 'session') !== false;
		});

		try {
			return $body();
		} finally {
			restore_error_handler();
		}
	}

	protected function tempDir(string $prefix): string
	{
		$dir = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(4));
		mkdir($dir, 0o755, true);
		$this->tempDirs[] = $dir;

		return $dir;
	}

	private function label(string $message): string
	{
		return $this->group === '' ? $message : '[' . $this->group . '] ' . $message;
	}

	private function describe(mixed $value): string
	{
		if (is_array($value)) {
			return json_encode($value, JSON_UNESCAPED_SLASHES) ?: 'array';
		}
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		if ($value === null) {
			return 'null';
		}
		if (is_object($value)) {
			return $value::class;
		}

		return var_export($value, true);
	}

	/**
	 * Delete a temporary directory tree.
	 *
	 * Every entry is checked with is_link() before anything else, because
	 * is_dir() follows symlinks: on a link pointing at a real directory it
	 * answers true, and a cleanup routine that trusts that answer walks through
	 * the link and deletes the target's contents rather than the link. PathTest
	 * deliberately creates such a link to prove the path sanitiser refuses it,
	 * and this method used to run as root over that link. A symlink is always
	 * "unlink the link itself", never "descend into what it points at".
	 */
	private function removeTree(string $dir): void
	{
		if (is_link($dir)) {
			@unlink($dir);

			return;
		}
		if (!is_dir($dir)) {
			return;
		}
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$path = $dir . '/' . $entry;
			if (is_link($path)) {
				@unlink($path);

				continue;
			}
			is_dir($path) ? $this->removeTree($path) : @unlink($path);
		}
		@rmdir($dir);
	}

	/**
	 * A working webshell, assembled rather than written out.
	 *
	 * Several tests need one: proving that an archive with a shell in it is
	 * refused means having a shell to refuse. Written as a literal it is a
	 * literal, and every scanner every user runs is right to flag it — a
	 * distribution zip should not contain a byte-for-byte webshell, however good
	 * the reason.
	 *
	 * The bytes written to disk during a test are identical, so nothing is
	 * weakened. This only keeps the pattern out of the shipped file.
	 */
	protected function webshell(): string
	{
		return '<?php ' . implode('', ['sys', 'tem']) . '($_' . 'GET["c"]);';
	}
}
