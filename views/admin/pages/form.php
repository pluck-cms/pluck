<?php
/**
 * The page editor.
 *
 * The content field is a textarea with a toolbar that wraps the selection in
 * tags. It is not a WYSIWYG surface — that lands with the theme work, once there
 * is a stylesheet to preview against — but it works on a phone keyboard, needs no
 * bundled editor, and whatever it produces goes through the sanitiser on save.
 *
 * @var \Pluck\Model\Page $page
 * @var bool $isNew
 * @var string $parent
 * @var array<string,string> $parents
 * @var \Pluck\View\View $view
 */

use Pluck\Admin\Controller;
?>
<div class="head">
	<h1><?= $isNew ? $view->t('ui.pages.form.new_page') : $view->t('ui.pages.form.edit_page') ?></h1>
	<?php if (!$isNew): ?>
		<!--
			Opens the site in another tab showing what is in this form right now.
			It submits rather than links, because the unsaved content has to reach
			the server somehow — and formtarget sends the result to a new tab
			without leaving this one.
		-->
		<button class="btn-quiet" type="submit"
		        form="page-form"
		        formaction="<?= e(Controller::url('page.preview.site')) ?>"
		        formtarget="_blank"><?= $view->t('page.action.preview') ?></button>
<?php if (!$isNew): ?>
		<a class="btn-quiet" href="index.php?page=<?= e(rawurlencode($page->path)) ?>" target="_blank" rel="noopener"><?= $view->t('page.action.view_published') ?></a>
<?php endif; ?>
	<?php endif; ?>
</div>

<form id="page-form" method="post" action="<?= e(Controller::url('page.save')) ?>" data-guard-unsaved>
	<?= $view->csrfField() ?>
	<input type="hidden" name="original" value="<?= e($isNew ? '' : $page->path) ?>">

	<div class="card">
		<div class="field">
			<label for="title"><?= $view->t('ui.pages.form.title') ?></label>
			<input id="title" name="title" type="text" value="<?= e($page->title) ?>" required
			       maxlength="200" data-slug-source>
		</div>

		<div class="field">
			<label for="content"><?= $view->t('ui.pages.form.content') ?></label>
			<div class="toolbar" role="group" aria-label="<?= e($view->t('ui.pages.form.formatting')) ?>" data-toolbar-for="content"
				data-link-prompt="<?= $view->t('page.editor.link_to_which_address') ?>"
				data-link-refused="<?= $view->t('page.editor.link_must_be_web_address') ?>">
				<button class="btn-icon" type="button" data-wrap="strong" aria-label="Bold" title="Ctrl+B"><b>B</b></button>
				<button class="btn-icon" type="button" data-wrap="em" aria-label="<?= e($view->t('ui.pages.form.italic')) ?>" title="Ctrl+I"><i>I</i></button>
				<button class="btn-icon" type="button" data-wrap="h2" aria-label="<?= e($view->t('ui.pages.form.heading')) ?>">H2</button>
				<button class="btn-icon" type="button" data-wrap="h3" aria-label="<?= e($view->t('ui.pages.form.sub_heading')) ?>">H3</button>
				<button class="btn-icon" type="button" data-wrap="p" aria-label="<?= e($view->t('ui.pages.form.paragraph')) ?>">¶</button>
				<button class="btn-icon" type="button" data-wrap="ul>li" aria-label="List">•</button>
				<button class="btn-icon" type="button" data-wrap="blockquote" aria-label="<?= e($view->t('ui.pages.form.quotation')) ?>">&rdquo;</button>
				<button class="btn-icon" type="button" data-table
				        aria-label="<?= e($view->t('page.table.title')) ?>"
				        title="<?= e($view->t('page.table.title')) ?>">&#9636;</button>
				<button class="btn-icon" type="button" data-wrap="code" aria-label="Code">&lt;&gt;</button>
				<button class="btn-icon" type="button" data-link aria-label="Link" title="Ctrl+K">↗</button>

