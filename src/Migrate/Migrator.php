<?php
declare(strict_types=1);

namespace Pluck\Migrate;

use Pluck\Archive\EntryPolicy;
use Pluck\Media\MediaLibrary;
use Pluck\Model\Page;
use Pluck\Site\Redirects;
use Pluck\Module\ReactionStatus;
use Pluck\Model\Role;
use Pluck\Model\User;
use Pluck\Security\Sanitizer;
use Pluck\Storage\StorageDriver;
use Pluck\Support\Path;
use Pluck\Support\Slug;

/**
 * Moves a 4.x site into a v5 install.
 *
 * Three things this deliberately does *not* do:
 *
 * 1. **Write to the old install.** Not one byte. A failed run costs time only,
 *    and the old site keeps serving until the new one is checked.
 * 2. **Carry the old password over.** 4.x stored an unsalted sha512 of it in
 *    data/settings/pass.php. Importing that would mean starting the new install
 *    with a hash nobody should be using, so the owner account gets a fresh
 *    one-time password instead and is asked to change it.
 * 3. **Trust the old content.** Every page body goes through the sanitiser, which
 *    is where stored XSS from the 4.x years actually gets removed. The report says
 *    how many pages changed and which, because silently rewriting someone's
 *    content is not acceptable even when the rewrite is correct.
 *
 * The report doubles as an audit of the old site. Old installs tend to contain
 * uploads that should never have been accepted — .php in files/, a shell in an
 * album — and the migration is the moment those become visible.
 */
final class Migrator
{
	private readonly Sanitizer $sanitizer;
	private readonly EntryPolicy $mediaPolicy;

	public function __construct(
		private readonly LegacySite $site,
		private readonly StorageDriver $storage,
		private readonly string $mediaDir,
	) {
		$this->sanitizer = new Sanitizer();

		// Uploads face the same rules as a theme archive, minus the template
		// formats: this is what catches the .php sitting in a 4.x files/ folder.
		$this->mediaPolicy = new EntryPolicy([
			'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'svg',
			'pdf', 'txt', 'csv', 'zip', 'mp3', 'mp4', 'webm', 'ogg', 'wav',
			'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
		], 'media file');
	}

	/** Read everything and report, writing nothing. */
	public function plan(): MigrationReport
	{
		return $this->execute(dryRun: true, ownerName: null);
	}

	/**
	 * Do it. $ownerName is the username for the new owner account; the generated
	 * password is on the report, shown once.
	 */
	public function run(string $ownerName): MigrationReport
	{
		return $this->execute(dryRun: false, ownerName: $ownerName);
	}

	private function execute(bool $dryRun, ?string $ownerName): MigrationReport
	{
		$report = new MigrationReport($dryRun);
		$report->legacyVersion = $this->site->version();

		if (!$this->site->looksLikePluck()) {
			$report->fail('That directory does not look like a Pluck installation: no data/settings found.');

			return $report;
		}

		$owner = null;
		if (!$dryRun && $ownerName !== null) {
			$owner = $this->createOwner($ownerName, $report);
		}

		$this->migrateSettings($report, $dryRun);
		$this->migratePages($report, $dryRun, $owner?->id);

		// Uploads first: the albums carry their pictures into the same media
		// folder, and they need to know what each one ended up being called.
		$this->migrateUploads($report, $dryRun);
		$this->migrateBlog($report, $dryRun, $owner?->id);
		$this->migrateAlbums($report, $dryRun);
		$this->reportModulesAndThemes($report);

		// Last, because it needs both maps: where the uploads went and where the
		// pages went. Copying the files and rewriting the addresses that point at
		// them are two halves of one job, and doing only the first produces a
		// migration that looks like it worked and has a broken image on every page.
		if (!$dryRun) {
			$this->rewriteLinks($report);
		}

		foreach ($this->site->notes() as $note) {
			$report->note($note);
		}

		// Recorded, not only printed. Everything linking to an old address — search
		// results, a newsletter, somebody's bookmark — got a 404 while the report
		// sat there knowing exactly where the page had gone.
		if (!$dryRun) {
			Redirects::remember($this->storage, $report->redirectMap());
		}

		return $report;
	}

