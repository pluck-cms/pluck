<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\I18n\Locale;
use Pluck\I18n\Plural;

/**
 * Keeps the translation catalogues honest.
 *
 * A flat, dotted key namespace was chosen precisely so this test could exist:
 * every key the code asks for is read out of the source, every key each file
 * defines is read out of the JSON, and both sides of the difference fail. Without
 * it, translations rot in the two usual ways — a new string nobody translated, and
 * a translated string nobody uses any more — and neither shows up until someone
 * switches language.
 *
 * It also carries the ratchet for the strings still hardcoded in views. That
 * number may only go down.
 */
final class CatalogueTest extends TestCase
{
	/**
	 * Keys the scanner cannot see, because the code builds them rather than
	 * writing them out.
	 *
	 * Every entry here is a hole in the check, so the list is short and each one
	 * says where it is used. The alternative — a scanner clever enough to follow
	 * a method that returns a key — would be a scanner nobody could predict, and
	 * the value of this test is that its answer is obvious.
	 */
	/**
	 * Prefixes whose keys are all chosen at runtime.
	 *
	 * Diagnostics builds a label, a value and a piece of advice per row, none of
	 * which appears as a literal anywhere — listing forty keys by hand here would
	 * be a second copy of that screen, kept in step by nobody.
	 *
	 * @var list<string>
	 */
	private const DYNAMIC_PREFIXES = ['diagnostics.'];

	private const DYNAMIC_KEYS = [
		// ReactionStatus::labelKey(), rendered as $view->t($status->labelKey())
		'blog.reaction_status.pending',
		'blog.reaction_status.approved',
		'blog.reaction_status.spam',
		// AdminModule::navigation() returns a label key, translated in the layout
		'blog.nav.blog',
		'albums.nav.albums',
		// SearchResult::$kindKey, rendered as $view->t($result->kindKey)
		'search.kind.page', 'search.kind.post', 'search.kind.album',
		// Guard::word(), built as 'form.number.' . $n
		'form.number.0', 'form.number.1', 'form.number.2', 'form.number.3', 'form.number.4',
		'form.number.5', 'form.number.6', 'form.number.7', 'form.number.8', 'form.number.9',
		// Returned as keys by Guard and by PublicForm::accept(), and rendered by
		// index.php from whatever the submission produced.
		'form.error.start_again', 'form.error.too_fast', 'form.error.too_slow',
		'form.error.too_many', 'form.error.wrong_answer', 'form.error.fill_it_in',
		'form.error.bad_email', 'form.error.captcha_missing', 'form.error.captcha_failed',
		'form.error.captcha_not_configured',
		'blog.reaction.posted', 'blog.reaction.waiting', 'blog.reaction.closed',
		'contact.label.subject',
		// Every diagnostics row names its label, value and advice as keys
		'diagnostics.present', 'diagnostics.missing', 'diagnostics.yes', 'diagnostics.no',
		'diagnostics.on', 'diagnostics.off', 'diagnostics.unlimited', 'diagnostics.check_yourself',
		'diagnostics.whose.you', 'diagnostics.whose.host',
		// Chosen in the settings form from a list of challenge kinds
		'settings.challenge.sum', 'settings.challenge.recaptcha', 'settings.challenge.none',
		// Backup::reason(), rendered as $view->t('backup.reason.' . $reason)
		'backup.reason.manual', 'backup.reason.scheduled',
		'backup.reason.before_restore', 'backup.reason.before_update',
		// Permissions::labelKey() and Permissions::groups(), rendered as
		// $view->t($row['label']) and $view->t($group)
		'acl.group.pages', 'acl.group.media', 'acl.group.people', 'acl.group.site',
		'acl.group.modules',
		'acl.permission.page_view', 'acl.permission.page_create', 'acl.permission.page_edit',
		'acl.permission.page_edit_own', 'acl.permission.page_delete', 'acl.permission.page_delete_own',
		'acl.permission.file_view', 'acl.permission.file_upload', 'acl.permission.file_delete',
		'acl.permission.user_view', 'acl.permission.user_create', 'acl.permission.user_edit',
		'acl.permission.user_delete', 'acl.permission.settings_view', 'acl.permission.settings_edit',
		'acl.permission.theme_view', 'acl.permission.update_run',
		// Chosen in the layout depending on whether an update is known about
		'nav.updates', 'nav.updates_available',
		'acl.permission.module_blog_manage', 'acl.permission.module_albums_manage',
	];

