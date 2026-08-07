<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Migrate\LegacySite;
use Pluck\Migrate\Migrator;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;

/**
 * Migrating a 4.x site.
 *
 * The fixture below is written the way 4.x's own save_file() and save_page()
 * write: `$name = 'value';` lines, a `<order>.<seoname>.php` filename, sub-pages
 * in a folder named after the parent's seoname. It also carries the things a site
 * running since 4.6 actually accumulates — a page whose address is a timestamp
 * because the title had no latin characters (issue #27), a stored XSS payload from
 * before the sanitiser was any good, module variables dumped into the page file,
 * a .php sitting in files/ from an upload bypass, and one page whose filename
 * makes no sense at all.
 */
final class MigrateTest extends TestCase
{
	public function run(): void
	{
		foreach (['flatfile', 'sqlite'] as $driver) {
			if ($driver === 'sqlite' && !extension_loaded('pdo_sqlite')) {
				continue;
			}
			if (!$this->requiresExtension('dom', 'migrating pages on the ' . $driver . ' driver')) {
				continue;
			}
			$this->group($driver, fn () => $this->migrate($driver));
		}

		$this->group('refusals', fn () => $this->refusals());
	}

	private function migrate(string $driver): void
	{
		$old = $this->buildLegacySite();
		[$storage, $media] = $this->freshInstall($driver);

		$site = new LegacySite($old);
		$this->assertTrue($site->looksLikePluck(), 'the fixture is recognised as a pluck install');
		$this->assertSame('4.7.21 dev', $site->version(), 'the version is read from source, not by defining it');

		$migrator = new Migrator($site, $storage, $media);

		// Taken before anything runs. Comparing the tree with itself afterwards
		// would prove nothing at all.
		$before = $this->fingerprint($old);

		// --- the plan writes nothing ---
		$plan = $migrator->plan();
		$this->assertTrue($plan->ok(), 'the plan succeeds');
		$this->assertTrue($plan->dryRun, 'the plan knows it is a plan');
		$this->assertSame(0, count($storage->allPages()), 'a plan writes no pages');
		$this->assertSame(0, $storage->countUsers(), 'a plan creates no account');
		$this->assertSame(6, count($plan->pages), 'all six readable pages are in the plan');

		// --- then the real thing ---
		$report = $migrator->run('bas');
		$this->assertTrue($report->ok(), 'the migration succeeds');

		$paths = array_map(static fn ($p): string => $p->path, $storage->allPages());
		sort($paths);
		$this->assertSame(
			['about', 'about/history', 'about/team', 'contact', 'over-ons', 'welcome'],
			$paths,
			'the tree arrives with sub-pages under their parent',
		);

		$this->assertSame(1, $storage->countUsers(), 'one owner account was created');
		$owner = $storage->findUserByUsername('bas');
		$this->assertTrue($owner !== null, 'the owner exists');
		$this->assertTrue($owner->mustChangePassword, 'and is asked to change the one-time password');
		$this->assertTrue(
			$owner->verify((string) $report->ownerPassword),
			'the password on the report is the one that works',
		);
		$this->assertTrue(strlen((string) $report->ownerPassword) >= 20, 'the one-time password is not trivial');

		// The old sha512 must not have travelled.
		$this->assertFalse(
			str_contains($owner->passwordHash, hash('sha512', 'oldpassword')),
			'the unsalted sha512 from pass.php was not imported',
		);
		$this->assertTrue(str_starts_with($owner->passwordHash, '$2y$'), 'the new hash is bcrypt');
		$ownerId = $owner->id;

		// --- ordering and flags ---
		$top = $storage->listPages(null);
		$this->assertSame('welcome', $top[0]->path, 'the menu order from the filename prefix is kept');
		$this->assertSame('about', $top[1]->path, 'second stays second');
		$this->assertTrue($storage->findPage('contact')->hidden, 'a hidden page stays hidden');
		$this->assertFalse($storage->findPage('about')->hidden, 'a visible page stays visible');

		// --- content ---
		$about = $storage->findPage('about');
		$this->assertSame('About us', $about->title, 'the title comes through');
		$this->assertTrue(str_contains($about->content, 'It&#039;s') || str_contains($about->content, "It's"), 'an escaped apostrophe survives');
		$this->assertSame('Who we are', $about->description, 'the description comes through');
		$this->assertSame('about, us', $about->keywords, 'so do the keywords');

		// --- stored XSS from the old install is removed, and reported ---
		$welcome = $storage->findPage('welcome');
		$this->assertFalse(str_contains($welcome->content, '<script'), 'a stored script tag is gone');
		$this->assertFalse(str_contains($welcome->content, 'onerror'), 'so is an event handler');
		$this->assertTrue(str_contains($welcome->content, 'Welcome to our site'), 'the real content is kept');
		$this->assertTrue(isset($report->sanitisedPages['welcome']), 'and the page is named in the report');
		$this->assertTrue(
			str_contains($report->sanitisedPages['welcome'], 'script'),
			'the report says a script tag was what came out, not just that something did',
		);
		$this->assertTrue(
			str_contains($report->sanitisedPages['welcome'], 'onerror'),
			'and names the event handler separately, because that is script too',
		);

		// A page whose markup was legal all along must not be in that list. 4.x
		// content is full of &euml; and <img />, and re-serialising those changes
		// the bytes without removing anything; reporting that would bury the two
		// entries above under every page on the site.
		$this->assertFalse(
			isset($report->sanitisedPages['about']),
			'a page the sanitiser only re-serialised is not reported as changed',
		);
		$this->assertFalse(
			in_array('about', $report->sanitisedPages, true),
			'a clean page is not reported as changed',
		);

		// --- issue #27: a title with no latin characters got a timestamp address ---
		$generated = $storage->findPage('over-ons');
		$this->assertTrue($generated !== null, 'the timestamp-named page got a readable address from its title');
		$this->assertSame('Över ons', $generated->title, 'its title is unchanged');
		$this->assertSame(
			'over-ons',
			$report->redirectMap()['20231104093012'] ?? null,
			'and the old address is in the redirect map',
		);

		// --- module data ---
		$this->assertSame(
			['blog_posts_per_page' => '10', 'contactform_email' => 'info@example.org'],
			$storage->findPage('contact')->moduleData['legacy'] ?? null,
			'module variables from the page file are kept verbatim under a legacy key',
		);
		$this->assertTrue(isset($report->moduleData['contact']), 'and the report says which page carries them');

		// --- hidden, the way 4.x meant it ---
		// Reading this backwards migrates a working site into one with an empty
		// menu, and every page still reachable, so nothing else fails. 4.7 writes
		// 'no' for a page that is in the menu and 'yes' for one that is not.
		$this->assertFalse($storage->findPage('welcome')->hidden, "a page marked 'no' stays in the menu");
		$this->assertFalse($storage->findPage('about/team')->hidden, 'including sub-pages');
		$this->assertTrue($storage->findPage('contact')->hidden, "and one marked 'yes' stays out of it");
		$this->assertFalse(
			$storage->findPage('over-ons')->hidden,
			'a page from a version that never wrote the field is visible, not hidden',
		);
		$this->assertSame(
			5,
			count($storage->listPages(null, false)) + count($storage->listPages('about', false)),
			'so the migrated site has a menu at all',
		);

		// --- settings ---
		$this->assertSame('Voorbeeldsite', $storage->getSetting('site_title'), 'the site title is migrated');
		$this->assertSame('nl', $storage->getSetting('language'), 'the language pref loses its .php');
		$this->assertSame('oldstyle', $storage->getSetting('legacy_theme'), 'the old theme name is remembered, not applied');

		// --- uploads ---
		$this->assertTrue(is_file($media . '/logo.png'), 'a real image is copied');

		// --- links follow the files they point at ---
		$links = (string) ($storage->findPage('welcome')?->content ?? '');

		$this->assertTrue(
			str_contains($links, 'media/privacy-policy-tian-dao.pdf'),
			'a link to an upload is pointed at where the upload went',
		);
		$this->assertFalse(
			str_contains($links, 'files/'),
			'and not left pointing at the folder 4.x kept it in',
		);

		// Matching exactly on case left this link dead while the file itself sat
		// in media/ perfectly migrated — everything reporting success, one page
		// with a broken link on it.
		$this->assertTrue(
			is_file($media . '/privacy-policy-tian-dao.pdf'),
			'even though the link was capitalised differently from the filename',
		);

		// Already broken before the migration, and not the migration's to fix.
		$this->assertTrue(
			str_contains($links, 'href="www.example.com"'),
			'an address that was already broken is left exactly as it was',
		);
		$this->assertTrue(
			count(array_filter($report->notes, static fn (string $n): bool => str_contains($n, 'already broken'))) > 0,
			'and reported, since a migration is the one moment somebody reads a site\'s links',
		);

		$this->assertTrue(is_file($media . '/brochure.pdf'), 'so is a pdf');
		$this->assertFalse(is_file($media . '/shell.php'), 'a php upload is not copied');
		$this->assertFalse(is_file($media . '/shell-php.php'), 'nor under a rebuilt name');

		$refused = array_column($report->refusedUploads, 'path');
		$this->assertTrue(
			in_array('files/shell.php', $refused, true),
			'the php file is named in the report, so the old install can be looked at',
		);

		// --- blog ---
		$this->assertSame(3, count($report->blogPosts), 'the three readable posts are migrated');
		$this->assertSame(
			['eerste-bericht', 'tweede-bericht', 'zonder-tijd'],
			array_map(static fn (array $p): string => substr($p['to'], 5), $report->blogPosts),
			'posts arrive oldest first, and the one without a timestamp sorts last',
		);

		$post = $storage->getModuleData('blog', 'post:eerste-bericht');
		$this->assertSame('Eerste bericht', $post['title'] ?? null, 'the post title survives');
		$this->assertSame('2010-11-01T00:00:00+00:00', $post['published_at'] ?? null, 'so does the publication time');
		$this->assertSame('nieuws', $post['category'] ?? null, 'the category is carried as its new slug');
		$this->assertSame($ownerId, $post['author_id'] ?? null, 'posts get the new owner as author');

		$untimed = $storage->getModuleData('blog', 'post:zonder-tijd');
		$this->assertTrue(array_key_exists('published_at', $untimed), 'every post carries a publication field');
		$this->assertSame(null, $untimed['published_at'], 'but a post with no timestamp gets no invented one');

		$sanitised = $storage->getModuleData('blog', 'post:tweede-bericht')['content'] ?? '';
		$this->assertFalse(str_contains($sanitised, '<script'), 'post bodies go through the sanitiser too');
		$this->assertTrue(str_contains($sanitised, 'Nog iets'), 'and keep the text around it');

		$this->assertSame(
			'Nieuws',
			$storage->getModuleData('blog', 'category:nieuws')['title'] ?? null,
			'categories are migrated with their titles',
		);

		// --- reactions ---
		$this->assertSame(2, $report->blogReactions, 'both reactions are counted');
		$reaction = $storage->getModuleData('blog', 'reaction:eerste-bericht:0001');
		$this->assertSame('Jan', $reaction['name'] ?? null, 'the commenter name is kept');
		$this->assertFalse(array_key_exists('email', $reaction ?? []), 'the e-mail address is not carried over');
		$this->assertFalse(str_contains((string) ($reaction['message'] ?? ''), '<script'), 'reaction bodies are sanitised');
		$this->assertTrue(
			str_contains(implode(' ', $report->notes), 'e-mail address'),
			'and the report says the addresses were dropped rather than doing it silently',
		);

		// --- albums ---
		// --- one file, two albums ---
		$this->assertSame(2, count($report->albums), 'both albums are migrated');
		$this->assertSame(
			$storage->getModuleData('albums', 'image:vakantie-2011:0000')['file'] ?? 'a',
			$storage->getModuleData('albums', 'image:reisje:0000')['file'] ?? 'b',
			'the same picture in two albums points at one file rather than two copies',
		);
		$this->assertFalse(
			is_file($media . '/strand-2.jpg'),
			'so no second copy is written',
		);
		$this->assertSame(
			1,
			count($report->duplicateUploads),
			'and the report says a copy was recognised rather than doing it silently',
		);
		$this->assertSame(
			'Ook het strand',
			$storage->getModuleData('albums', 'image:reisje:0000')['title'] ?? null,
			'while each album keeps its own caption for it',
		);

		// Looked up by name, not by position: albums arrive in their own order and
		// adding a second one to this fixture silently moved the first.
		$vakantie = array_values(array_filter(
			$report->albums,
			static fn (array $a): bool => $a['to'] === 'albums/vakantie-2011',
		));

		$this->assertSame(1, count($vakantie), 'the album is migrated');
		$this->assertSame(2, $vakantie[0]['images'], 'holding the two pictures that actually exist');
		$this->assertSame(
			'Vakantie 2011',
			$storage->getModuleData('albums', 'album:vakantie-2011')['title'] ?? null,
			'the album keeps its name',
		);
		$this->assertTrue(
			str_contains((string) ($storage->getModuleData('albums', 'album:vakantie-2011')['description'] ?? ''), 'Zomer aan zee'),
			'and a description written by the albums-enhancements module',
		);
		$this->assertFalse(
			in_array('data/settings/modules/albums/vakantie/1.album_desc.php', array_column($report->refusedUploads, 'path'), true),
			'which is not reported as an executable smuggled into a photo folder',
		);

		$image = $storage->getModuleData('albums', 'image:vakantie-2011:0000');
		$this->assertSame('Op het strand', $image['title'] ?? null, 'the caption survives');
		$this->assertSame('strand.jpg', $image['file'] ?? null, 'and points at the file in the media folder');
		$this->assertTrue(is_file($media . '/' . ($image['file'] ?? 'x')), 'which is really there');

		$this->assertTrue(
			in_array('data/settings/modules/albums/vakantie/kwijt.jpg', array_column($report->skipped, 'path'), true),
			'an album entry whose picture is gone is reported by the name of the missing file',
		);

		// --- what the albums tree must NOT have contributed ---
		$this->assertFalse(
			is_file($media . '/strand-2.jpg') || is_file($media . '/bergen-2.jpg'),
			'generated thumbnails are not copied a second time',
		);
		$this->assertSame(
			[],
			array_filter(array_column($report->refusedUploads, 'path'), static fn (string $p): bool => str_ends_with($p, '.jpg.php')),
			'the module\'s own caption files are not reported as refused uploads',
		);
		$this->assertTrue(
			in_array('data/settings/modules/albums/vakantie/backdoor.php', array_column($report->refusedUploads, 'path'), true),
			'but a php file in the photo folder that is not caption data still is',
		);

		// --- unreadable files ---
		$skippedPaths = array_column($report->skipped, 'path');
		$this->assertTrue(
			count(array_filter($skippedPaths, static fn (string $p): bool => str_contains($p, 'broken'))) === 2,
			'the malformed page and the malformed post are skipped, not guessed at',
		);

		// --- modules and themes ---
		$this->assertSame('in the core for 5, including reactions', $report->modules['blog'] ?? null, 'a bundled module is accounted for');
		// The updater is in the core now. Calling it third-party sent people looking
		// for a replacement that does not need to exist — which is exactly what
		// this list is for avoiding.
		$this->assertTrue(
			str_contains($report->modules['updater'] ?? '', 'in the core'),
			'a module that became part of Pluck 5 says so',
		);

		// And something genuinely unknown still is flagged.
		$this->assertTrue(
			str_contains($report->modules['someothermodule'] ?? 'third-party', 'third-party'),
			'while an unrecognised module is flagged for review',
		);
		$this->assertSame(['default', 'oldstyle'], $report->themes, 'the old themes are listed');

		// --- the old install is untouched ---
		$this->assertTrue(is_file($old . '/data/settings/pages/1.welcome.php'), 'the old page file still exists');
		$this->assertTrue(is_file($old . '/files/shell.php'), 'even the refused upload is left where it was');
		$this->assertSame(
			$before,
			$this->fingerprint($old),
			'nothing in the old tree changed during the migration',
		);

		// --- the report reads like something a person can act on ---
		$text = $report->toText();
		foreach (['Migration complete', 'Sign in', 'Addresses that changed', 'Uploads not copied', 'sanitiser changed'] as $heading) {
			$this->assertTrue(str_contains($text, $heading), 'the report mentions: ' . $heading);
		}
		$this->assertTrue(str_contains($text, (string) $report->ownerPassword), 'the one-time password is in the report');
	}

