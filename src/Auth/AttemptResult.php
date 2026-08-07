<?php
declare(strict_types=1);

namespace Pluck\Auth;

/**
 * Why a sign-in did or did not go through.
 *
 * The sign-in screen deliberately shows the same wording for BadCredentials and
 * Inactive: telling a stranger that an account exists but is switched off is a
 * free hint. The distinction exists here so the audit log can be honest.
 */
enum AttemptResult
{
	case Ok;
	case BadCredentials;
	case Inactive;
	case Locked;
	case TotpRequired;
	case TotpInvalid;

	public function isSuccess(): bool
	{
		return $this === self::Ok;
	}
}