	private function createOwner(string $username, MigrationReport $report): User
	{
		$password = $this->readablePassword();

		// An account with this name may already exist — bin/migrate can install
		// Pluck 5 before migrating into it, and that install makes an owner. Adding
		// a second account with the same name would leave nobody able to say which
		// of the two the password on the report belongs to.
		$owner = $this->storage->findUserByUsername($username);

		if ($owner !== null) {
			$owner->setPassword($password);
			$owner->role = Role::Owner;
		} else {
			$owner = User::create($username, $password, Role::Owner);
		}

		$owner->mustChangePassword = true;
		$this->storage->saveUser($owner);

		$report->ownerUsername = $username;
		$report->ownerPassword = $password;

		if ($this->site->hasPassword()) {
			$report->note(
				'The old install had a password in data/settings/pass.php. It was an unsalted sha512 hash, '
				. 'so it was not imported; the account above has a new one-time password instead.',
			);
		}

		return $owner;
	}

	private function migrateSettings(MigrationReport $report, bool $dryRun): void
	{
		$legacy = $this->site->settings();

		$map = [
			'sitetitle' => 'site_title',
			'email' => 'contact_email',
			'langpref' => 'language',
			'themepref' => 'legacy_theme',
		];

		foreach ($map as $from => $to) {
			if (!isset($legacy[$from]) || $legacy[$from] === '') {
				continue;
			}

			$value = $legacy[$from];
			if ($to === 'language') {
				$value = str_replace('.php', '', $value);
			}

			if (!$dryRun) {
				$this->storage->setSetting($to, $value);
			}
			$report->settings[$to] = $value;
		}

		if (isset($legacy['themepref'])) {
			$report->note(sprintf(
				'The old site used the "%s" theme. Themes in 5 are templates rather than PHP, so it needs '
				. 'converting by hand; the name is stored as legacy_theme so nothing is lost.',
				$legacy['themepref'],
			));
		}
	}

	private function migratePages(MigrationReport $report, bool $dryRun, ?string $authorId): void
	{
		$taken = [];
		// Where each legacy page ended up, keyed by its 4.x seoname. Only used to
		// give a child the parent's new path; the redirect list the report prints
		// is derived from the pages themselves, so the two never drift apart.
		$newBySeoname = [];

		foreach ($this->site->pages() as $legacy) {
			if ($legacy->problem !== null) {
				$report->skip($legacy->sourceFile, $legacy->problem);
				continue;
			}

			$oldPath = $legacy->path();

			// The parent has already been migrated, so its new path is known.
			$parentNew = $legacy->parentSeoname === null ? '' : ($newBySeoname[$legacy->parentSeoname] ?? Slug::path($legacy->parentSeoname));

			$slug = Slug::make($legacy->hasGeneratedName() ? $legacy->title : $legacy->seoname, 'page');
			$newPath = $parentNew === '' ? $slug : $parentNew . '/' . $slug;
			$newPath = Slug::path($newPath);

			$newPath = Slug::unique($newPath, static fn (string $candidate): bool => isset($taken[$candidate]));
			$taken[$newPath] = true;

			$inspected = $this->sanitizer->inspect($legacy->content);
			$clean = $inspected->html;
			if ($inspected->removedAnything()) {
				$report->sanitised($oldPath, $inspected->summary());
			}

			$newBySeoname[$legacy->seoname] ??= $newPath;

			if ($legacy->hasGeneratedName()) {
				$report->note(sprintf(
					'"%s" had the generated address %s, which 4.x used for titles with no latin characters. '
					. 'It is now /%s, and the old address is in the redirect map.',
					$legacy->title,
					$legacy->seoname,
					$newPath,
				));
			}

			$moduleData = [];
			if ($legacy->extra !== []) {
				// Module data in 4.x was bare top-level variables with no namespace,
				// so it is kept verbatim under a key that says where it came from.
				$moduleData['legacy'] = $legacy->extra;
				$report->moduleData[$oldPath] = array_keys($legacy->extra);
			}

			if (!$dryRun) {
				$this->storage->savePage(new Page(
					path: $newPath,
					title: $legacy->title !== '' ? $legacy->title : $slug,
					content: $clean,
					hidden: $legacy->hidden,
					order: $legacy->order,
					description: mb_substr($legacy->description, 0, 320),
					keywords: mb_substr($legacy->keywords, 0, 320),
					moduleData: $moduleData,
					authorId: $authorId,
				));
			}

			$report->pages[] = ['from' => $oldPath, 'to' => $newPath, 'hidden' => $legacy->hidden];
		}
	}