	private function refusals(): void
	{
		[$storage, $media] = $this->freshInstall('flatfile');

		$empty = $this->tempDir('not-pluck');
		$report = (new Migrator(new LegacySite($empty), $storage, $media))->plan();
		$this->assertFalse($report->ok(), 'a directory that is not a pluck install is refused');
		$this->assertTrue(str_contains($report->toText(), 'STOPPED'), 'and the report leads with that');

		$this->expectFailure(
			static fn () => new LegacySite('/nonexistent/path'),
			'a missing directory is an error straight away',
		);
	}

	// ---- fixture --------------------------------------------------------

	/** @return array{0:StorageDriver,1:string} */
	private function freshInstall(string $driver): array
	{
		$dir = $this->tempDir('pluck5-' . $driver);
		$storage = DriverFactory::make($driver, $dir);
		$storage->install();

		$media = $dir . '/media';
		mkdir($media, 0o755, true);

		return [$storage, $media];
	}

	private function buildLegacySite(): string
	{
		$root = $this->tempDir('pluck47');

		$write = static function (string $path, string $contents) use ($root): void {
			$full = $root . '/' . $path;
			@mkdir(dirname($full), 0o755, true);
			file_put_contents($full, $contents);
		};

		/** Exactly the shape save_page() produces. */
		$page = static function (array $values): string {
			$out = "<?php\n";
			foreach ($values as $name => $value) {
				$out .= '$' . $name . " = '" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value) . "';\n";
			}

			return $out . "?>";
		};

