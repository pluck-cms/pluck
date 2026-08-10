<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Module\AlbumsModule;
use Pluck\Module\BlogModule;
use Pluck\Module\ContactModule;
use Pluck\Module\Insertable;
use Pluck\Storage\DriverFactory;

/**
 * What the editor's Pluck menu offers, and who decides it.
 *
 * A module says what can be inserted from it. The editor lists what it is given
 * and knows nothing: an editor aware that the blog takes `show=summary` would be
 * an editor carrying a copy of the blog's parameters, and the two drift the
 * first time either is touched.
 */
final class InsertMenuTest extends TestCase
{
	public function run(): void
	{
		$this->group('a module says what it offers', fn () => $this->options());
		$this->group('the editor knows nothing of modules', fn () => $this->ignorance());
		$this->group('something to fill in', fn () => $this->placeholders());
		$this->group('inserting where the cursor was', fn () => $this->cursor());
	}

	private function options(): void
	{
		$dir = $this->tempDir('pluck-insert');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$blog = new BlogModule();
		$this->assertTrue($blog instanceof Insertable, 'the blog says what it offers');

		$options = $blog->embedOptions($storage);

		$this->assertSame(2, count($options), 'titles and summaries on an empty blog');
		$this->assertSame('[module:blog count=5]', $options[0]['marker'], 'the plain one first');
		$this->assertTrue(
			str_contains($options[1]['marker'], 'show=summary'),
			'and the summaries, which is the parameter only the blog knows about',
		);

		// A category this site actually has, rather than the idea of categories.
		$storage->setModuleData('blog', 'category:recepten', ['name' => 'Recepten']);
		$withCategory = $blog->embedOptions($storage);

		$this->assertSame(3, count($withCategory), 'a category is offered once it exists');
		$this->assertTrue(
			str_contains($withCategory[2]['marker'], 'category=recepten'),
			'by its slug',
		);

		$storage->setModuleData('albums', 'album:open-dag', ['title' => 'Open dag']);
		$albums = (new AlbumsModule())->embedOptions($storage);

		$this->assertSame(2, count($albums), 'all albums, and this one');
		$this->assertSame('Open dag', $albums[1]['label'], 'listed by the name somebody gave it');

		// Optional: a module that does not implement it still appears, with the
		// bare marker — which is what every module had before this existed.
		$this->assertFalse(
			new ContactModule() instanceof Insertable,
			'a module need not implement it',
		);
	}

	/**
	 * Nothing in the editor names a module or one of its parameters.
	 *
	 * This is the assertion that matters. The markup and the script build a menu
	 * out of whatever they are handed; the moment either mentions `blog` or
	 * `show=summary`, there are two places that know the blog's parameters.
	 */
	private function ignorance(): void
	{
		$form = (string) file_get_contents(dirname(__DIR__) . '/views/admin/pages/form.php');
		$js = (string) file_get_contents(dirname(__DIR__) . '/assets/admin/pluck.js');

		foreach (['show=summary', 'module:blog', 'module:albums', 'category='] as $knowledge) {
			$this->assertFalse(
				str_contains($form, $knowledge),
				'the form does not know: ' . $knowledge,
			);
			$this->assertFalse(
				str_contains($js, $knowledge),
				'and neither does the script: ' . $knowledge,
			);
		}
	}

	/**
	 * A marker that cannot be complete says which part is missing.
	 *
	 * A video needs the address of a video and only the person inserting it knows
	 * which, so the module supplies a placeholder and names it in `select`. The
	 * editor selects that text after inserting, which is the difference between a
	 * placeholder that gets replaced and one that reaches the live site.
	 *
	 * Asserted on the plumbing rather than a bundled module: none of the bundled
	 * ones needs a placeholder, and the video module lives in its own repository.
	 */
	private function placeholders(): void
	{
		$form = (string) file_get_contents(dirname(__DIR__) . '/views/admin/pages/form.php');

		$this->assertTrue(
			str_contains($form, "data-select=\"<?= e(\$option['select']) ?>\""),
			'the menu passes the placeholder on',
		);
		$this->assertTrue(
			str_contains($form, "(\$option['select'] ?? '') !== ''"),
			'and omits it for an option that has none',
		);

		$js = (string) file_get_contents(dirname(__DIR__) . '/assets/admin/pluck.js');

		$this->assertTrue(str_contains($js, "getAttribute('data-select')"), 'the script reads it');
		$this->assertTrue(
			str_contains($js, 'snippet.indexOf(select)'),
			'and selects it in the textarea rather than leaving the cursor at the end',
		);

		$editor = (string) file_get_contents(dirname(__DIR__) . '/assets/admin/editor.js');

		$this->assertTrue(
			str_contains($editor, 'function selectText(needle)'),
			'and the visible editor does the same',
		);
		$this->assertTrue(
			str_contains($editor, 'createTreeWalker(editor, NodeFilter.SHOW_TEXT)'),
			'by walking text nodes, because an offset into markup is not one into text',
		);
	}

	/**
	 * A menu that takes focus has to give the cursor back.
	 *
	 * Clicking the menu button moves focus out of the field. In a textarea
	 * `selectionStart` then reads 0; in a contenteditable the selection collapses
	 * entirely. Either way whatever was chosen landed at the very beginning of
	 * the text rather than where somebody was working.
	 *
	 * The colour picker had this and it was fixed there; the Pluck menu was
	 * written afterwards and did not inherit it. Asserted for both now, because
	 * the next menu will not inherit it either.
	 */
	private function cursor(): void
	{
		$js = (string) file_get_contents(dirname(__DIR__) . '/assets/admin/pluck.js');
		$editor = (string) file_get_contents(dirname(__DIR__) . '/assets/admin/editor.js');

		$this->assertTrue(
			str_contains($js, "closest('.pluckmenu > summary')"),
			'the textarea records where the cursor was when the menu opened',
		);
		$this->assertTrue(
			str_contains($js, 'insertAt ? insertAt.start : field.selectionStart'),
			'and inserts there rather than wherever focus left it',
		);

		$this->assertTrue(
			str_contains($editor, '.swatches > summary, .pluckmenu > summary'),
			'the visible editor remembers for both of its menus',
		);
		$this->assertTrue(
			str_contains($editor, "restoreSelection();\n\t\tdocument.execCommand('insertHTML'"),
			'and puts the selection back before inserting',
		);
	}
}