	private function migrateUploads(MigrationReport $report, bool $dryRun): void
	{
		/** @var array<string,string> content hash => name already in the media folder */
		$seen = [];

		foreach ($this->site->uploads() as $relative) {
			$source = $this->site->root() . '/' . $relative;
			$basename = basename($relative);

			$verdict = $this->mediaPolicy->judge($basename, (int) (filesize($source) ?: 0), 0);
			if ($verdict !== null) {
				$report->refusedUpload($relative, $verdict);
				continue;
			}

			// 4.x kept a copy of a picture in every album that used it, because an
			// album was a directory. One media folder means the same photograph in
			// three albums would arrive as three files — so identical content is
			// carried once and pointed at from everywhere.
			//
			// By content, never by name: two files called logo.png are routinely
			// two different logos, and merging those would replace one of them
			// silently.
			$hash = MediaLibrary::hashFile($source);
			if ($hash !== null && isset($seen[$hash])) {
				// Recorded separately: uploads is keyed by the name in the media
				// folder, so writing the second original into it would lose the
				// first and leave whatever pointed at it dangling.
				$report->duplicateUploads[$relative] = $seen[$hash];
				continue;
			}

			$extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
			$stem = substr(Slug::make(pathinfo($basename, PATHINFO_FILENAME), 'file'), 0, 80);
			$name = $stem . '.' . $extension;

			$counter = 2;
			while (isset($report->uploads[$name])) {
				$name = $stem . '-' . $counter++ . '.' . $extension;
			}

			if (!$dryRun) {
				$target = Path::within($this->mediaDir, $name);
				if (!copy($source, $target)) {
					$report->skip($relative, 'could not be copied into the media folder');
					continue;
				}
				@chmod($target, 0o644);
			}

			$report->uploads[$name] = $relative;

			if ($hash !== null) {
				$seen[$hash] = $name;
			}
		}
	}

