<?php
declare(strict_types=1);

namespace Pluck\Form;

/**
 * What the guard decided.
 *
 * Three outcomes, not two. "Accepted" and "refused" are the obvious pair; the
 * third is a submission thrown away while telling the sender it went fine — what
 * a honeypot catch deserves, because a bot told which field gave it away is a bot
 * that comes back without filling it in.
 */
final class FormResult
{
	private function __construct(
		public readonly bool $ok,
		public readonly bool $stored,
		/** A translation key, never a finished sentence. */
		public readonly string $reason = '',
	) {
	}

	public static function accepted(): self
	{
		return new self(true, true);
	}

	/** Looks like success to whoever sent it, and is written nowhere. */
	public static function silentlyDiscarded(): self
	{
		return new self(true, false);
	}

	public static function refused(string $reason): self
	{
		return new self(false, false, $reason);
	}
}
