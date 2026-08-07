<?php
declare(strict_types=1);

namespace Pluck\Migrate;

/**
 * What a migration found and did.
 *
 * Written to be read by a person who is about to point DNS at a new server, so
 * the interesting parts are the ones that need a decision: what got renamed, what
 * content the sanitiser changed, which uploads were refused, which modules have no
 * home yet.
 */
final class MigrationReport
{
	public ?string $legacyVersion = null;
	public ?string $ownerUsername = null;
	public ?string $ownerPassword = null;

	/** @var list<array{from:string,to:string,hidden:bool}> */
	public array $pages = [];

	/** @var array<string,string> stored name => original relative path */
	public array $uploads = [];

	/** @var array<string,string> original relative path => the name it now shares */
	public array $duplicateUploads = [];

	/** @var list<array{from:string,to:string,reactions:int}> */
	public array $blogPosts = [];

	/** @var array<string,string> old category seoname => new slug */
	public array $blogCategories = [];

	public int $blogReactions = 0;

	/** @var list<array{from:string,to:string,images:int}> */
	public array $albums = [];

	/** @var array<string,string> */
	public array $settings = [];

	/** @var array<string,string> module => what happens to it */
	public array $modules = [];

	/** @var list<string> */
	public array $themes = [];

	/** @var array<string,list<string>> page => module variable names kept */
	public array $moduleData = [];

	/** @var array<string,string> path => what the sanitiser took out */
	public array $sanitisedPages = [];

	/** @var list<array{path:string,reason:string}> */
	public array $refusedUploads = [];

	/** @var list<array{path:string,reason:string}> */
	public array $skipped = [];

	/** @var list<string> */
	public array $notes = [];

	/** @var list<string> */
	public array $failures = [];

	public function __construct(public readonly bool $dryRun)
	{
	}

	public function fail(string $message): void
	{
		$this->failures[] = $message;
	}

	public function note(string $note): void
	{
		if (!in_array($note, $this->notes, true)) {
			$this->notes[] = $note;
		}
	}

	public function sanitised(string $path, string $what = ''): void
	{
		$this->sanitisedPages[$path] = $what;
	}

	public function refusedUpload(string $path, string $reason): void
	{
		$this->refusedUploads[] = ['path' => $path, 'reason' => $reason];
	}

	public function skip(string $path, string $reason): void
	{
		$this->skipped[] = ['path' => $path, 'reason' => $reason];
	}

	public function ok(): bool
	{
		return $this->failures === [];
	}

	/**
	 * The redirect map, as rules for the new site. Only entries where the address
	 * actually changed are useful here.
	 *
	 * @return array<string,string>
	 */
	public function redirectMap(): array
	{
		$map = [];
		foreach ($this->pages as $page) {
			if ($page['from'] !== $page['to']) {
				$map[$page['from']] = $page['to'];
			}
		}

		// Blog posts and albums are addresses too. Only the renamed ones belong
		// here: a post that kept its name needs no rule, and listing all of them
		// would bury the handful that do in a wall of identical lines.
		foreach ([...$this->blogPosts, ...$this->albums] as $entry) {
			if ($entry['from'] !== $entry['to']) {
				$map[$entry['from']] = $entry['to'];
			}
		}

		return $map;
	}

	public function toText(): string
	{
		$out = [];
		$out[] = $this->dryRun ? '=== Migration plan (nothing was written) ===' : '=== Migration complete ===';
		$out[] = '';

		if ($this->legacyVersion !== null) {
			$out[] = 'Old install: pluck ' . $this->legacyVersion;
		}

		if ($this->failures !== []) {
			$out[] = '';
			$out[] = 'STOPPED:';
			foreach ($this->failures as $failure) {
				$out[] = '  ' . $failure;
			}

			return implode("\n", $out) . "\n";
		}

		$out[] = sprintf('Pages: %d', count($this->pages));
		$renamed = $this->redirectMap();
		$out[] = sprintf('Addresses changed: %d', count($renamed));
		$out[] = sprintf('Uploads copied: %d', count($this->uploads));
		$out[] = sprintf('Uploads refused: %d', count($this->refusedUploads));

		if ($this->duplicateUploads !== []) {
			$out[] = sprintf(
				'Identical copies not carried twice: %d',
				count($this->duplicateUploads),
			);
		}
		$out[] = sprintf('Content the sanitiser changed: %d', count($this->sanitisedPages));

		if ($this->blogPosts !== [] || $this->blogCategories !== []) {
			$out[] = sprintf(
				'Blog: %d posts, %d categories, %d reactions',
				count($this->blogPosts),
				count($this->blogCategories),
				$this->blogReactions,
			);
		}

		if ($this->albums !== []) {
			$images = array_sum(array_column($this->albums, 'images'));
			$out[] = sprintf('Albums: %d, holding %d pictures', count($this->albums), $images);
		}

		if ($this->ownerUsername !== null) {
			$out[] = '';
			$out[] = '--- Sign in ---';
			$out[] = '  username: ' . $this->ownerUsername;
			$out[] = '  password: ' . (string) $this->ownerPassword;
			$out[] = '  Shown once. You will be asked to change it on first sign-in.';
		}

		if ($renamed !== []) {
			$out[] = '';
			$out[] = '--- Addresses that changed (set up redirects) ---';
			foreach ($renamed as $from => $to) {
				$out[] = sprintf('  /%s  ->  /%s', $from, $to);
			}
		}

		if ($this->sanitisedPages !== []) {
			$out[] = '';
			$out[] = '--- Content the sanitiser changed ---';
			$out[] = '  Markup the allow-list does not accept was taken out of these. Reformatting';
			$out[] = '  is not listed here — only content that was actually removed, with what it';
			$out[] = '  was. A script tag or an event handler in this list is worth understanding';
			$out[] = '  before you decide the old site was fine.';
			foreach ($this->sanitisedPages as $path => $what) {
				$out[] = $what === '' ? '  /' . $path : sprintf('  /%s — %s', $path, $what);
			}
		}

		if ($this->refusedUploads !== []) {
			$out[] = '';
			$out[] = '--- Uploads not copied ---';
			$out[] = '  These were in the old install and were left behind. An executable file here';
			$out[] = '  is worth a closer look: it may be a leftover from an old exploit rather than';
			$out[] = '  something anyone uploaded on purpose.';
			foreach ($this->refusedUploads as $refused) {
				$out[] = sprintf('  %s — %s', $refused['path'], $refused['reason']);
			}
		}

		if ($this->moduleData !== []) {
			$out[] = '';
			$out[] = '--- Module data kept on pages ---';
			foreach ($this->moduleData as $path => $keys) {
				$out[] = sprintf('  /%s: %s', $path, implode(', ', $keys));
			}
		}

		if ($this->modules !== []) {
			$out[] = '';
			$out[] = '--- Modules in the old install ---';
			foreach ($this->modules as $module => $what) {
				$out[] = sprintf('  %s — %s', $module, $what);
			}
		}

		if ($this->themes !== []) {
			$out[] = '';
			$out[] = '--- Themes in the old install ---';
			$out[] = '  ' . implode(', ', $this->themes);
		}

		if ($this->skipped !== []) {
			$out[] = '';
			$out[] = '--- Skipped ---';
			foreach ($this->skipped as $skip) {
				$out[] = sprintf('  %s — %s', $skip['path'], $skip['reason']);
			}
		}

		if ($this->notes !== []) {
			$out[] = '';
			$out[] = '--- Notes ---';
			foreach ($this->notes as $note) {
				$out[] = '  ' . $note;
			}
		}

		return implode("\n", $out) . "\n";
	}
}