	/**
	 * Blog posts, their categories and their reactions.
	 *
	 * 4.x kept these as PHP files under data/settings/modules/blog and nothing
	 * else knew about them; in v5 they are module data behind the storage
	 * driver, so they migrate once and work on either backend.
	 *
	 * Two things change on the way across. Post bodies go through the sanitiser
	 * like page bodies do — a blog that took reactions for a decade is the most
	 * likely place in an old install to find stored XSS. And the e-mail address
	 * 4.x recorded with every reaction is dropped: it was never displayed, the
	 * commenter has no way to ask for it back, and carrying it over would mean
	 * the new install starts life holding personal data it has no use for. The
	 * report says so rather than leaving it to be discovered.
	 */
	private function migrateBlog(MigrationReport $report, bool $dryRun, ?string $authorId): void
	{
		$posts = $this->site->posts();
		$categories = $this->site->postCategories();

		if ($posts === [] && $categories === []) {
			return;
		}

		foreach ($categories as $seoname => $title) {
			$slug = Slug::make($title !== '' ? $title : $seoname, 'category');
			if (!$dryRun) {
				$this->storage->setModuleData('blog', 'category:' . $slug, [
					'title' => $title,
					'legacy_seoname' => $seoname,
				]);
			}
			$report->blogCategories[$seoname] = $slug;
		}

		$taken = [];
		$reactionCount = 0;
		$withEmail = 0;

		foreach ($posts as $post) {
			if ($post->problem !== null) {
				$report->skip($post->sourceFile, $post->problem);
				continue;
			}

			$slug = Slug::unique(
				Slug::make($post->seoname !== '' ? $post->seoname : $post->title, 'post'),
				static fn (string $candidate): bool => isset($taken[$candidate]),
			);
			$taken[$slug] = true;

			$inspected = $this->sanitizer->inspect($post->content);
			$clean = $inspected->html;
			if ($inspected->removedAnything()) {
				$report->sanitised($post->path(), $inspected->summary());
			}

			if (!$dryRun) {
				$this->storage->setModuleData('blog', 'post:' . $slug, [
					'title' => $post->title,
					'content' => $clean,
					'category' => $post->category !== '' ? ($report->blogCategories[$post->category] ?? $post->category) : '',
					// Everything on a 4.x site was live; there were no drafts to
					// import. Saying so explicitly means the setting is not
					// something a migrated blog inherits by accident.
					'published' => true,
					'published_at' => $post->publishedAt(),
					// Also from the enhancements module; absent means open, which
					// is what the stock module did.
					'allow_reaction' => $post->allowReaction,
					'author_id' => $authorId,
					'legacy_seoname' => $post->seoname,
				]);
			}

			foreach ($post->reactions as $reaction) {
				$reactionCount++;
				if ($reaction->email !== '') {
					$withEmail++;
				}

				if (!$dryRun) {
					$this->storage->setModuleData('blog', sprintf('reaction:%s:%04d', $slug, $reaction->id), [
						'name' => $reaction->name,
						'website' => $reaction->website,
						'message' => $this->sanitizer->clean($reaction->message),
						'posted_at' => $reaction->postedAt(),
						// Visible for years on the old site; putting them in a
						// moderation queue on moving day would be a surprise
						// rather than a safety measure.
						'status' => ReactionStatus::Approved->value,
					]);
				}
			}

			$report->blogPosts[] = ['from' => $post->path(), 'to' => 'blog/' . $slug, 'reactions' => count($post->reactions)];
		}

		if (!$dryRun) {
			$settings = $this->site->moduleSettings('blog');
			// The blog-enhancements module stored reverse_posts alongside the
			// stock settings, so an install that had it keeps its ordering and its
			// date formats rather than silently reverting to the defaults.
			$this->storage->setModuleData('blog', 'settings', [
				'posts_per_page' => max(1, (int) ($settings['posts_per_page'] ?? 10)),
				'truncate_posts' => max(0, (int) ($settings['truncate_posts'] ?? 0)),
				'reverse_posts' => ($settings['reverse_posts'] ?? '') === 'true',
				'allow_reactions' => ($settings['allow_reactions'] ?? '') === 'true',
				'moderate_reactions' => false,
				'post_date' => (string) ($settings['post_date'] ?? ''),
				'post_time' => (string) ($settings['post_time'] ?? ''),
			]);
		}

		$report->blogReactions = $reactionCount;

		if ($withEmail > 0) {
			$report->note(sprintf(
				'%d of the %d imported reactions had an e-mail address stored with them. 4.x never showed those, '
				. 'so they were not carried over; the old install still has them if they are needed.',
				$withEmail,
				$reactionCount,
			));
		}

		$report->note(sprintf(
			'%d blog posts were imported as module data under "blog". 4.x served them at ?file=blog&blog=<name>; '
			. 'the redirect map lists where each one now lives.',
			count($report->blogPosts),
		));
	}

