<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\I18n\Translator;
use Pluck\Storage\StorageDriver;

/**
 * The modules this install has.
 *
 * One list, built in code, used by both front controllers. Having index.php and
 * admin.php each assemble their own was how the site could render a blog the
 * admin did not know existed.
 *
 * Built in code rather than scanned from a directory on purpose. "Drop a folder
 * in and it runs" is how Pluck 4 shipped, and it is exactly the property that
 * made a compromised install so easy to keep — a web shell dropped in
 * data/modules was a module.
 *
 * A module outside this list therefore has to be named, once, by somebody who
 * can already change settings. `modules_enabled` holds those names; a folder in
 * modules/ that is not on it is inert, and putting one there is not enough.
 *
 * modules/ is preserved by the updater, which is the other half of the problem:
 * before this, a site-specific module had nowhere to live that survived an
 * update, because src/ is replaced wholesale.
 */
final class Modules
{
	public static function registry(?Translator $translator = null, ?StorageDriver $storage = null): ModuleRegistry
	{
		$modules = [
			new BlogModule($translator),
			new AlbumsModule($translator),
			new ContactModule($translator),
			new BlogAdminModule(),
			new AlbumsAdminModule(),
		];

		foreach (self::extra($translator, $storage) as $module) {
			$modules[] = $module;
		}

		return new ModuleRegistry($modules);
	}

	/**
	 * Modules this install has been told to load, by name.
	 *
	 * A name here means modules/<name>/<Class>.php exists and an owner put the
	 * name in the setting. Both are required: a folder alone does nothing, which
	 * is the property that stops an upload from becoming code that runs.
	 *
	 * @return list<object>
	 */
	private static function extra(?Translator $translator, ?StorageDriver $storage): array
	{
		if ($storage === null) {
			return [];
		}

		$names = $storage->getSetting('modules_enabled', []);
		if (!is_array($names)) {
			return [];
		}

		$root = dirname(__DIR__, 2) . '/modules';
		$loaded = [];

		foreach ($names as $name) {
			// Rebuilt rather than trusted: this becomes a path and a class name.
			$name = preg_replace('/[^a-z0-9-]/', '', is_string($name) ? $name : '');
			if ($name === '' || !is_dir($root . '/' . $name)) {
				continue;
			}

			foreach (glob($root . '/' . $name . '/*.php') ?: [] as $file) {
				require_once $file;
			}

			foreach (get_declared_classes() as $class) {
				if (!is_subclass_of($class, SiteModule::class) || !str_contains($class, 'Pluck\\Module\\')) {
					continue;
				}

				$module = new $class($translator);

				if ($module->name() === $name) {
					$loaded[] = $module;

					break;
				}
			}
		}

		return $loaded;
	}
}
