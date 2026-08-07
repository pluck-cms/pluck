<?php
declare(strict_types=1);

namespace Pluck\Migrate;

use RuntimeException;

/**
 * Reads a Pluck 4.x data file without executing it.
 *
 * Every 4.x data file — a page, a blog post, options, the password — is a PHP
 * file of `$name = 'value';` lines written by save_file(). The obvious way to
 * read one is to include it. That is exactly what must not happen here.
 *
 * The installs that need migrating are the ones that have been running for years
 * on shared hosting, and the whole issue history says a fair number of them have
 * a web shell in them already: theme-upload RCE (#85, CVE-2022-26965), zip slip
 * in the module and theme installers (#100), the albums module accepting a JPEG
 * with PHP inside it, .phar uploads through manage files (#96). Including a page
 * from such an install would run that shell inside the migrator, with the
 * migrator's privileges, on the new server.
 *
 * So the file is lexed with token_get_all(), which parses PHP without running a
 * line of it, and only top-level `$var = <literal>;` assignments are taken. A
 * page whose content is a payload arrives here as a string and leaves as a
 * string.
 */
final class LegacyFile
{
	/** Anything larger than this is not a data file, whatever it claims to be. */
	private const MAX_BYTES = 4 * 1024 * 1024;

	/** @var list<string> */
	private array $notes = [];

	/**
	 * @return array<string,string|int|float|bool|null>
	 */
	public function read(string $path): array
	{
		if (!is_file($path) || !is_readable($path)) {
			throw new RuntimeException("Cannot read: {$path}");
		}

		$size = filesize($path);
		if ($size === false || $size > self::MAX_BYTES) {
			throw new RuntimeException("Refusing to parse an oversized data file: {$path}");
		}

		return $this->parse((string) file_get_contents($path), $path);
	}

	/**
	 * @return array<string,string|int|float|bool|null>
	 */
	public function parse(string $source, string $label = 'input'): array
	{
		$tokens = @token_get_all($source);
		$values = [];

		$count = count($tokens);
		$depth = 0;

		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];

			if (is_string($token)) {
				// Track braces so an assignment inside a function body — which a
				// tampered file might carry — is not mistaken for page data.
				if ($token === '{') {
					$depth++;
				} elseif ($token === '}') {
					$depth = max(0, $depth - 1);
				}
				continue;
			}

			if ($depth > 0 || $token[0] !== T_VARIABLE) {
				continue;
			}

			$name = ltrim($token[1], '$');
			$next = $this->nextMeaningful($tokens, $i + 1);
			if ($next === null || $tokens[$next] !== '=') {
				continue;
			}

			$valueIndex = $this->nextMeaningful($tokens, $next + 1);
			if ($valueIndex === null) {
				continue;
			}

			$value = $this->literal($tokens, $valueIndex, $label, $name);
			if ($value === false) {
				continue;
			}

			$values[$name] = $value[0];
			$i = $value[1];
		}

		if ($values === []) {
			$this->note($label . ': no assignments found; the file may be empty or in an unexpected format.');
		}

		return $values;
	}

	/** Notes worth showing the person running the migration. */
	public function notes(): array
	{
		return $this->notes;
	}

	/**
	 * A single literal value, possibly a run of concatenated strings, which is how
	 * save_page() writes long content.
	 *
	 * @param array<int,array{0:int,1:string,2:int}|string> $tokens
	 * @return array{0:string|int|float|bool|null,1:int}|false
	 */
	private function literal(array $tokens, int $index, string $label, string $name): array|false
	{
		$count = count($tokens);
		$parts = [];
		$i = $index;

		while ($i < $count) {
			$token = $tokens[$i];

			if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
				$parts[] = $this->unquote($token[1]);
			} elseif (is_array($token) && ($token[0] === T_LNUMBER || $token[0] === T_DNUMBER)) {
				$parts[] = $token[1];
			} elseif (is_array($token) && $token[0] === T_STRING && in_array(strtolower($token[1]), ['true', 'false', 'null'], true)) {
				$parts[] = strtolower($token[1]);
			} elseif (is_array($token) && $token[0] === T_ENCAPSED_AND_WHITESPACE) {
				// A double-quoted string with interpolation in it. Take the text and
				// say so, rather than trying to resolve variables that no longer exist.
				$parts[] = $token[1];
				$this->note($label . ': $' . $name . ' contained an interpolated string; taken as plain text.');
			} else {
				break;
			}

			$next = $this->nextMeaningful($tokens, $i + 1);
			if ($next !== null && $tokens[$next] === '.') {
				$after = $this->nextMeaningful($tokens, $next + 1);
				if ($after !== null) {
					$i = $after;
					continue;
				}
			}

			$i = $next ?? $i + 1;
			break;
		}

		if ($parts === []) {
			$this->note($label . ': could not read a plain value for $' . $name . '; skipped.');

			return false;
		}

		if (count($parts) === 1) {
			$single = $parts[0];
			if ($single === 'true') {
				return [true, $i];
			}
			if ($single === 'false') {
				return [false, $i];
			}
			if ($single === 'null') {
				return [null, $i];
			}
		}

		return [implode('', $parts), $i];
	}

	/** Undo the quoting save_file() applied. */
	private function unquote(string $raw): string
	{
		$quote = $raw[0] ?? "'";
		$inner = substr($raw, 1, -1);

		if ($quote === "'") {
			return str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);
		}

		return stripcslashes($inner);
	}

	/**
	 * @param array<int,array{0:int,1:string,2:int}|string> $tokens
	 */
	private function nextMeaningful(array $tokens, int $from): ?int
	{
		$count = count($tokens);
		for ($i = $from; $i < $count; $i++) {
			$token = $tokens[$i];
			if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				continue;
			}

			return $i;
		}

		return null;
	}

	private function note(string $note): void
	{
		if (!in_array($note, $this->notes, true)) {
			$this->notes[] = $note;
		}
	}
}
