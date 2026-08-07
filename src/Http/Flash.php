<?php
declare(strict_types=1);

namespace Pluck\Http;

use Pluck\Security\Session;

/**
 * One-shot messages that survive a redirect. Levels match the notice styles in
 * pluck.css: ok, warn, stop.
 */
final class Flash
{
	private const KEY = '_pluck_flash';

	public function __construct(private readonly Session $session)
	{
	}

	public function ok(string $message): void
	{
		$this->add('ok', $message);
	}

	public function warn(string $message): void
	{
		$this->add('warn', $message);
	}

	public function stop(string $message): void
	{
		$this->add('stop', $message);
	}

	public function add(string $level, string $message): void
	{
		$messages = $this->session->get(self::KEY, []);
		if (!is_array($messages)) {
			$messages = [];
		}
		$messages[] = ['level' => $level, 'message' => $message];
		$this->session->set(self::KEY, $messages);
	}

	/** @return list<array{level:string,message:string}> */
	public function drain(): array
	{
		$messages = $this->session->get(self::KEY, []);
		$this->session->forget(self::KEY);

		return is_array($messages) ? array_values($messages) : [];
	}
}
