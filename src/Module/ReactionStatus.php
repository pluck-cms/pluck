<?php
declare(strict_types=1);

namespace Pluck\Module;

/**
 * What has been decided about a reaction.
 *
 * Only meaningful when the blog's moderation setting is on. With it off every
 * reaction is shown, and the status is still recorded — so turning moderation on
 * later is a setting change rather than a migration, and turning it off does not
 * throw away decisions someone already made.
 *
 * Imported reactions arrive Approved. They were visible on the old site for years
 * and hiding them behind a queue on the day of the move would be a surprise, not
 * a safety measure.
 */
enum ReactionStatus: string
{
	case Pending = 'pending';
	case Approved = 'approved';
	case Spam = 'spam';

	public static function from_(mixed $raw): self
	{
		return self::tryFrom(is_string($raw) ? $raw : '') ?? self::Approved;
	}

	/** Whether a visitor sees it, given the moderation setting. */
	public function isVisible(bool $moderated): bool
	{
		if ($this === self::Spam) {
			// Spam stays hidden either way. Someone marked it; turning moderation
			// off should not undo that.
			return false;
		}

		return !$moderated || $this === self::Approved;
	}

	public function labelKey(): string
	{
		return 'blog.reaction_status.' . $this->value;
	}
}
