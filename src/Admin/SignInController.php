<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Auth\AttemptResult;
use Pluck\Http\Response;

final class SignInController extends Controller
{
	/** The same sentence for every kind of failure, so nothing is confirmed. */
	private const GENERIC_FAILURE = 'That username and password do not match an account.';

	public function form(): never
	{
		if ($this->c->auth->check()) {
			$this->back('dashboard');
		}

		$pending = $this->c->auth->pendingTwoFactorUser();

		$this->renderSignIn([
			'stage' => $pending !== null ? 'totp' : 'password',
			'username' => $pending?->username ?? '',
			'next' => $this->c->request->query('next'),
		]);
	}

	public function submit(): never
	{
		$request = $this->c->request;
		$username = $request->post('username');
		$password = $request->raw('password');
		$next = $request->post('next');

		$result = $this->c->auth->attempt($username, $password);

		match ($result) {
			AttemptResult::Ok => $this->afterSignIn($next),
			AttemptResult::TotpRequired => $this->renderSignIn([
				'stage' => 'totp',
				'username' => $username,
				'next' => $next,
			]),
			AttemptResult::Locked => $this->renderSignIn([
				'stage' => 'password',
				'username' => $username,
				'next' => $next,
				'error' => 'Too many attempts. Wait a quarter of an hour and try again.',
			]),
			default => $this->renderSignIn([
				'stage' => 'password',
				'username' => $username,
				'next' => $next,
				'error' => self::GENERIC_FAILURE,
			]),
		};
	}

	public function submitTwoFactor(): never
	{
		$request = $this->c->request;
		$next = $request->post('next');

		$result = $this->c->auth->completeTwoFactor($request->post('code'));

		match ($result) {
			AttemptResult::Ok => $this->afterSignIn($next),
			AttemptResult::Locked => $this->renderSignIn([
				'stage' => 'password',
				'next' => $next,
				'error' => 'Too many attempts. Wait a quarter of an hour and try again.',
			]),
			default => $this->renderSignIn([
				'stage' => 'totp',
				'username' => $this->c->auth->pendingTwoFactorUser()?->username ?? '',
				'next' => $next,
				'error' => 'That code was not accepted. Codes last thirty seconds — try the next one.',
			]),
		};
	}

	public function signOut(): never
	{
		$this->c->auth->logout();
		Response::redirect(self::url('signin'));
	}

	/**
	 * The GET side of signing out: ask, do not act.
	 *
	 * Signing out changes state, so it belongs behind a POST and a token like
	 * every other change. Acting on the GET made the admin logout-able by anyone
	 * who could get a signed-in admin to follow a link — the mildest member of
	 * the family of bugs version 4 had all over its ?action= handlers, but the
	 * same family. Someone arriving with an old bookmark still gets a working
	 * button rather than a dead end.
	 */
	public function confirmSignOut(): never
	{
		if (!$this->c->auth->check()) {
			Response::redirect(self::url('signin'));
		}

		Response::html($this->c->view->page('admin/signout', ['title' => $this->t('signin.title.sign_out')]));
	}

	private function afterSignIn(string $next): never
	{
		$user = $this->c->auth->user();

		if ($user !== null && $user->mustChangePassword) {
			$this->c->flash->warn($this->t('signin.flash.choose_own_password_before_you_carry'));
			$this->back('account');
		}

		// Only a known route name is honoured, so ?next= cannot bounce anyone off
		// this site.
		$target = $next !== '' && preg_match('/^[a-z][a-z0-9.\-]{0,39}$/', $next) ? $next : 'dashboard';

		$this->back($target);
	}

	private function renderSignIn(array $data): never
	{
		Response::html($this->c->view->partial('admin/signin', $data + [
			'siteTitle' => (string) $this->c->storage->getSetting('site_title', 'Pluck'),
			'error' => '',
			'username' => '',
			'next' => '',
		]));
	}
}
