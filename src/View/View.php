<?php
declare(strict_types=1);

namespace Pluck\View;

use Pluck\I18n\Translator;
use Pluck\Security\Csp;
use Pluck\Security\Csrf;
use Pluck\Support\Path;
use RuntimeException;

/**
 * Plain PHP templates. No engine, no compiled cache, no build step.
 *
 * The rule that replaces auto-escaping: every value printed in a template goes
 * through e(), and the two exceptions — page content that the sanitiser has
 * already cleaned, and markup this class builds itself — are wrapped in raw()
 * so they are visible in review.
 */
final class View
{
	private array $shared = [];

	public function __construct(
		private readonly string $viewDir,
		private readonly Csrf $csrf,
		private readonly Csp $csp,
		private readonly ?Translator $translator = null,
	) {
	}

	/**
	 * A translated string, escaped for HTML.
	 *
	 * Escaped here on purpose, and there is deliberately no raw variant. A theme
	 * may ship its own lang/*.json and a theme arrives as an uploaded archive, so a
	 * translation is attacker-influenceable in the same way page content is. This
	 * way a hostile translation can produce visible text and nothing else.
	 *
	 * Placeholder values are escaped individually before substitution, so a value
	 * cannot smuggle markup in either.
	 *
	 * @param array<string,string|int|float> $replacements
	 */
	public function t(string $key, array $replacements = [], ?int $count = null): string
	{
		if ($this->translator === null) {
			return htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}

		$escaped = [];
		foreach ($replacements as $name => $value) {
			$escaped[$name] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}

		$text = $this->translator->get($key, [], $count);
		$text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		foreach ($escaped as $name => $value) {
			$text = str_replace('{' . $name . '}', $value, $text);
		}

		return $text;
	}

	public function translator(): ?Translator
	{
		return $this->translator;
	}

	public function share(string $key, mixed $value): void
	{
		$this->shared[$key] = $value;
	}

	/** Render a template inside the admin layout and send it. */
	public function page(string $template, array $data = []): string
	{
		return $this->wrap($this->partial($template, $data), $data);
	}

	/**
	 * Put already-rendered markup inside the admin layout.
	 *
	 * Split out of page() so a module can render its own template, from its own
	 * directory, and still get the core frame around it — a module supplies the
	 * middle of the page and never the layout, which is what keeps the admin
	 * navigation and the sign-out button out of its reach.
	 *
	 * The content goes on the left of `+` on purpose: that operator keeps the
	 * left-hand value, so a caller who happens to pass a 'content' key of their
	 * own cannot replace the body of the page with it.
	 *
	 * @param array<string,mixed> $data
	 */
	public function wrap(string $html, array $data = []): string
	{
		return $this->partial('admin/layout', ['content' => new Raw($html)] + $data);
	}

	/** Render a template on its own. */
	public function partial(string $template, array $data = []): string
	{
		$file = Path::within($this->viewDir, $template . '.php');
		if (!is_file($file)) {
			throw new RuntimeException('View not found: ' . $template);
		}

		$scope = $this->shared + $data + [
			'csrf' => $this->csrf,
			'locale' => $this->translator?->locale()->code ?? 'en',
			'nonce' => $this->csp->nonce(),
			'view' => $this,
		];

		ob_start();
		try {
			(static function (string $__file, array $__scope): void {
				extract($__scope, EXTR_SKIP);
				require $__file;
			})($file, $scope);
		} catch (\Throwable $e) {
			ob_end_clean();
			throw $e;
		}

		return (string) ob_get_clean();
	}

	/** The hidden CSRF input, ready to print. */
	public function csrfField(): Raw
	{
		return new Raw($this->csrf->field());
	}
}
