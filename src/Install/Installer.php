<?php
declare(strict_types=1);

namespace Pluck\Install;

use InvalidArgumentException;
use Pluck\Bootstrap;
use Pluck\Model\Page;
use Pluck\Model\Role;
use Pluck\Model\User;
use Pluck\Storage\DriverFactory;
use Pluck\Support\Path;

final class Installer
{
	public function __construct(private readonly Bootstrap $app)
	{
	}

	/**
	 * @param array{site_title:string,storage:string,username:string,password:string,email:string,timezone:string,language:string} $input
	 * @return list<string> validation errors; empty means the install ran
	 */
	public function run(array $input): array
	{
		$errors = $this->validate($input);
		if ($errors !== []) {
			return $errors;
		}

		$config = $this->app->config;
		$config->set('storage', $input['storage']);
		$config->set('timezone', $input['timezone']);
		$config->set('language', $input['language']);
		$config->set('installed_at', gmdate('c'));
		$config->set('version', Bootstrap::VERSION);
		$config->save();

		$storage = DriverFactory::make($input['storage'], $this->app->dataDir);
		$storage->install();

		$storage->transaction(function () use ($storage, $input): void {
			$storage->setSetting('site_title', $input['site_title']);
			$storage->setSetting('site_description', '');
			$storage->setSetting('theme', 'plain');
			$storage->setSetting('search_enabled', false);
			$storage->setSetting('updates_check_enabled', true);
			$storage->setSetting('updates_channel', 'stable');

			$owner = User::create($input['username'], $input['password'], Role::Owner, $input['email']);
			$storage->saveUser($owner);

			$storage->savePage(new Page(
				path: 'welcome',
				title: 'Welcome',
				content: '<p>This is your first page. Open the admin area to change it, or delete it and start your own.</p>',
				authorId: $owner->id,
			));
		});

		$this->hardenDataDir();

		return [];
	}

	/**
	 * @param array<string,mixed> $input
	 * @return list<string>
	 */
	public function validate(array $input): array
	{
		$errors = [];

		if (trim((string) ($input['site_title'] ?? '')) === '') {
			$errors[] = 'Give the site a name.';
		}

		$username = trim((string) ($input['username'] ?? ''));
		if (preg_match('/^[\p{L}\p{N}._-]{3,32}$/u', $username) !== 1) {
			$errors[] = 'Pick a username of 3 to 32 characters, using letters, numbers, dot, dash or underscore.';
		}

		$password = (string) ($input['password'] ?? '');
		if (mb_strlen($password, 'UTF-8') < 12) {
			$errors[] = 'Use a password of at least 12 characters.';
		}

		$email = trim((string) ($input['email'] ?? ''));
		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			$errors[] = 'That email address does not look right.';
		}

		$storage = (string) ($input['storage'] ?? '');
		$options = DriverFactory::options();
		if (!isset($options[$storage])) {
			$errors[] = 'Choose how to store your content.';
		} elseif (!$options[$storage]['available']) {
			$errors[] = $options[$storage]['reason'];
		}

		$timezone = (string) ($input['timezone'] ?? 'UTC');
		if (!in_array($timezone, timezone_identifiers_list(), true)) {
			$errors[] = 'Choose a time zone from the list.';
		}

		return $errors;
	}

	/**
	 * Drop a deny-all .htaccess and an empty index.html into every data folder.
	 * On nginx this does nothing, which is why the installer also reports back
	 * whether data/ is reachable over HTTP.
	 */
	private function hardenDataDir(): void
	{
		$deny = <<<'HTACCESS'
			# Nothing under data/ may be served or executed directly.
			<IfModule mod_authz_core.c>
				Require all denied
			</IfModule>
			<IfModule !mod_authz_core.c>
				Order allow,deny
				Deny from all
			</IfModule>
			php_flag engine off
			HTACCESS;

		/*
		 * Uploads are the exception: images have to be reachable over HTTP, so
		 * this folder cannot be denied. What it must never do is execute, which
		 * is the actual attack (upload shell.php, request it).
		 */
		$uploadsRules = <<<'HTACCESS'
			# Images and downloads are served from here, but nothing in this folder
			# may ever be executed.
			php_flag engine off
			<IfModule mod_php.c>
				php_admin_flag engine off
			</IfModule>
			RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .py
			RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar
			<FilesMatch "\.(?i:php|phtml|phar|ph[0-9]|cgi|pl|py|htaccess)$">
				<IfModule mod_authz_core.c>
					Require all denied
				</IfModule>
				<IfModule !mod_authz_core.c>
					Order allow,deny
					Deny from all
				</IfModule>
			</FilesMatch>
			HTACCESS;

		$uploads = $this->app->dataDir . '/uploads';
		if (is_dir($uploads) && !is_file($uploads . '/.htaccess')) {
			@file_put_contents($uploads . '/.htaccess', $uploadsRules . "\n");
		}

		$directories = ['', 'content', 'content/pages', 'settings', 'users', 'modules', 'cache', 'trash'];
		foreach ($directories as $relative) {
			try {
				$dir = $relative === '' ? $this->app->dataDir : Path::within($this->app->dataDir, $relative);
			} catch (InvalidArgumentException) {
				continue;
			}
			if (!is_dir($dir)) {
				continue;
			}
			if (!is_file($dir . '/.htaccess')) {
				@file_put_contents($dir . '/.htaccess', $deny . "\n");
			}
			if (!is_file($dir . '/index.html')) {
				@file_put_contents($dir . '/index.html', '');
			}
		}
	}
}