	/**
	 * Literal words still sitting in view templates instead of a catalogue.
	 * Measured, not estimated. Lower this as views are converted; it must never
	 * rise, which is what stops the admin drifting back to English-only one
	 * template at a time.
	 */
	private const LITERAL_WORDS_IN_VIEWS = 95;

	public function run(): void
	{
		$root = dirname(__DIR__);
		$en = $this->catalogue($root . '/lang/en.json');

		$this->group('english is the source of truth', function () use ($root, $en): void {
			$this->assertTrue($en !== [], 'lang/en.json exists and parses');

			$used = array_values(array_unique([...$this->keysUsedInCode($root), ...self::DYNAMIC_KEYS]));
			$this->assertTrue($used !== [], 'keys were found in the source');

			$undefined = array_values(array_diff($used, array_keys($en)));
			$this->assertSame([], $undefined, 'every key the code uses is defined in english');

			$unused = array_values(array_filter(
				array_diff(array_keys($en), $used),
				static function (string $key): bool {
					foreach (self::DYNAMIC_PREFIXES as $prefix) {
						if (str_starts_with($key, $prefix)) {
							return false;
						}
					}

					return true;
				},
			));
			$this->assertSame([], $unused, 'every key defined in english is actually used');
		});

		$this->group('other locales', function () use ($root, $en): void {
			foreach (glob($root . '/lang/*.json') ?: [] as $file) {
				$code = basename($file, '.json');
				if ($code === 'en') {
					continue;
				}

				$locale = Locale::tryFrom($code);
				$this->assertTrue($locale !== null, $code . ' is a valid locale code');
				if ($locale === null) {
					continue;
				}

				$catalogue = $this->catalogue($file);

				// A key here that english does not have is either a typo or a
				// leftover; either way it can never be reached.
				$orphans = array_values(array_diff(array_keys($catalogue), array_keys($en)));
				$this->assertSame([], $orphans, $code . ': no keys that english does not have');

				foreach ($catalogue as $key => $value) {
					// Plural sets have to match how english structured the string,
					// otherwise a count renders the wrong form or nothing at all.
					$this->assertSame(
						is_array($en[$key] ?? null),
						is_array($value),
						$code . ': ' . $key . ' has the same shape as english',
					);

					if (is_array($value)) {
						$expected = Plural::categoriesFor($locale->language);
						if ($expected !== null) {
							$missing = array_values(array_diff($expected, array_keys($value)));
							$this->assertSame([], $missing, $code . ': ' . $key . ' covers every plural form this language uses');
						}
					}

					// An invented placeholder renders literally as "{foo}".
					$theirs = $this->placeholders($value);
					$ours = $this->placeholders($en[$key] ?? '');
					$extra = array_values(array_diff($theirs, $ours));
					$this->assertSame([], $extra, $code . ': ' . $key . ' invents no placeholders');
				}
			}
		});

		$this->group('flash messages go through the catalogue', function () use ($root): void {
			// Views have a ratchet because converting them is a long job. Flash
			// messages are a closed set and are all converted, so this one is not a
			// ceiling but a floor: nothing new may be written in english here.
			$offenders = [];
			foreach ($this->sourceFiles($root) as $file) {
				$source = (string) file_get_contents($file);
				if (preg_match_all('/flash->(?:ok|warn|stop|note)\(\s*[\'"]/', $source, $m) > 0) {
					$offenders[] = basename($file) . ' (' . count($m[0]) . ')';
				}
			}
			sort($offenders);

			$this->assertSame([], $offenders, 'no flash message is written as a literal string');
		});

		$this->group('the admin script has no prose of its own', function () use ($root): void {
			// Anything the browser says to a person is written by the template,
			// which has the translator, and reaches the script through a data
			// attribute. A literal here would be untranslatable for good.
			$offenders = [];
			foreach (glob($root . '/assets/admin/*.js') ?: [] as $file) {
				$source = (string) file_get_contents($file);
				if (preg_match_all('/window\.(?:alert|prompt|confirm)\(\s*[\'"]/', $source, $m) > 0) {
					$offenders[] = basename($file) . ' (' . count($m[0]) . ')';
				}
			}
			sort($offenders);

			$this->assertSame([], $offenders, 'no message to the user is hardcoded in javascript');
		});

		$this->group('the ratchet on hardcoded view text', function () use ($root): void {
			$count = $this->literalWordsInViews($root);

			$this->assertTrue(
				$count <= self::LITERAL_WORDS_IN_VIEWS,
				sprintf(
					'views hold %d literal words, and the agreed ceiling is %d — a new hardcoded string was added',
					$count,
					self::LITERAL_WORDS_IN_VIEWS,
				),
			);

			// Nudge, not a failure: when the real number drops, the constant should
			// follow so the ceiling keeps meaning something.
			if ($count < self::LITERAL_WORDS_IN_VIEWS - 20) {
				printf(
					"       note: views are down to %d literal words; lower LITERAL_WORDS_IN_VIEWS from %d.\n",
					$count,
					self::LITERAL_WORDS_IN_VIEWS,
				);
			}
		});
	}