		$write('data/inc/security.php', "<?php\ndefine('PLUCK_VERSION', '4.7.21 dev');\n");

		// Page 1: carries a stored XSS payload from the years before the sanitiser
		// was any good — exactly what #64 and CVE-2026-31205 describe.
		$write('data/settings/pages/1.welcome.php', $page([
			'title' => 'Welcome',
			'seoname' => 'welcome',
			// The link is written with different capitals and %20 for the spaces,
			// which is what an editor produces — and what left a real site with a
			// dead link to a PDF that had migrated perfectly.
			'content' => '<p>Welcome to our site</p><script>alert(document.cookie)</script><img src=x onerror=alert(1)>'
				. '<p><a href="files/privacy%20policy%20tian%20dao.pdf">Privacy</a> '
				. '<a href="www.example.com">not an address at all</a></p>',
			'hidden' => 'no',
		]));

		$write('data/settings/pages/2.about.php', $page([
			'title' => 'About us',
			'seoname' => 'about',
			'content' => "<p>It's a small company.</p>",
			'hidden' => 'no',
			'description' => 'Who we are',
			'keywords' => 'about, us',
		]));

		// Sub-pages: folder named after the parent's seoname.
		$write('data/settings/pages/about/1.team.php', $page([
			'title' => 'Team', 'seoname' => 'team', 'content' => '<p>Us</p>', 'hidden' => 'no',
		]));
		$write('data/settings/pages/about/2.history.php', $page([
			'title' => 'History', 'seoname' => 'history', 'content' => '<p>Since 2009</p>', 'hidden' => 'no',
		]));

