<?php
//Load all the modules, so we can use hooks.
//This has to be done before including other functions files.
$path = opendir('data/modules');
while (false !== ($dir = readdir($path))) {
	if ($dir != '.' && $dir != '..') {
		if (is_dir('data/modules/'.$dir))
			$modules[] = $dir;
	}
}
closedir($path);

foreach ($modules as $module) {
	if (file_exists('data/modules/'.$module.'/'.$module.'.php')) {
		require_once ('data/modules/'.$module.'/'.$module.'.php');
		$module_list[] = $module;
	}
}
unset($module);

/**
 * Run a module hook.
 *
 * @param string $name Name of the hook.
 */
function run_hook($name, $par = null) {
	global $module_list;
	if (!isset($name))
		return;
	foreach ($module_list as $module) {
		if (function_exists($module.'_'.$name) && module_is_compatible($module)) {
			if ($par == null)
				call_user_func($module.'_'.$name);
			else
				call_user_func_array($module.'_'.$name, $par);
		}
	}
	unset($module);
}

/**
 * Check if module is compatible with the current version of pluck.
 *
 * @param string $module The module you want to check.
 * @return bool
 */
function module_is_compatible($module) {
	//Include module information.
	if (file_exists('data/modules/'.$module.'/'.$module.'.php')) {
		$module_info = call_user_func($module.'_info');
		if (isset($module_info['compatibility'])) {
			if (strpos($module_info['compatibility'], ','))
				$version_compat = explode(',', $module_info['compatibility']);
			else
				$version_compat[0] = $module_info['compatibility'];

			//Now check if we have a compatible version. NOTE: If pluck is an alpha or beta version, it will always be compatible.
			foreach ($version_compat as $number => $version) {
				if (preg_match('/'.$version.'/', PLUCK_VERSION) || preg_match('/(alpha|beta)/', PLUCK_VERSION)) {
					return true;
				}
			}
			unset($number);
		}
	}

	else
		return false;
}
?>