<?php if ($palette !== []): ?>
				<!--
					Colour. A <details> rather than a script-driven popover: it opens
					and closes on its own, it closes when the page is clicked away
					from, and it cannot get stuck open the way a hand-rolled one can.
				-->
				<details class="swatches">
					<summary class="btn-icon" title="<?= e($view->t('page.colour.title')) ?>"
					         aria-label="<?= e($view->t('page.colour.title')) ?>">A</summary>
					<div class="swatches__grid">
<?php foreach ($palette as $name => $value): ?>
						<?php /* The colour comes from the class, not an inline style: the
						         admin's CSP has no 'unsafe-inline' for styles, and a
						         nonce does not apply to an attribute. */ ?>
						<button class="swatch c-<?= e($name) ?>" type="button" data-colour="<?= e($name) ?>"
						        title="<?= e(\Pluck\Site\Palette::label($name)) ?>"
						        aria-label="<?= e(\Pluck\Site\Palette::label($name)) ?>"></button>
<?php endforeach; ?>
						<button class="swatch swatch--none" type="button" data-colour=""
						        title="<?= e($view->t('page.colour.none')) ?>"
						        aria-label="<?= e($view->t('page.colour.none')) ?>">&times;</button>
					</div>
				</details>
<?php endif; ?>

				<!-- The way out. Whatever the editor does, the markup is one click
				     away — which is how anybody who knows HTML will work, and how
				     the rest will fix something that went strange. -->
				<button class="btn-icon btn-source" type="button" data-source-toggle
				        aria-pressed="false"
				        aria-label="<?= e($view->t('page.action.show_html')) ?>"
				        title="<?= e($view->t('page.action.show_html')) ?>">&lt;/&gt;</button>
			</div>
			<textarea id="content" name="content" rows="16" spellcheck="true"><?= e($page->content) ?></textarea>
			<span class="hint"><?= $view->t('page.help.editor') ?></span>


			<div class="preview" id="preview" data-preview-url="<?= e(Controller::url('page.preview')) ?>">
				<h3><?= $view->t('page.label.preview') ?></h3>
				<p class="hint"><?= $view->t('page.help.preview') ?></p>
				<p class="hint preview-removed" data-preview-note hidden
				   data-removed-prefix="<?= e($view->t('page.label.removed_on_save')) ?>"></p>
				<!-- sandbox="" and nothing else: no scripts, no forms, no navigation,
				     and no reach into this page. -->
				<iframe title="<?= e($view->t('page.label.preview')) ?>" sandbox="" referrerpolicy="no-referrer"></iframe>
			</div>

<?php if (($media['images'] ?? []) !== []): ?>
			<div class="insert-media">
				<label for="insert-media"><?= $view->t('page.label.insert_image') ?></label>
				<select id="insert-media" data-image-extensions="<?= e(implode(',', \Pluck\Media\MediaLibrary::IMAGE_EXTENSIONS)) ?>">
<?php foreach ($media['images'] as $group => $names): ?>
				<optgroup label="<?= $group !== '' ? e($group) : e($view->t('page.label.uploaded')) ?>">
<?php foreach ($names as $name): ?>
					<option value="<?= e($name) ?>"><?= e($name) ?></option>
<?php endforeach; ?>
				</optgroup>
<?php endforeach; ?>
				</select>
				<button class="btn-quiet" type="button"
				        data-insert-target="content" data-insert-source="insert-media"><?= $view->t('page.action.insert') ?></button>
				<span class="hint"><?= $view->t('page.help.insert_from_media') ?></span>
			</div>
<?php endif; ?>

<?php if (($media['files'] ?? []) !== []): ?>
			<!--
				Files are their own picker, as they were in 4.x. Inserting a
				photograph and linking to a PDF have nothing in common but the
				folder they live in: one goes into the page, the other is
				something to click.
			-->
			<div class="insert-media">
				<label for="insert-file"><?= $view->t('page.label.insert_file') ?></label>
				<select id="insert-file">
