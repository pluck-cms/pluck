<?php
declare(strict_types=1);

namespace Pluck\Archive;

/**
 * Whether one entry in an archive may be written, and why not.
 *
 * This is deliberately a pure function over a name and a few numbers, with no
 * ZipArchive in sight. Pluck 4's theme and module installers were exploited
 * repeatedly through the same door — #85 and CVE-2022-26965 (a theme whose
 * theme.php is a shell), #100 (zip slip writing outside the target),
 * modules_install.php with a crafted zip — and the rules that would have stopped
 * all of them are exactly the rules below. Keeping them separable means they can
 * be tested exhaustively on any PHP build, including one without the zip
 * extension, which is the whole reason this is its own class.
 *
 * The design decision underneath it: themes are data, not code. A feature that
 * unpacks attacker-supplied PHP into the document root is remote code execution
 * by definition, so v5 does not have one. Modules, which really are PHP, are
 * installed by putting files on the server over FTP or SSH.
 */
final class EntryPolicy
{
	/** What a theme may legitimately contain. */
	public const THEME_EXTENSIONS = [
		'html', 'htm', 'css', 'js', 'mjs', 'json', 'svg', 'txt', 'md',
		'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
		'woff', 'woff2', 'ttf', 'otf', 'eot',
	];

	/**
	 * Never written, whatever the allow-list says. The allow-list is the rule;
	 * this is the assertion that nobody widened the rule by accident.
	 */
	public const NEVER = [
		'php', 'phtml', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'phar',
		'pht', 'inc', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'so', 'dll',
		'htaccess', 'htpasswd', 'ini', 'conf', 'user',
	];

	public const MAX_ENTRIES = 2000;
	public const MAX_TOTAL_BYTES = 32 * 1024 * 1024;
	public const MAX_ENTRY_BYTES = 8 * 1024 * 1024;
	public const MAX_RATIO = 200;
	public const MAX_DEPTH = 8;

	/**
	 * @param list<string> $allowedExtensions
	 * @param string $subject what is being judged, used in the refusal messages.
	 *        The same rules guard theme archives and migrated uploads, but telling
	 *        someone "themes are templates, not code" about a file in their old
	 *        files/ folder is just confusing.
	 */
	public function __construct(
		private readonly array $allowedExtensions = self::THEME_EXTENSIONS,
		private readonly string $subject = 'theme',
	) {
	}

	/**
	 * A sentence explaining the refusal, or null when the entry is acceptable.
	 * Directory entries return null and write nothing.
	 */
	public function judge(string $name, int $size = 0, int $packedSize = 0, bool $isSymlink = false): ?string
	{
		if ($name === '' || str_contains($name, "\0")) {
			return 'An entry has an unusable name.';
		}

		$unix = str_replace('\\', '/', $name);

		if ($isSymlink) {
			return sprintf('"%s" is a symbolic link.', $name);
		}

		if (str_starts_with($unix, '/') || preg_match('#^[a-zA-Z]:#', $unix) === 1) {
			return sprintf('"%s" is an absolute path.', $name);
		}

		foreach (explode('/', $unix) as $segment) {
			if ($segment === '..') {
				return sprintf('"%s" tries to write outside its own folder.', $name);
			}
		}

		if (substr_count(rtrim($unix, '/'), '/') > self::MAX_DEPTH) {
			return sprintf('"%s" is nested too deeply.', $name);
		}

		if (str_ends_with($unix, '/')) {
			return null;
		}

		$basename = basename($unix);
		$extension = strtolower(pathinfo($unix, PATHINFO_EXTENSION));

		if (str_starts_with($basename, '.') || $extension === '') {
			return sprintf('"%s" has no usable extension.', $name);
		}

		/*
		 * Every dotted part after the first, not just the last one. "shell.php.css"
		 * is served as PHP by an Apache with a stray AddHandler, and an .htaccess
		 * inside an archive is how an attacker switches PHP back on in a folder
		 * where it was switched off.
		 */
		foreach (array_slice(explode('.', strtolower($basename)), 1) as $part) {
			if (in_array($part, self::NEVER, true)) {
				return sprintf(
					'"%s" contains an executable part (.%s), which a %s must never carry.',
					$name,
					$part,
					$this->subject,
				);
			}
		}

		if (!in_array($extension, $this->allowedExtensions, true)) {
			return sprintf('"%s" is a .%s file, which a %s does not need.', $name, $extension, $this->subject);
		}

		if ($size > self::MAX_ENTRY_BYTES) {
			return sprintf('"%s" unpacks to %s, over the per-file limit.', $name, self::humanBytes($size));
		}

		if ($packedSize > 0 && $size / $packedSize > self::MAX_RATIO) {
			return sprintf('"%s" is compressed %dx, which is what a zip bomb looks like.', $name, (int) ($size / $packedSize));
		}

		return null;
	}

	public static function humanBytes(int $bytes): string
	{
		if ($bytes >= 1048576) {
			return round($bytes / 1048576, 1) . ' MB';
		}

		return max(1, (int) round($bytes / 1024)) . ' kB';
	}
}
