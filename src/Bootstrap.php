<?php
declare(strict_types=1);

namespace Pluck;

use Pluck\I18n\Locale;
use Pluck\I18n\Translator;
use Pluck\Model\User;
use Pluck\Security\Csp;
use Pluck\Security\Csrf;
use Pluck\Security\Sanitizer;
use Pluck\Security\Session;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;
use Pluck\Support\Config;
use Pluck\Support\Path;

/**
 * The application container. Deliberately hand-written and tiny: Pluck's
 * audience installs by unzipping, so there is no service-container package and
 * no compiled cache to go stale.
 */
final class Bootstrap
{
	public const VERSION = '5.0.0-rc61';

	private ?StorageDriver $storage = null;
	private ?Session $session = null;
	private ?Csrf $csrf = null;
	private ?Csp $csp = null;
	private ?Sanitizer $sanitizer = null;
	private ?Translator $translator = null;

	private function __construct(
		public readonly string $rootDir,
		public readonly string $dataDir,
		public readonly Config $config,
	) {
	}

	public static function boot(?string $rootDir = null): self
	{
		$rootDir = $rootDir ?? dirname(__DIR__);
		$dataDir = $rootDir . '/data';
		Path::ensureDir($dataDir);
		Path::ensureDir($dataDir . '/settings');

		// Content is data, never code, so error output should never leak a path
		// or a stack trace to a visitor. Log instead.
		ini_set('display_errors', '0');
		ini_set('log_errors', '1');
		error_reporting(E_ALL);

		$timezone = 'UTC';
		$config = Config::load($dataDir);
		$configured = $config->get('timezone');
		if (is_string($configured) && $configured !== '' && in_array($configured, timezone_identifiers_list(), true)) {
			$timezone = $configured;
		}
		date_default_timezone_set($timezone);

		return new self($rootDir, $dataDir, $config);
	}

	public function isInstalled(): bool
	{
		return $this->config->exists() && $this->storage()->isInstalled();
	}

	public function storage(): StorageDriver
	{
		return $this->storage ??= DriverFactory::fromConfig($this->config, $this->dataDir);
	}

	public function session(): Session
	{
		return $this->session ??= new Session($this->dataDir . '/cache');
	}

	public function csrf(): Csrf
	{
		return $this->csrf ??= new Csrf($this->session());
	}

	public function csp(): Csp
	{
		return $this->csp ??= new Csp();
	}

	public function sanitizer(): Sanitizer
	{
		return $this->sanitizer ??= new Sanitizer();
	}

	/**
	 * Translations for the admin, the site and the installer.
	 *
	 * Sources are added in increasing precedence: core first, then themes and
	 * modules, which are expected to namespace their own keys so they cannot
	 * overwrite a core string by accident.
	 */
	public function translator(): Translator
	{
		if ($this->translator instanceof Translator) {
			return $this->translator;
		}

		/*
		 * A theme's own wording, after Pluck's.
		 *
		 * Translator already took several sources; the theme simply never got to
		 * be one. A theme with words of its own had two options before this: put
		 * them in Pluck's catalogue, which is other people's file, or write them
		 * into its templates in one language — which is what the video module was
		 * told off for.
		 *
		 * Later sources win, so a theme can also replace a word of Pluck's it does
		 * not like without anybody editing lang/nl.json.
		 */
		$this->translator = new Translator(
			$this->siteLocale(),
			$this->rootDir . '/lang',
			$this->rootDir . '/themes/' . $this->activeThemeName() . '/lang',
		);

		foreach (['themes', 'modules'] as $group) {
			foreach (glob($this->rootDir . '/' . $group . '/*/lang', GLOB_ONLYDIR) ?: [] as $directory) {
				$this->translator->addSource($directory);
			}
		}

		return $this->translator;
	}

	/**
	 * The language the site falls back to. Comes from install-time config, which is
	 * readable before storage exists — the installer needs a language too.
	 */
	/**
	 * Which theme is in use, for the sake of its language files.
	 *
	 * Read rather than resolved through ThemeRepository: this runs while the
	 * translator is being built, and ThemeRepository wants a translator for its
	 * error messages. A name that turns out not to exist costs nothing —
	 * Translator ignores a source directory that is not there.
	 *
	 * Not a second way of deciding which theme is active: this asks for a folder
	 * name, and the site still resolves the theme itself, with the fallback, when
	 * it renders.
	 */
	private function activeThemeName(): string
	{
		if (!$this->config->exists()) {
			return '';
		}

		$stored = $this->storage()->getSetting('theme');
		$name = is_string($stored) ? basename($stored) : '';

		return preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1 ? $name : '';
	}

	public function siteLocale(): Locale
	{
		$configured = $this->config->get('language');
		$locale = is_string($configured) ? Locale::tryFrom($configured) : null;

		if ($locale === null && $this->config->exists()) {
			$stored = $this->storage()->getSetting('language');
			$locale = is_string($stored) ? Locale::tryFrom($stored) : null;
		}

		return $locale ?? Locale::fallback();
	}

	/**
	 * Switch to an account's own language. 4.x had one global langpref because it
	 * had one admin; a Dutch owner with a Polish editor is a normal situation now,
	 * so the account wins over the site and the site over English.
	 */
	public function useLocaleFor(?User $user): void
	{
		$locale = $user?->language !== null ? Locale::tryFrom($user->language) : null;

		$this->translator()->setLocale($locale ?? $this->siteLocale());
	}
}