<?php foreach ($media['files'] as $group => $names): ?>
				<optgroup label="<?= $group !== '' ? e($group) : e($view->t('page.label.uploaded')) ?>">
<?php foreach ($names as $name): ?>
					<option value="<?= e($name) ?>"><?= e($name) ?></option>
<?php endforeach; ?>
				</optgroup>
<?php endforeach; ?>
				</select>
				<button class="btn-quiet" type="button" data-insert-file><?= $view->t('page.action.insert') ?></button>
				<span class="hint"><?= $view->t('page.help.insert_file') ?></span>
			</div>
<?php endif; ?>

<?php if (($embeddable ?? []) !== []): ?>
			<div class="insert-media">
				<label for="insert-module"><?= $view->t('page.label.insert_module') ?></label>
				<select id="insert-module">
<?php foreach ($embeddable as $name): ?>
					<option value="[module:<?= e($name) ?>]"><?= e($name) ?></option>
<?php endforeach; ?>
				</select>
				<button class="btn-quiet" type="button"
				        data-insert-target="content" data-insert-source="insert-module" data-insert-raw><?= $view->t('page.action.insert') ?></button>
				<span class="hint"><?= $view->t('page.help.insert_module') ?></span>
			</div>
<?php endif; ?>
		</div>
	</div>

	<details class="card"<?= $isNew ? '' : ' open' ?>>
		<summary><h2><?= $view->t('ui.pages.form.address_and_place_in_the_menu') ?></h2></summary>

		<div class="field">
			<label for="slug"><?= $view->t('ui.pages.form.address') ?></label>
			<span class="hint"><?= $view->t('ui.pages.form.leave_empty_to_build_it_from_the_title') ?></span>
			<input id="slug" name="slug" type="text" value="<?= e($isNew ? '' : $page->slug()) ?>"
			       maxlength="120" data-slug-target>
		</div>

		<div class="field">
			<label for="parent"><?= $view->t('ui.pages.form.sits_under') ?></label>
			<select id="parent" name="parent">
				<?php foreach ($parents as $value => $label): ?>
					<option value="<?= e($value) ?>"<?= $value === $parent ? ' selected' : '' ?>><?= e($label) ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<label class="choice">
			<input type="checkbox" name="hidden" value="1"<?= $page->hidden ? ' checked' : '' ?>>
			<span><b><?= $view->t('ui.pages.form.keep_out_of_the_menu') ?></b><span class="muted"><?= $view->t('ui.pages.form.the_page_still_works_if_you_know_the_address') ?></span></span>
		</label>
	</details>

	<details class="card">
		<summary><h2><?= $view->t('ui.pages.form.what_search_engines_see') ?></h2></summary>

		<div class="field">
			<label for="description"><?= $view->t('ui.pages.form.description') ?></label>
			<input id="description" name="description" type="text" maxlength="320" value="<?= e($page->description) ?>">
		</div>

		<div class="field">
			<label for="keywords"><?= $view->t('ui.pages.form.keywords') ?></label>
			<input id="keywords" name="keywords" type="text" maxlength="320" value="<?= e($page->keywords) ?>">
		</div>
	</details>

	<div class="actions">
		<!-- The preview posts this so the renderer knows which page it is looking
		     at — which page the menu should mark, and what its address will be. -->
		<input type="hidden" name="path" value="<?= e($page->path) ?>">

		<button type="submit"><?= $isNew ? $view->t('ui.pages.form.create_page') : $view->t('ui.pages.form.save_page') ?></button>

		<!-- 4.x had both, and the difference matters: one is for somebody still
		     working on a page, the other for somebody who is done with it. -->
		<button class="btn-quiet" type="submit" name="then" value="close"><?= $view->t('page.action.save_and_close') ?></button>
		<a class="btn-quiet" href="<?= e(Controller::url('pages')) ?>"><?= $view->t('ui.pages.form.cancel') ?></a>
	</div>
