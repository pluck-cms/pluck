<?php
declare(strict_types=1);

/**
 * Pluck 5 installer.
 *
 * Two steps: check the server, then set up the site. It refuses to run once an
 * install exists, and deletes itself when it is done.
 */

require __DIR__ . '/src/autoload.php';

// An unwritable data/ is the most common way an install fails, and a stack trace
// is the least useful way to say so.
\Pluck\Failure::install(__DIR__ . '/data');

use Pluck\Bootstrap;
use Pluck\Install\Installer;
use Pluck\Install\Requirements;
use Pluck\Security\Csp;
use Pluck\Security\Escaper;
use Pluck\I18n\Locale;
use Pluck\Storage\DriverFactory;

$app = Bootstrap::boot(__DIR__);
Csp::send($app->csp()->adminHeaders());

// The session cookie must go out with the headers, before a single byte of
// HTML: starting it lazily from inside the template silently loses the cookie
// and every form then reports itself as expired.
$app->session()->start();

/*
 * The installer is the first thing anyone sees, so it has to be translatable
 * before there is any storage to read a preference from. The language comes from
 * ?lang=, falls back to what the browser asks for, and then to English.
 */
$translator = $app->translator();
$available = $translator->available();
$chosen = Locale::tryFrom(is_string($_POST['language'] ?? null) ? $_POST['language'] : null)
	?? Locale::tryFrom(is_string($_GET['lang'] ?? null) ? $_GET['lang'] : null)
	?? Locale::negotiate($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null, $available)
	?? Locale::fallback();

// Only a language we actually have a catalogue for; otherwise the installer would
// present itself in a language it cannot speak.
if ($available !== [] && !in_array($chosen->code, $available, true)) {
	$chosen = Locale::fallback();
}
$translator->setLocale($chosen);
$t = static fn (string $key, array $params = []): string => Escaper::html($translator->get($key, $params));

$checks = Requirements::check(__DIR__);
$serverReady = Requirements::passes($checks);
$alreadyInstalled = $app->isInstalled();

$errors = [];
$done = false;
$input = [
	'site_title' => 'My website',
	'username' => '',
	'email' => '',
	'storage' => DriverFactory::FLAT_FILE,
	'timezone' => 'Europe/Amsterdam',
	'language' => $chosen->code,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled && $serverReady) {
	try {
		$app->csrf()->assert($_POST, $_SERVER);

		foreach (array_keys($input) as $key) {
			if (isset($_POST[$key]) && is_string($_POST[$key])) {
				$input[$key] = trim($_POST[$key]);
			}
		}
		$input['password'] = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

		$errors = (new Installer($app))->run($input);
		if ($errors === []) {
			$done = true;
			$app->csrf()->rotate();
			// Best effort: an installer left in a webroot is an invitation.
			@unlink(__FILE__);
		}
	} catch (Throwable $e) {
		$errors[] = $e->getMessage();
	}
}

$installerRemains = is_file(__FILE__);
$glyph = ['pass' => '+', 'note' => '~', 'fail' => '!'];
?>
<!DOCTYPE html>
<html lang="<?= Escaper::html($chosen->code) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $t('install.title') ?></title>
<link rel="stylesheet" href="assets/admin/pluck.css">
</head>
<body>
<div class="shell">

	<div class="wordmark">
		<b>pluck</b>
		<span><?= Escaper::html(Bootstrap::VERSION) ?></span>
	</div>

<?php if ($alreadyInstalled): ?>
	<div class="card">
		<h1><?= $t('install.already_installed') ?></h1>
		<p><?= $t('install.already_installed_body') ?></p>
		<?php if ($installerRemains): ?>
			<p class="muted"><?= $t('install.remove_installer') ?></p>
		<?php endif; ?>
		<p><a href="admin.php"><?= $t('install.go_to_admin') ?></a></p>
	</div>

<?php elseif ($done): ?>
	<div class="notice notice-ok">
		<h1><?= $t('install.ready') ?></h1>
		<p><?= $t('install.ready_body') ?></p>
	</div>
	<div class="card">
		<p><a href="admin.php"><?= $t('install.open_admin') ?></a></p>
		<?php if ($installerRemains): ?>
			<p class="muted"><?= $t('install.could_not_self_delete') ?></p>
		<?php endif; ?>
	</div>