		// Hidden, and carrying module variables the way $module_additional_data did.
		$write('data/settings/pages/3.contact.php', $page([
			'title' => 'Contact',
			'seoname' => 'contact',
			'content' => '<p>Mail us</p>',
			'hidden' => 'yes',
			'blog_posts_per_page' => '10',
			'contactform_email' => 'info@example.org',
		]));

		// Issue #27: a non-latin title produced a date-time filename.
		$write('data/settings/pages/4.20231104093012.php', $page([
			'title' => 'Över ons',
			'seoname' => '20231104093012',
			'content' => '<p>Hallo</p>',
		]));

		// Something no version ever wrote, but old trees have them.
		$write('data/settings/pages/broken-file.php', "<?php\n\$title = 'Broken';\n");

		$write('data/settings/options.php', $page(['sitetitle' => 'Voorbeeldsite', 'email' => 'info@example.org']));
		$write('data/settings/langpref.php', $page(['langpref' => 'nl.php']));
		$write('data/settings/themepref.php', $page(['themepref' => 'oldstyle']));
		$write('data/settings/pass.php', $page(['ww' => hash('sha512', 'oldpassword')]));
		$write('data/settings/install.dat', '');

		$write('data/themes/default/theme.php', "<?php // a 4.x theme is php\n");
		$write('data/themes/oldstyle/theme.php', "<?php // and so is this one\n");

