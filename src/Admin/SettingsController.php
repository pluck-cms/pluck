<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Media\MediaLibrary;

final class SettingsController extends Controller
{
	public function show(): never
	{
		$storage = $this->c->storage;

		$this->render('admin/settings', [
			'title' => $this->t('settings.title.settings'),
			'siteTitleValue' => (string) $storage->getSetting('site_title', 'Pluck'),
			'siteDescription' => (string) $storage->getSetting('site_description', ''),
			'searchEnabled' => (bool) $storage->getSetting('search_enabled', false),
			'updatesEnabled' => (bool) $storage->getSetting('updates_check_enabled', true),
			'timezone' => (string) $this->c->app->config->get('timezone', 'UTC'),
			'timezones' => timezone_identifiers_list(),
			'siteLanguage' => (string) $this->c->app->config->get('language', 'en'),
			// The same list the account screen uses: whatever lang/*.json exists,
			// which is the only honest answer to "which languages are there".
			'languages' => $this->c->app->translator()->available(),
			'maxMb' => round(((int) $storage->getSetting('media_max_bytes', MediaLibrary::DEFAULT_MAX_BYTES)) / 1048576, 1),
			'formChallenge' => (string) $storage->getSetting('form_challenge', 'sum'),
			'recaptchaSiteKey' => (string) $storage->getSetting('recaptcha_site_key', ''),
			'recaptchaSecret' => (string) $storage->getSetting('recaptcha_secret', '') === '' ? '' : '••••••••',
			'availableModules' => $this->modulesOnDisk(),
			'enabledModules' => $this->enabled('modules_enabled'),
			'frameHostNames' => \Pluck\Security\Csp::frameHostNames(),
			'frameHosts' => $this->enabled('frame_hosts'),
			// Backups: how many to keep and how often. The backups screen lists
			// what exists; deciding the policy belongs with the other decisions.
			'backupKeep' => (int) $this->c->storage->getSetting('backup_keep', 5),
			'backupIntervalDays' => (int) $this->c->storage->getSetting('backup_interval_days', 7),
			'prettyUrls' => (bool) $storage->getSetting('pretty_urls', false),
		]);
	}

	public function save(): never
	{
		$request = $this->c->request;
		$storage = $this->c->storage;

		$title = $request->post('site_title');
		if ($title === '') {
			$this->c->flash->stop($this->t('settings.flash.site_needs_title'));
			$this->back('settings');
		}

		$storage->setSetting('site_title', mb_substr($title, 0, 120));
		$storage->setSetting('site_description', mb_substr($request->post('site_description'), 0, 320));
		$storage->setSetting('search_enabled', $request->postBool('search_enabled'));
		$storage->setSetting('updates_check_enabled', $request->postBool('updates_check_enabled'));

		$megabytes = (float) $request->post('media_max_mb', '8');
		$storage->setSetting('media_max_bytes', (int) round(max(0.1, min(512.0, $megabytes)) * 1048576));

		// Only a theme that actually loads may be selected. A name typed into the
		// form, or a directory removed between rendering and saving, would
		// otherwise be stored and then silently fall back on every request, which
		// reads as "the setting does not work".
		/*
		 * The logo and the tagline moved to Appearance, and these lines stayed.
		 *
		 * The form here no longer has those fields, so every save posted nothing
		 * for them and wrote the nothing back — one press of Save under Settings
		 * silently cleared a logo set on the other screen. Removed rather than
		 * guarded: the screen that owns a setting is the screen that writes it.
		 */

		/*
		 * The timezone first, before anything that can refuse.
		 *
		 * It lives in config.php rather than in settings, because Bootstrap needs
		 * it before a storage driver exists — and it used to be written last,
		 * after the pretty-URL check, which returns early when rewriting does not
		 * work. Toggling that checkbox therefore threw away the timezone silently.
		 */
		$timezone = $request->post('timezone');
		if (in_array($timezone, timezone_identifiers_list(), true)) {
			$this->c->app->config->set('timezone', $timezone);
			$this->c->app->config->save();
		}

		// The language of the site, as opposed to the one an account reads the
		// admin in. It went missing when per-user languages were added.
		$language = $request->post('site_language', '');
		if ($language !== '' && in_array($language, $this->c->app->translator()->available(), true)) {
			$this->c->app->config->set('language', $language);
			$this->c->app->config->save();
		}

		/*
		 * Which extra modules load, and which services a page may frame.
		 *
		 * Both were settings with no screen: a folder in modules/ did nothing and
		 * the only way to change that was to write the setting by hand. A setting
		 * nobody can reach is a setting nobody has.
		 *
		 * Checked against what is actually there rather than saved as posted — a
		 * name that is not a folder would be a line in the store that does nothing
		 * and confuses whoever reads it next.
		 */
		$storage->setSetting('modules_enabled', array_values(array_intersect(
			$request->postArray('modules_enabled'),
			$this->modulesOnDisk(),
		)));

		$storage->setSetting('frame_hosts', array_values(array_intersect(
			$request->postArray('frame_hosts'),
			\Pluck\Security\Csp::frameHostNames(),
		)));

		$storage->setSetting('backup_keep', max(1, min(50, (int) $request->post('backup_keep', '5'))));
		// Zero switches the automatic backup off, which has to stay possible: on a
		// site with a large media folder somebody may prefer to run it themselves.
		$storage->setSetting('backup_interval_days', max(0, min(365, (int) $request->post('backup_interval_days', '7'))));

		$challenge = $request->post('form_challenge', 'sum');
		$storage->setSetting('form_challenge', in_array($challenge, ['none', 'sum', 'recaptcha'], true) ? $challenge : 'sum');
		$storage->setSetting('recaptcha_site_key', mb_substr(trim($request->post('recaptcha_site_key', '')), 0, 100));

		// Left alone when the field comes back with the dots that stand in for it,
		// so saving the page without retyping the secret does not erase it.
		$secret = trim($request->post('recaptcha_secret', ''));
		if ($secret !== '' && !str_starts_with($secret, '•')) {
			$storage->setSetting('recaptcha_secret', mb_substr($secret, 0, 100));
		}

		// The theme moved too. It was harmless — in_array on an empty post never
		// matched, so nothing was written — but a line that only works because it
		// always fails is a line waiting to be fixed into a bug.

		$this->savePrettyUrls($request->postBool('pretty_urls'));



		$this->c->flash->ok($this->t('settings.flash.settings_saved'));
		$this->back('settings');
	}

