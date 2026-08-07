<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Model\Role;
use Pluck\Support\Path;
use Pluck\Theme\ThemeRepository;
use Throwable;

/**
 * Editing the current theme's stylesheet.
 *
 * 4.x had a wider editor: the theme's PHP as well as its CSS. Only the CSS half
 * is here, and that limit is the design rather than a first instalment.
 *
 * A template in Pluck 5 is PHP the site executes, so an editor for one is an
 * editor for running code — the thing the whole security model was rebuilt to
 * avoid. And a mistake in a template takes the site down with a fatal error,
 * while the editor that would fix it lives on that same site. A stylesheet is
 * text: the worst outcome is an ugly page, still reachable, still fixable.
 *
 * One file, the one the theme actually loads. Not a file browser — a browser
 * over a theme directory is a way to reach the templates after all.
 */
final class StylesheetController extends Controller
{
	/** Kept beside the stylesheet, so a bad save is one click back. */
	private const PREVIOUS = 'style.previous.css';

	private const MAX_BYTES = 512000;

	public function edit(): never
	{
		$this->requireOwner();

		$path = $this->stylesheet();

		$this->render('admin/stylesheet', [
			'title' => $this->t('stylesheet.title.stylesheet'),
			'theme' => $this->themeName(),
			'css' => is_file($path) ? (string) file_get_contents($path) : '',
			'exists' => is_file($path),
			'writable' => is_file($path) ? is_writable($path) : is_writable(dirname($path)),
			'hasPrevious' => is_file($this->previous()),
		]);
	}

	public function save(): never
	{
		$this->requireOwner();

		$path = $this->stylesheet();
		$css = $this->c->request->post('css', '');

		if (strlen($css) > self::MAX_BYTES) {
			$this->c->flash->stop($this->t('stylesheet.flash.too_big'));
			$this->back('stylesheet');
		}

		// A stylesheet is text and stays text. Nothing here interprets it, so
		// there is nothing to sanitise — but it is written where the web server
		// serves it, and a file called style.css that contains PHP would be a
		// problem the moment somebody misconfigured a handler. The extension is
		// fixed and the content is never executed.
		if (!is_dir(dirname($path))) {
			$this->c->flash->stop($this->t('stylesheet.flash.no_theme'));
			$this->back('stylesheet');
		}

		if (is_file($path) && !@copy($path, $this->previous())) {
			// No copy, no save. The one thing this screen promises is that a bad
			// edit is undoable, and saving without the copy quietly removes that.
			$this->c->flash->stop($this->t('stylesheet.flash.no_backup'));
			$this->back('stylesheet');
		}

		if (@file_put_contents($path, $css) === false) {
			$this->c->flash->stop($this->t('stylesheet.flash.could_not_write'));
			$this->back('stylesheet');
		}

		@chmod($path, 0o644);

		$this->c->flash->ok($this->t('stylesheet.flash.saved'));
		$this->back('stylesheet');
	}

	/** Put back what was there before the last save. */
	public function undo(): never
	{
		$this->requireOwner();

		$previous = $this->previous();

		if (!is_file($previous)) {
			$this->c->flash->stop($this->t('stylesheet.flash.nothing_to_undo'));
			$this->back('stylesheet');
		}

		// Swapped rather than copied back, so undo can be undone — which matters
		// when somebody clicks it to see what it does.
		$current = (string) @file_get_contents($this->stylesheet());

		if (!@copy($previous, $this->stylesheet())) {
			$this->c->flash->stop($this->t('stylesheet.flash.could_not_write'));
			$this->back('stylesheet');
		}

		@file_put_contents($previous, $current);

		$this->c->flash->ok($this->t('stylesheet.flash.undone'));
		$this->back('stylesheet');
	}

	// ---- where the file is ----------------------------------------------

	/**
	 * The theme in use, resolved the way the site resolves it.
	 *
	 * This read the setting and cleaned the name itself, which is a third copy of
	 * a rule that lives in ThemeRepository — and it had the same consequence as
	 * the second one: on an install whose stored theme is missing, it offered to
	 * edit a stylesheet in a directory that is not there.
	 *
	 * active() falls back to a theme that loads, and a loaded Theme knows its own
	 * name, so nothing has to be sanitised back into one.
	 */
	private function themeName(): string
	{
		return (new ThemeRepository($this->c->app->rootDir . '/themes'))
			->active($this->c->storage)
			->name;
	}

	/**
	 * The stylesheet of the theme now in use.
	 *
	 * Built with Path::within so a theme name that somehow held a traversal
	 * cannot reach out of the themes directory, and the filename is a constant so
	 * this can never be pointed at a template.
	 */
	private function stylesheet(): string
	{
		return Path::within($this->c->app->rootDir . '/themes', $this->themeName() . '/assets/style.css');
	}

	private function previous(): string
	{
		return Path::within($this->c->app->rootDir . '/themes', $this->themeName() . '/assets/' . self::PREVIOUS);
	}

	private function requireOwner(): void
	{
		if ($this->c->auth->user()?->role !== Role::Owner) {
			$this->c->flash->stop($this->t('stylesheet.flash.owners_only'));
			$this->back('dashboard');
		}
	}
}