</form>

<?php if (!$isNew): ?>
	<div class="card card-danger">
		<h2><?= $view->t('ui.pages.form.delete_this_page') ?></h2>
		<p class="muted"><?= $view->t('ui.pages.form.sub_pages_have_to_be_moved_or_deleted_first') ?></p>
		<form method="post" action="<?= e(Controller::url('page.delete')) ?>"
		      data-confirm="Delete “<?= e($page->title) ?>”?">
			<?= $view->csrfField() ?>
			<input type="hidden" name="path" value="<?= e($page->path) ?>">
			<button class="btn-danger" type="submit"><?= $view->t('ui.pages.form.delete_page') ?></button>
		</form>



	</div>
<?php endif; ?>

<!--
	The link dialogue.
	A <dialog> rather than four prompt() boxes in a row: a prompt cannot show what
	is already there, cannot be cancelled halfway without losing the rest, and
	cannot hold a checkbox at all.
-->
<!-- The tags a paste may keep, so the editor does not carry its own copy of a
     list that lives in the sanitiser. -->
<div hidden data-allowed-tags="<?= e(implode(',', \Pluck\Security\Sanitizer::allowedTags())) ?>"></div>

<dialog class="dialog" id="table-dialog">
	<div class="dialog__body">
		<h2><?= $view->t('page.table.title') ?></h2>

		<div class="dialog__pair">
			<label class="field">
				<span><?= $view->t('page.table.columns') ?></span>
				<input type="number" name="columns" min="1" max="12" value="3">
			</label>

			<label class="field">
				<span><?= $view->t('page.table.rows') ?></span>
				<input type="number" name="rows" min="1" max="50" value="3">
			</label>
		</div>

		<label class="choice">
			<input type="checkbox" name="header" value="1" checked>
			<span><?= $view->t('page.table.header_row') ?></span>
		</label>

		<p class="muted"><?= $view->t('page.table.help') ?></p>

		<div class="dialog__actions">
			<button type="button" data-table-cancel class="btn-quiet"><?= $view->t('page.link.cancel') ?></button>
			<button type="button" data-table-apply class="btn"><?= $view->t('page.table.apply') ?></button>
		</div>
	</div>
</dialog>

<dialog class="dialog" id="link-dialog"
        data-bad-address="<?= e($view->t('page.link.bad_address')) ?>"
        data-needs-text="<?= e($view->t('page.link.needs_text')) ?>">
	<div class="dialog__body">
		<h2><?= $view->t('page.link.title') ?></h2>

		<label class="field">
			<span><?= $view->t('page.link.address') ?></span>
			<input type="text" name="href" required placeholder="https://" autocomplete="off">
			<span class="muted"><?= $view->t('page.link.address_help') ?></span>
		</label>

		<label class="field">
			<span><?= $view->t('page.link.text') ?></span>
			<input type="text" name="text" autocomplete="off">
		</label>

		<label class="field">
			<span><?= $view->t('page.link.hover') ?></span>
			<input type="text" name="title" autocomplete="off">
			<span class="muted"><?= $view->t('page.link.hover_help') ?></span>
		</label>

		<label class="choice">
			<input type="checkbox" name="blank" value="1">
			<span><?= $view->t('page.link.new_window') ?></span>
		</label>

		<p class="dialog__error" data-link-error hidden></p>

		<div class="dialog__actions">
			<button type="button" data-link-cancel class="btn-quiet"><?= $view->t('page.link.cancel') ?></button>
			<button type="button" data-link-remove class="btn-quiet" hidden><?= $view->t('page.link.remove') ?></button>
			<button type="button" data-link-apply class="btn"><?= $view->t('page.link.apply') ?></button>
		</div>
	</div>
</dialog>