<?php else: ?>
	<?php if (count($available) > 1): ?>
	<form method="get" action="install.php" class="card">
		<div class="field">
			<label for="lang"><?= $t('install.language') ?></label>
			<!-- No onchange handler: the admin CSP blocks inline event handlers,
			     nonce or not, so this is a plain form with a button. -->
			<select id="lang" name="lang">
<?php foreach ($available as $code): ?>
				<option value="<?= Escaper::html($code) ?>"<?= $code === $chosen->code ? ' selected' : '' ?>><?= Escaper::html($code) ?></option>
<?php endforeach; ?>
			</select>
		</div>
		<button type="submit"><?= $t('install.language') ?></button>
	</form>
	<?php endif; ?>

	<div class="card">
		<p class="eyebrow"><?= $t('install.step_one_of_two') ?></p>
		<h1><?= $t('install.what_server_offers') ?></h1>
		<ul class="checks">
<?php foreach ($checks as $check): ?>
			<li class="<?= Escaper::html($check['status']) ?>">
				<span class="glyph" aria-hidden="true"><?= Escaper::html($glyph[$check['status']]) ?></span>
				<span>
					<span class="name"><?= Escaper::html($check['name']) ?></span>
					<span class="detail"><?= Escaper::html($check['detail']) ?></span>
				</span>
			</li>
<?php endforeach; ?>
		</ul>
	</div>

<?php if (!$serverReady): ?>
	<div class="notice notice-stop">
		<p><?= $t('install.fix_first') ?></p>
	</div>
<?php else: ?>

	<?php if ($errors !== []): ?>
	<div class="notice notice-stop">
		<p><strong><?= $t('install.nothing_saved') ?></strong></p>
		<ul>
<?php foreach ($errors as $error): ?>
			<li><?= Escaper::html($error) ?></li>
<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>

	<form method="post" action="install.php" class="card">
		<?= $app->csrf()->field() ?>
		<!-- Carries the language chosen above through the POST: without it the
		     confirmation screen reverts to English and the site is installed in a
		     language nobody picked. -->
		<input type="hidden" name="language" value="<?= Escaper::html($chosen->code) ?>">
		<p class="eyebrow"><?= $t('install.step_two_of_two') ?></p>
		<h1><?= $t('install.set_up_your_site') ?></h1>

		<div class="field">
			<label for="site_title"><?= $t('install.site_name') ?></label>
			<input type="text" id="site_title" name="site_title" value="<?= Escaper::html($input['site_title']) ?>" required>
		</div>

		<div class="field">
			<label><?= $t('install.where_content_lives') ?></label>
			<span class="hint"><?= $t('install.storage_movable') ?></span>
			<div class="choices">
<?php foreach (DriverFactory::options() as $key => $option): ?>
				<label class="choice">
					<input type="radio" name="storage" value="<?= Escaper::html($key) ?>"
						<?= $input['storage'] === $key ? 'checked' : '' ?>
						<?= $option['available'] ? '' : 'disabled' ?>>
					<span>
						<b><?= Escaper::html($option['label']) ?></b>
						<span class="muted"><?= Escaper::html($option['available'] ? $option['description'] : $option['reason']) ?></span>
					</span>
				</label>
<?php endforeach; ?>
			</div>
		</div>

		<h2><?= $t('install.your_account') ?></h2>
		<div class="field">
			<label for="username"><?= $t('install.username') ?></label>
			<input type="text" id="username" name="username" value="<?= Escaper::html($input['username']) ?>" autocomplete="username" required>
		</div>

		<div class="field">
			<label for="password"><?= $t('install.password') ?></label>
			<span class="hint"><?= $t('install.password_hint') ?></span>
			<input type="password" id="password" name="password" autocomplete="new-password" required minlength="12">
		</div>

		<div class="field">
			<label for="email"><?= $t('install.email') ?></label>
			<span class="hint"><?= $t('install.email_hint') ?></span>
			<input type="email" id="email" name="email" value="<?= Escaper::html($input['email']) ?>" autocomplete="email">
		</div>

		<div class="field">
			<label for="timezone"><?= $t('install.timezone') ?></label>
			<select id="timezone" name="timezone">
<?php foreach (timezone_identifiers_list() as $zone): ?>
				<option value="<?= Escaper::html($zone) ?>"<?= $zone === $input['timezone'] ? ' selected' : '' ?>><?= Escaper::html($zone) ?></option>
<?php endforeach; ?>
			</select>
		</div>

		<button type="submit"><?= $t('install.submit') ?></button>
	</form>
<?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>