	/** @return array<string,mixed> */
	private function catalogue(string $file): array
	{
		if (!is_file($file)) {
			return [];
		}

		$decoded = json_decode((string) file_get_contents($file), true);

		return is_array($decoded) ? $decoded : [];
	}

	/** @return list<string> */
	private function keysUsedInCode(string $root): array
	{
		$keys = [];

		foreach ($this->sourceFiles($root) as $file) {
			$source = (string) file_get_contents($file);

			/*
			 * $this->t('x'), $view->t('x'), $t('x') and translator()->get('x').
			 * Deliberately not a bare "->get(", which also matches $router->get() in
			 * the route table and reports every route name as a missing translation.
			 */
			// `?->` counts as a call: a nullsafe translator is still a translator,
			// and not matching that spelling made keys used in SiteRenderer look
			// dead, which invites someone to delete a string the 404 page needs.
			if (preg_match_all("/(?:\??->t|translator\(\)\??->get|translator\??->get|\\\$t)\(\s*'([a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+)'/i", $source, $m) > 0) {
				foreach ($m[1] as $key) {
					$keys[$key] = true;
				}
			}

			/*
			 * The layout builds its navigation as an array of key names and calls
			 * $view->t() on them later, so those literals need picking up too. Limited
			 * to the nav. prefix on purpose: route names and permission strings
			 * ("page.save", "user.view") have exactly the same dotted shape, and a
			 * broader pattern reports every one of them as a missing translation.
			 */
			if (preg_match_all("/'(nav\.[a-z0-9_]+)'/", $source, $m) > 0) {
				foreach ($m[1] as $key) {
					$keys[$key] = true;
				}
			}
		}

		$list = array_keys($keys);
		sort($list);

		return $list;
	}

	/** @return list<string> */
	private function sourceFiles(string $root): array
	{
		$files = [];

		// Themes are scanned along with core: a bundled theme is the only place
		// some site.* strings are used, and without this the catalogue would call
		// them dead and invite someone to delete them.
		foreach (['src', 'views', 'themes'] as $directory) {
			if (!is_dir($root . '/' . $directory)) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS),
			);
			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$files[] = $file->getPathname();
				}
			}
		}

		foreach (['admin.php', 'install.php', 'index.php'] as $entry) {
			if (is_file($root . '/' . $entry)) {
				$files[] = $root . '/' . $entry;
			}
		}

		return $files;
	}

	/** @param mixed $value @return list<string> */
	private function placeholders(mixed $value): array
	{
		$text = is_array($value) ? implode(' ', array_map('strval', $value)) : (string) $value;
		preg_match_all('/\{([a-z0-9_]+)\}/i', $text, $m);

		$names = array_values(array_unique($m[1]));
		sort($names);

		return $names;
	}

	/** Words of prose sitting between tags in a template, i.e. not translatable yet. */
	private function literalWordsInViews(string $root): int
	{
		$total = 0;

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root . '/views', \FilesystemIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}

			$source = (string) file_get_contents($file->getPathname());
			$source = preg_replace('/<\?(?:php|=).*?\?>/s', ' ', $source) ?? $source;
			$source = preg_replace('/<[^>]*>/', ' ', $source) ?? $source;

			preg_match_all("/[A-Za-z][A-Za-z'\x{2019}\-]{2,}/u", $source, $m);
			$total += count($m[0]);
		}

		return $total;
	}
}
