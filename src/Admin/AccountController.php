<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\I18n\Locale;
use Pluck\Auth\Totp;

/**
 * The screen every signed-in account can reach, whatever its role: own name,
 * own password, own two-factor.
 */
final class AccountController extends Controller
{
	private const MIN_PASSWORD = 12;
	private const PENDING_SECRET = '_pluck_totp_setup';

	public function show(): never
	{
		$user = $this->c->auth->user();
		$pending = $this->c->app->session()->get(self::PENDING_SECRET);

		$this->render('admin/account', [
			'title' => $this->t('account.title.account'),
			'user' => $user,
			'pendingSecret' => is_string($pending) ? $pending : null,
			'pendingUri' => is_string($pending) && $user !== null
				? Totp::uri($pending, $user->username, (string) $this->c->storage->getSetting('site_title', 'Pluck'))
				: null,
			'languages' => $this->c->app->translator()->available(),
			'siteLanguage' => $this->c->app->siteLocale()->code,
		]);
	}

	public function saveProfile(): never
	{
		$user = $this->c->auth->user();
		if ($user === null) {
			$this->back('signin');
		}

		$request = $this->c->request;
		$display = $request->post('display_name');
		$user->displayName = $display !== '' ? mb_substr($display, 0, 80) : $user->username;
		$user->email = mb_substr($request->post('email'), 0, 190);

		/*
		 * The language is validated through Locale, not trusted as typed: it ends up
		 * as a filename when the catalogue is loaded. An empty choice means "follow
		 * the site", which is stored as null rather than as a copy of the site value,
		 * so changing the site language moves everyone who never chose one.
		 */
		$requested = $request->post('language');
		if ($requested === '') {
			$user->language = null;
		} else {
			$locale = Locale::tryFrom($requested);
			$available = $this->c->app->translator()->available();
			$user->language = $locale !== null && in_array($locale->code, $available, true) ? $locale->code : $user->language;
		}

		$this->c->storage->saveUser($user);
		$this->c->flash->ok($this->t('account.flash.saved'));
		$this->back('account');
	}

	public function savePassword(): never
	{
		$user = $this->c->auth->user();
		if ($user === null) {
			$this->back('signin');
		}

		$request = $this->c->request;
		$current = $request->raw('current_password');
		$new = $request->raw('new_password');
		$again = $request->raw('confirm_password');

		// The current password is required even though the session is already
		// authenticated: it is what stops a borrowed, unlocked browser from
		// becoming a permanent takeover.
		if (!$user->verify($current)) {
			$this->c->flash->stop($this->t('account.flash.not_current_password'));
			$this->back('account');
		}

		if (strlen($new) < self::MIN_PASSWORD) {
			$this->c->flash->stop($this->t('account.flash.use_at_least_length', ['count' => self::MIN_PASSWORD]));
			$this->back('account');
		}

		if (!hash_equals($new, $again)) {
			$this->c->flash->stop($this->t('account.flash.two_new_passwords_not_same'));
			$this->back('account');
		}

		$user->setPassword($new);
		$user->mustChangePassword = false;
		$this->c->storage->saveUser($user);

		// A password change invalidates other sessions by design.
		$this->c->app->session()->regenerate();
		$this->c->csrf->rotate();

		$this->c->flash->ok($this->t('account.flash.password_changed'));
		$this->back('account');
	}

	/** Step one: hand out a secret and wait for a code proving it was stored. */
	public function beginTwoFactor(): never
	{
		$this->c->app->session()->set(self::PENDING_SECRET, Totp::secret());
		$this->back('account');
	}

	public function confirmTwoFactor(): never
	{
		$user = $this->c->auth->user();
		$session = $this->c->app->session();
		$secret = $session->get(self::PENDING_SECRET);

		if ($user === null || !is_string($secret) || $secret === '') {
			$this->c->flash->stop($this->t('account.flash.start_two_factor_setup_again'));
			$this->back('account');
		}

		if (!Totp::verify($secret, $this->c->request->post('code'))) {
			$this->c->flash->stop($this->t('account.flash.code_not_accepted_check_time_phone'));
			$this->back('account');
		}

		$user->totpSecret = $secret;
		$this->c->storage->saveUser($user);
		$session->forget(self::PENDING_SECRET);

		$this->c->flash->ok($this->t('account.flash.two_factor_you_will_need_code'));
		$this->back('account');
	}

	public function disableTwoFactor(): never
	{
		$user = $this->c->auth->user();
		if ($user === null) {
			$this->back('signin');
		}

		if (!$user->verify($this->c->request->raw('current_password'))) {
			$this->c->flash->stop($this->t('account.flash.enter_password_switch_two_factor_off'));
			$this->back('account');
		}

		$user->totpSecret = null;
		$this->c->storage->saveUser($user);
		$this->c->app->session()->forget(self::PENDING_SECRET);

		$this->c->flash->warn($this->t('account.flash.two_factor_off'));
		$this->back('account');
	}
}