	/**
	 * Photo albums.
	 *
	 * The pictures themselves travel with the rest of the uploads, so this only
	 * has to record the albums, their captions and which media file each entry
	 * points at now. An entry whose picture was refused by the media policy, or
	 * was already missing in the old install, is reported rather than written:
	 * an album referring to a file that is not there is worse than a short album.
	 */
	private function migrateAlbums(MigrationReport $report, bool $dryRun): void
	{
		$albums = $this->site->albums();
		if ($albums === []) {
			return;
		}

		// migrateUploads() recorded new name => old relative path; the albums need
		// that the other way round, plus the copies it recognised as identical and
		// therefore did not carry twice.
		$byOriginal = array_flip($report->uploads);
		foreach ($report->duplicateUploads as $relative => $name) {
			$byOriginal[$relative] = $name;
		}

		$taken = [];
		$order = 0;

		foreach ($albums as $album) {
			if ($album->problem !== null) {
				$report->skip($album->sourceFile, $album->problem);
				continue;
			}

			$slug = Slug::unique(
				Slug::make($album->title !== '' ? $album->title : $album->seoname, 'album'),
				static fn (string $candidate): bool => isset($taken[$candidate]),
			);
			$taken[$slug] = true;

			$stored = 0;
			foreach ($album->images as $index => $image) {
				if (!$image->fileExists) {
					$report->skip($image->relativePath, 'the album entry has no image file');
					continue;
				}

				$media = $byOriginal[$image->relativePath] ?? null;
				if ($media === null) {
					$report->skip($image->relativePath, 'the image did not make it into the media folder');
					continue;
				}

				if (!$dryRun) {
					$this->storage->setModuleData('albums', sprintf('image:%s:%04d', $slug, $index), [
						'file' => $media,
						'title' => $image->title,
						'info' => $this->sanitizer->clean($image->info),
					]);

					// Recorded so the media picker can group by album. On a site
					// with two hundred pictures, one flat list is a haystack.
					$this->storage->setModuleData('albums', 'media:' . $media, [
						'added_at' => gmdate('c'),
						'album' => $album->title,
					]);
				}
				$stored++;
			}

			if (!$dryRun) {
				$this->storage->setModuleData('albums', 'album:' . $slug, [
					'title' => $album->title,
					'description' => $album->description === '' ? '' : $this->sanitizer->clean($album->description),
					'order' => $order,
					'legacy_seoname' => $album->seoname,
				]);
			}

			$order++;
			$report->albums[] = ['from' => $album->path(), 'to' => 'albums/' . $slug, 'images' => $stored];
		}

		$report->note(sprintf(
			'%d albums were imported as module data under "albums". Their pictures are in the media folder with '
			. 'everything else; 4.x kept them under data/settings, where the web server could not serve them directly.',
			count($report->albums),
		));
	}

	/**
	 * Point the content at where things ended up.
	 *
	 * A second pass rather than part of writing each page, because a page is
	 * written before the uploads are copied and cannot know their new names yet.
	 */
	private function rewriteLinks(MigrationReport $report): void
	{
		$pages = [];
		foreach ($report->pages as $page) {
			$pages[$page['from']] = $page['to'];
		}

		$modules = [];
		foreach ([...$report->blogPosts, ...$report->albums] as $entry) {
			$modules[$entry['from']] = $entry['to'];
		}

		/*
		 * The duplicates belong here too.
		 *
		 * migrateUploads() carries identical content once and records the rest in
		 * duplicateUploads — the albums already merge that in, and this did not.
		 * So a page linking to the copy that was not carried kept pointing at
		 * images/, and every other picture on the page was rewritten: one broken
		 * photograph in a page that otherwise came across perfectly, which is the
		 * hardest kind to notice.
		 *
		 * Keyed the same way as $report->uploads — new name => original path — so
		 * the rewriter needs no idea that some of them were duplicates.
		 */
		$rewriter = new LinkRewriter($report->uploads, $pages, $modules, $report->duplicateUploads);
		$changed = 0;

		foreach ($this->storage->allPages(includeHidden: true) as $page) {
			[$content, $count] = $rewriter->rewrite($page->content);

			if ($count > 0) {
				$page->content = $content;
				$this->storage->savePage($page);
				$changed += $count;
			}
		}

		foreach ($this->storage->listModuleData('blog', 'post:') as $key => $post) {
			if (!is_array($post)) {
				continue;
			}

			[$content, $count] = $rewriter->rewrite((string) ($post['content'] ?? ''));

			if ($count > 0) {
				$post['content'] = $content;
				$this->storage->setModuleData('blog', $key, $post);
				$changed += $count;
			}
		}

		if ($rewriter->suspicious !== []) {
			$report->note(sprintf(
				'%d links in the content were already broken before this migration and were left alone: %s. '
				. 'An address without https:// in front of it is read as a page on this site, which is what '
				. 'they were doing on the old one too.',
				count($rewriter->suspicious),
				implode(', ', array_slice($rewriter->suspicious, 0, 6)),
			));
		}

		if ($changed > 0) {
			$report->note(sprintf(
				'%d addresses in the content were pointed at their new places: uploads moved from images/ '
				. 'and files/ into media/, and links written as ?file=name became the page\'s own address.',
				$changed,
			));
		}
	}

