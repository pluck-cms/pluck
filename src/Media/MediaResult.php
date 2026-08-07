<?php
declare(strict_types=1);

namespace Pluck\Media;

/**
 * What happened when a file was offered to the media folder.
 *
 * A reason code rather than a message, so the media screen and a module screen
 * can each phrase it their own way — and so the reason survives into a test
 * without anyone matching on translated text.
 */
final class MediaResult
{
	public const NO_FILE = 'no_file';

	public const TOO_LARGE_FOR_SERVER = 'too_large_for_server';

	public const TOO_LARGE = 'too_large';

	public const CUT_SHORT = 'cut_short';

	public const NOWHERE_TO_PUT_IT = 'nowhere_to_put_it';

	public const NOT_AN_UPLOAD = 'not_an_upload';

	public const NO_EXTENSION = 'no_extension';

	public const EXTENSION_REFUSED = 'extension_refused';

	public const CONTENTS_DISAGREE = 'contents_disagree';

	public const UNREADABLE_IMAGE = 'unreadable_image';

	public const COULD_NOT_WRITE = 'could_not_write';

	public const FAILED = 'failed';

	/** Not a failure: this exact file is already here, under another name. */
	public const DUPLICATE = 'duplicate';

	private function __construct(
		public readonly bool $ok,
		public readonly string $name = '',
		public readonly string $reason = '',
		public readonly string $detail = '',
		/** The content hash, for showing to whoever wants to check the file arrived intact. */
		public readonly string $hash = '',
	) {
	}

	public static function stored(string $name, string $hash = ''): self
	{
		return new self(true, name: $name, hash: $hash);
	}

	/**
	 * This exact content is already here, as $name.
	 *
	 * Reported rather than written, and not treated as an error: the caller
	 * decides whether to point at what is already there or keep a second copy.
	 * Nothing is held anywhere in the meantime — the bytes are on disk already,
	 * so "keep both" is a copy of a file rather than a rescued upload.
	 */
	public static function duplicate(string $name, string $hash): self
	{
		return new self(false, name: $name, reason: self::DUPLICATE, hash: $hash);
	}

	public function isDuplicate(): bool
	{
		return $this->reason === self::DUPLICATE;
	}

	public static function failed(string $reason, string $detail = ''): self
	{
		return new self(false, reason: $reason, detail: $detail);
	}
}