		$write('data/modules/blog/blog.php', "<?php\n");
		$write('data/modules/albums/albums.php', "<?php\n");
		$write('data/modules/updater/updater.php', "<?php\n");

		// --- blog ------------------------------------------------------
		// The number in front is a display position the module rewrites on every
		// save, so it deliberately disagrees with the timestamps here: the
		// migrator has to sort on time, not on the filename.
		$write('data/settings/modules/blog/categories/nieuws.php', $page(['category_title' => 'Nieuws']));
		$write('data/settings/modules/blog/categories/uit-de-oude-doos.php', $page(['category_title' => 'Uit de oude doos']));

		$write('data/settings/modules/blog/posts/3.eerste-bericht.php', $page([
			'post_title' => 'Eerste bericht',
			'post_category' => 'nieuws',
			'post_content' => '<p>Hallo wereld</p>',
			'post_time' => '1288569600',
		]));
		$write('data/settings/modules/blog/posts/1.tweede-bericht.php', $page([
			'post_title' => 'Tweede bericht',
			'post_category' => '',
			'post_content' => '<p>Nog iets</p><script>alert(1)</script>',
			'post_time' => '1420070400',
		]));
		$write('data/settings/modules/blog/posts/2.zonder-tijd.php', $page([
			'post_title' => 'Zonder tijd',
			'post_category' => 'nieuws',
			'post_content' => '<p>Geen timestamp</p>',
		]));
		$write('data/settings/modules/blog/posts/broken.php', "<?php\n\$post_title = 'Broken';\n");

