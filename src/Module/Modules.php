<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\I18n\Translator;

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
 * data/modules was a module. Third-party modules arrive through the installer,
 * where the archive policy applies, and are added here.
 */
final class Modules
{
	public static function registry(?Translator $translator = null): ModuleRegistry
	{
		return new ModuleRegistry([
			new BlogModule($translator),
			new AlbumsModule($translator),
			new ContactModule($translator),
			new BlogAdminModule(),
			new AlbumsAdminModule(),
		]);
	}
}