	/**
	 * Turn pretty URLs on only when they demonstrably work.
	 *
	 * Enabling this where mod_rewrite is off, or where AllowOverride keeps the
	 * bundled .htaccess from being read, turns every link on the site into a dead
	 * one. The setting is therefore not taken at its word: the probe below asks
	 * the server whether a path-style address reaches index.php, and the answer
	 * decides.
	 *
	 * Turning it *off* is never refused. If the site is already broken, the way
	 * out has to work.
	 */
	private function savePrettyUrls(bool $wanted): void
	{
		$storage = $this->c->storage;

		if (!$wanted) {
			$storage->setSetting('pretty_urls', false);

			return;
		}

		if ((bool) $storage->getSetting('pretty_urls', false)) {
			return;
		}

		if (!$this->rewriteWorks()) {
			$this->c->flash->warn($this->t('settings.flash.pretty_urls_no_rewrite'));

			return;
		}

		$storage->setSetting('pretty_urls', true);
	}

	/**
	 * Ask this site whether a rewritten address reaches index.php.
	 *
	 * index.php answers a reserved path with a known marker. Requesting it over
	 * the loopback tests the whole chain — web server, rewrite rules, PHP handler
	 * — rather than PHP's opinion of its own configuration, which is what
	 * apache_get_modules() gives and which is wrong behind a proxy or on nginx.
	 *
	 * A failure to reach ourselves reads as "no". That is the safe direction: the
	 * cost is having to try again, rather than a site of broken links.
	 */
	private function rewriteWorks(): bool
	{
		$server = $this->c->request->server();

		$scheme = ($server['HTTPS'] ?? '') !== '' && $server['HTTPS'] !== 'off' ? 'https' : 'http';
		$host = (string) ($server['HTTP_HOST'] ?? '');
		if ($host === '' || !preg_match('/^[a-z0-9.:_-]+$/i', $host)) {
			return false;
		}

		$base = rtrim(str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/admin.php'))), '/');
		$url = $scheme . '://' . $host . $base . '/' . Probe::PATH;

		$context = stream_context_create([
			'http' => ['timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0],
			'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
		]);

		$body = @file_get_contents($url, false, $context);

		return is_string($body) && trim($body) === Probe::MARKER;
	}

	/**
	 * The module folders this install has.
	 *
	 * A folder with a module.json in it. Being here is not enough to run — the
	 * name has to be ticked as well, which is the property that stops an upload
	 * from becoming code.
	 *
	 * @return list<string>
	 */
	private function modulesOnDisk(): array
	{
		$found = [];

		foreach (glob($this->c->app->rootDir . '/modules/*', GLOB_ONLYDIR) ?: [] as $dir) {
			if (is_file($dir . '/module.json')) {
				$found[] = basename($dir);
			}
		}

		sort($found);

		return $found;
	}

	/** @return list<string> */
	private function enabled(string $setting): array
	{
		$stored = $this->c->storage->getSetting($setting, []);

		return is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
	}

}