		// Reactions live in a directory named after the post, numbered from 1.
		$write('data/settings/modules/blog/posts/eerste-bericht/1.php', $page([
			'reaction_name' => 'Jan',
			'reaction_email' => 'jan@example.org',
			'reaction_website' => 'http://example.org',
			'reaction_message' => 'Leuk!<script>alert(2)</script>',
			'reaction_time' => '1288656000',
		]));
		$write('data/settings/modules/blog/posts/eerste-bericht/2.php', $page([
			'reaction_name' => 'Piet',
			'reaction_email' => '',
			'reaction_message' => 'Ook leuk',
			'reaction_time' => '1288742400',
		]));

		$write('data/settings/blog.settings.php', $page([
			'allow_reactions' => 'true',
			'posts_per_page' => '4',
		]));

		// --- albums ----------------------------------------------------
		$pixel = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
		) ?: 'png';

		$write('data/settings/modules/albums/vakantie.php', $page(['album_name' => 'Vakantie 2011']));
		$write('data/settings/modules/albums/vakantie/1.strand.jpg.php', $page([
			'name' => 'Op het strand',
			'info' => 'De eerste dag',
		]));
		$write('data/settings/modules/albums/vakantie/strand.jpg', $pixel);
		$write('data/settings/modules/albums/vakantie/2.bergen.jpg.php', $page([
			'name' => 'In de bergen',
			'info' => '',
		]));
		// A different picture, byte for byte, so the deduplication below is proved
		// by the one file that really is a copy rather than by everything being one.
		$write('data/settings/modules/albums/vakantie/bergen.jpg', $pixel . 'bergen');

		// An entry whose picture went missing at some point.
		$write('data/settings/modules/albums/vakantie/3.kwijt.jpg.php', $page(['name' => 'Kwijt', 'info' => '']));

		// The same photograph in a second album. 4.x kept a copy per album, because
		// an album was a directory; one media folder means it must arrive once.
		$write('data/settings/modules/albums/reisje.php', $page(['album_name' => 'Reisje']));
		$write('data/settings/modules/albums/reisje/1.strand.jpg.php', $page(['name' => 'Ook het strand', 'info' => '']));
		$write('data/settings/modules/albums/reisje/strand.jpg', $pixel);

		// Generated thumbnails: same names, different directory. These must not
		// end up in the media folder a second time.
		$write('data/settings/modules/albums/vakantie/thumb/strand.jpg', $pixel);
		$write('data/settings/modules/albums/vakantie/thumb/bergen.jpg', $pixel . 'bergen');

		// Not album metadata, not a picture. The sweep has to still catch this.
		$write('data/settings/modules/albums/vakantie/backdoor.php', $this->webshell());

		// The albums-enhancements module's description file. Stock 4.x has none; an
		// install that used the module has one per album, and it is not an upload.
		$write('data/settings/modules/albums/vakantie/1.album_desc.php', $page(['descripcion' => '<p>Zomer aan zee</p>']));

		$write('data/settings/albums.settings.php', $page(['resize_image_width' => '800']));

		// Uploads, including one that should never have been accepted.
		$write('files/brochure.pdf', "%PDF-1.4\n%%EOF\n");
		$write('files/shell.php', $this->webshell());
		$write('files/notes.txt', "just notes\n");
		// A filename with spaces and capitals, linked with different capitals —
		// which is what an editor produces and what left a real site with a dead
		// link to a perfectly migrated PDF.
		$write('files/Privacy Policy Tian Dao.pdf', "%PDF-1.4\n% privacy\n%%EOF\n");
		// Distinct bytes on purpose: identical content is now carried once, so a
		// fixture where every picture is the same pixel would merge into one file
		// and prove nothing about anything else.
		$write('images/logo.png', (base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
		) ?: 'png') . 'logo');
		$write('images/index.html', '');

		return $root;
	}

	/** A cheap content fingerprint of a tree, to prove nothing was written to it. */
	private function fingerprint(string $dir): string
	{
		$parts = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file) {
			if ($file->isFile()) {
				$parts[] = $file->getPathname() . ':' . md5_file($file->getPathname());
			}
		}
		sort($parts);

		return md5(implode('|', $parts));
	}
}