	private function reportModulesAndThemes(MigrationReport $report): void
	{
		/*
		 * The SEO module did one thing, and that thing is off by default here.
		 *
		 * Saying "not needed: pretty URLs are in the core" and then leaving the
		 * site on ?page=name is a broken promise: the addresses that module
		 * produced are exactly what stops working. It cannot simply be switched on
		 * — Pluck checks that rewriting works before allowing it, and the migration
		 * runs from a shell where there is nothing to ask — so this says so
		 * instead of pretending.
		 */
		if (in_array('seo', $this->site->modules(), true)) {
			$report->note(
				'The old site had the SEO module, whose only job was readable addresses. Pluck 5 has that '
				. 'in the core but starts with it off, so this install serves ?page=name until you switch '
				. 'Readable addresses on under Settings. Do that before the site goes live: it changes every '
				. 'address on it.',
			);
		}

		/*
		 * What each 4.x module became.
		 *
		 * Written out rather than sorted into "bundled" and "everything else",
		 * because that split was answering the wrong question. Somebody reading
		 * this report wants to know whether they have lost something — and for
		 * search, seo and the updater the answer is no, they are in the core now.
		 * Calling them third-party sent people looking for a replacement that does
		 * not need to exist.
		 */
		$known = [
			'albums' => 'in the core for 5, with the enhancements module folded in',
			'blog' => 'in the core for 5, including reactions',
			'contactform' => 'in the core for 5 as [module:contact-form]',
			'search' => 'in the core for 5; switch it on under Settings',
			'seo' => 'not needed: pretty URLs are in the core, and a page keeps its own address',
			'updater' => 'in the core for 5, under Updates',
			'multitheme' => 'not needed: a theme is chosen under Settings, and per page in the editor',
			'tinymce' => 'not carried over: the editor is a textarea with a preview of what will be saved',
			'viewsite' => 'not needed: the admin links to the site directly',
			'backup' => 'in the core for 5, under Backups. The 4.x one wrote archives where a web server would serve them; check for leftover *.tar.gz in the old install',
			'editor' => 'a theme file editor. Not in 5 yet — see ROADMAP',
		];

		foreach ($this->site->modules() as $module) {
			$report->modules[$module] = $known[$module]
				?? 'third-party; needs bin/migrate-module and a review by hand';
		}

		$report->themes = $this->site->themes();
	}

	/** Four words and a number: typable over the phone, still long enough. */
	private function readablePassword(): string
	{
		$words = [
			'anchor', 'basket', 'cobalt', 'dolphin', 'ember', 'fathom', 'granite', 'harbour',
			'indigo', 'jasmine', 'kestrel', 'lantern', 'marble', 'nutmeg', 'orchid', 'pebble',
			'quartz', 'rowan', 'saffron', 'thistle', 'umber', 'velvet', 'willow', 'zephyr',
		];

		$picked = [];
		for ($i = 0; $i < 4; $i++) {
			$picked[] = $words[random_int(0, count($words) - 1)];
		}

		return implode('-', $picked) . '-' . random_int(10, 99);
	}
}
