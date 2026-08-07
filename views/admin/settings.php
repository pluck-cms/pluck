<?php
/**
 * @var string $siteTitleValue @var string $siteDescription
 * @var bool $searchEnabled @var bool $updatesEnabled
 * @var string $timezone @var list<string> $timezones @var float $maxMb
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<h1><?= $view->t('nav.settings') ?></h1>

<form method="post" action="<?= e(Controller::url('settings.save')) ?>">
	<?= $view->csrfField() ?>

	<div class="card">
		<h2><?= $view->t('ui.settings.this_site') ?></h2>

		<div class="field">
			<label for="site_title"><?= $view->t('ui.settings.title') ?></label>
			<input id="site_title" name="site_title" type="text" maxlength="120" required
			       value="<?= e($siteTitleValue) ?>">
		</div>

		<div class="field">
			<label for="site_description"><?= $view->t('ui.settings.description') ?></label>
			<span class="hint"><?= $view->t('ui.settings.used_as_the_default_description_for_pages_th') ?></span>
			<input id="site_description" name="site_description" type="text" maxlength="320"
			       value="<?= e($siteDescription) ?>">
		</div>

		<div class="field">
			<label for="timezone"><?= $view->t('ui.settings.timezone') ?></label>
			<span class="hint"><?= $view->t('ui.settings.dates_in_the_admin_and_on_the_site_are_shown') ?></span>
			<select id="timezone" name="timezone">
				<?php foreach ($timezones as $zone): ?>
					<option value="<?= e($zone) ?>"<?= $zone === $timezone ? ' selected' : '' ?>><?= e($zone) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="card">
		<h2><?= $view->t('settings.heading.appearance') ?></h2>

		<label class="field">
			<span><?= $view->t('settings.label.theme') ?></span>
			<select name="theme">
<?php foreach ($themes as $name): ?>
				<option value="<?= e($name) ?>"<?= $name === $activeTheme ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
			</select>
			<span class="muted"><?= $view->t('settings.help.theme') ?></span>
		</label>

		<label class="field">
			<span><?= $view->t('settings.label.site_language') ?></span>
			<select name="site_language">
<?php foreach ($languages as $code): ?>
				<option value="<?= e($code) ?>"<?= $code === $siteLanguage ? ' selected' : '' ?>><?= e(strtoupper($code)) ?></option>
<?php endforeach; ?>
			</select>
			<span class="muted"><?= $view->t('settings.help.site_language') ?></span>
		</label>

		<label class="field">
			<span><?= $view->t('settings.label.logo') ?></span>
			<select name="site_logo">
				<option value=""><?= $view->t('settings.label.no_logo') ?></option>
<?php foreach ($media as $name): ?>
				<option value="<?= e($name) ?>"<?= $name === $logo ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
			</select>
			<span class="muted"><?= $view->t('settings.help.logo') ?></span>
		</label>

		<label class="field">
			<span><?= $view->t('settings.label.tagline') ?></span>
			<input type="text" name="site_tagline" maxlength="120" value="<?= e($tagline) ?>">
			<span class="muted"><?= $view->t('settings.help.tagline') ?></span>
		</label>

		<label class="field">
			<span><?= $view->t('settings.label.form_challenge') ?></span>
			<select name="form_challenge">
<?php foreach (['sum', 'recaptcha', 'none'] as $option): ?>
				<option value="<?= e($option) ?>"<?= $formChallenge === $option ? ' selected' : '' ?>><?= $view->t('settings.challenge.' . $option) ?></option>
<?php endforeach; ?>
			</select>
			<span class="muted"><?= $view->t('settings.help.form_challenge') ?></span>
		</label>

		<label class="field">
			<span><?= $view->t('settings.label.recaptcha_site_key') ?></span>
			<input type="text" name="recaptcha_site_key" value="<?= e($recaptchaSiteKey) ?>" autocomplete="off">
		</label>

		<label class="field">
			<span><?= $view->t('settings.label.recaptcha_secret') ?></span>
			<input type="text" name="recaptcha_secret" value="<?= e($recaptchaSecret) ?>" autocomplete="off">
			<span class="muted"><?= $view->t('settings.help.recaptcha') ?></span>
		</label>

		<label class="choice">
			<input type="checkbox" name="pretty_urls" value="1"<?= $prettyUrls ? ' checked' : '' ?>>
			<span><b><?= $view->t('settings.label.pretty_urls') ?></b><span class="muted"><?= $view->t('settings.help.pretty_urls') ?></span></span>
		</label>
	</div>

	<div class="card">
		<h2><?= $view->t('ui.settings.features') ?></h2>

		<label class="choice">
			<input type="checkbox" name="search_enabled" value="1"<?= $searchEnabled ? ' checked' : '' ?>>
			<span><b><?= $view->t('ui.settings.search_box_on_the_site') ?></b><span class="muted"><?= $view->t('ui.settings.visitors_can_search_page_titles_and_text') ?></span></span>
		</label>

		<label class="choice">
			<input type="checkbox" name="updates_check_enabled" value="1"<?= $updatesEnabled ? ' checked' : '' ?>>
			<span><b><?= $view->t('ui.settings.check_for_pluck_updates') ?></b><span class="muted"><?= $view->t('ui.settings.asks_github_once_a_day_whether_a_newer_relea') ?></span></span>
		</label>

		<div class="field spaced">
			<label for="media_max_mb"><?= $view->t('ui.settings.largest_upload') ?></label>
			<span class="hint"><?= $view->t('ui.settings.in_megabytes_your_server_s_own_limit_still_a') ?></span>
			<input id="media_max_mb" name="media_max_mb" type="text" inputmode="decimal" value="<?= e($maxMb) ?>">
		</div>
	</div>

	<div class="actions">
		<button type="submit"><?= $view->t('ui.settings.save_settings') ?></button>
	</div>
</form>
