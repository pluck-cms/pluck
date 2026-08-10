/*
 * A visible editor for the page content.
 *
 * Pluck 4 shipped TinyMCE and its users learned to expect one. Asking them to
 * type HTML instead is asking them why they are using a CMS — so this exists,
 * and it is written here rather than pulled in because Pluck's promise is that
 * you unpack it and it runs. No npm, no CDN, no build step.
 *
 * ## Why this is not the security boundary
 *
 * The sanitiser is. Whatever comes out of here goes through the allow-list on
 * save, so a bug in this file cannot put anything into a page that a hand-typed
 * <script> could not. That is what makes a visible editor a reasonable thing to
 * have at all: it is a convenience over the textarea, not a layer that has to be
 * trusted.
 *
 * ## What it does and does not do
 *
 * The buttons are exactly what the allow-list accepts — bold, italic, two
 * heading levels, paragraph, lists, quotation, code, link, image. Nothing else,
 * because anything else is a button that produces markup the save will quietly
 * remove.
 *
 * The textarea underneath is still the field that gets submitted. This writes
 * into it. Press "HTML" and it comes back, which is the way out when the editor
 * does something odd — and the way anybody who does know HTML will work.
 *
 * With JavaScript off, none of this happens and the textarea is the editor,
 * exactly as before.
 */
(function () {
	var field = document.getElementById('content');
	if (!field || !window.getSelection || !document.execCommand) {
		return;
	}

	var toolbar = document.querySelector('[data-toolbar-for="content"]');
	if (!toolbar) {
		return;
	}

	/* ---- the editable surface ---------------------------------------- */

	var editor = document.createElement('div');
	editor.className = 'editor-surface';
	editor.contentEditable = 'true';
	editor.setAttribute('role', 'textbox');
	editor.setAttribute('aria-multiline', 'true');
	editor.setAttribute('aria-label', field.getAttribute('data-editor-label') || 'Content');
	editor.innerHTML = field.value;

	field.parentNode.insertBefore(editor, field);
	field.classList.add('is-source');
	field.hidden = true;

	/*
	 * execCommand emits <b> and <i> rather than spans with inline styles when
	 * this is off. Both are on the allow-list; a span with a style attribute is
	 * not, and would be stripped on save — an editor whose bold button does
	 * nothing you can see afterwards.
	 */
	try {
		document.execCommand('styleWithCSS', false, false);
	} catch (e) { /* not every browser has it, and the default is what we want */ }

	/* ---- keeping the textarea in step -------------------------------- */

	function sync() {
		if (field.hidden) {
			field.value = editor.innerHTML;
			/* The live preview listens on the textarea, and setting .value does
			   not fire anything on its own. */
			field.dispatchEvent(new Event('input', { bubbles: true }));
		}
	}

	editor.addEventListener('input', sync);
	editor.addEventListener('blur', sync);

	/* Belt and braces: whatever else happened, the form submits what is on
	   screen. */
	var form = field.closest('form');
	if (form) {
		form.addEventListener('submit', sync);
	}

	/* ---- switching to the markup ------------------------------------- */

	var sourceButton = toolbar.querySelector('[data-source-toggle]');

	if (sourceButton) {
		sourceButton.addEventListener('click', function () {
			var showingSource = !field.hidden;

			if (showingSource) {
				editor.innerHTML = field.value;
				field.hidden = true;
				editor.hidden = false;
				editor.focus();
			} else {
				sync();
				field.hidden = false;
				editor.hidden = true;
				field.focus();
			}

			sourceButton.setAttribute('aria-pressed', String(!showingSource));
			toolbar.classList.toggle('is-source', !showingSource);
		});
	}

	/* ---- the buttons -------------------------------------------------- */

	var BLOCKS = { h2: 'H2', h3: 'H3', p: 'P', blockquote: 'BLOCKQUOTE', pre: 'PRE' };

	toolbar.addEventListener('click', function (event) {
		var button = event.target.closest('button');
		if (!button || button === sourceButton) {
			return;
		}

		/* When the markup is showing, the old textarea behaviour applies and this
		   file stays out of the way. */
		if (!field.hidden) {
			return;
		}

		var wrap = button.getAttribute('data-wrap');
		var isLink = button.hasAttribute('data-link');

		if (!wrap && !isLink) {
			return;
		}

		event.preventDefault();
		editor.focus();

		if (isLink) {
			applyLink();
		} else if (wrap === 'strong') {
			document.execCommand('bold');
		} else if (wrap === 'em') {
			document.execCommand('italic');
		} else if (wrap === 'ul>li') {
			document.execCommand('insertUnorderedList');
		} else if (wrap === 'code') {
			document.execCommand('formatBlock', false, 'pre');
		} else if (BLOCKS[wrap]) {
			document.execCommand('formatBlock', false, wrap);
		}

		sync();
	});

	/* ---- the link dialogue -------------------------------------------- */

	var dialog = document.getElementById('link-dialog');

	/*
	 * The selection has to be remembered.
	 *
	 * Opening a dialogue moves the focus, and moving the focus out of a
	 * contenteditable throws the selection away — so by the time somebody presses
	 * Add link, the editor no longer knows where they were. Saved on the way in
	 * and put back on the way out.
	 */
	var savedRange = null;

	function rememberSelection() {
		var selection = window.getSelection();

		savedRange = selection && selection.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
	}

	function restoreSelection() {
		if (!savedRange) {
			return;
		}

		var selection = window.getSelection();
		selection.removeAllRanges();
		selection.addRange(savedRange);
	}

	/* The <a> the cursor is sitting in, if any: editing a link should show what
	   is already there rather than starting from nothing. */
	function currentLink() {
		var node = savedRange ? savedRange.startContainer : null;

		while (node && node !== editor) {
			if (node.nodeType === 1 && node.tagName === 'A') {
				return node;
			}
			node = node.parentNode;
		}

		return null;
	}

	/*
	 * Linking to a file is linking, with one field already answered.
	 *
	 * The same dialogue rather than a second one that looks like it: everything a
	 * link needs — what it is called, whether it opens in a new window, the hover
	 * text — a file needs too, and the only difference is that nobody should be
	 * retyping the address of a file they just picked from a list.
	 */
	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-insert-file]');
		if (!button || !dialog || !dialog.showModal) {
			return;
		}

		var picker = document.getElementById('insert-file');
		if (!picker || !picker.value) {
			return;
		}

		rememberSelection();

		var href = 'media/' + picker.value;
		var field = dialog.querySelector('[name="href"]');

		field.value = href;
		field.readOnly = true;

		dialog.querySelector('[name="text"]').value =
			(savedRange && savedRange.toString()) || picker.value;
		dialog.querySelector('[name="title"]').value = '';
		/* A PDF or a spreadsheet in the same tab replaces the page somebody was
		   reading, and the way back is the browser's own button. Ticked by
		   default, and still theirs to untick. */
		dialog.querySelector('[name="blank"]').checked = true;
		dialog.querySelector('[data-link-remove]').hidden = true;

		showError('');
		dialog.showModal();
		dialog.querySelector('[name="text"]').focus();
		dialog.querySelector('[name="text"]').select();
	});

	function applyLink() {
		if (!dialog || !dialog.showModal) {
			/*
			 * No <dialog>, which means a browser older than 2022. Rather than a
			 * row of prompt() boxes with English in them, the markup view is the
			 * fallback — it is always there, and typing an <a> is a thing the
			 * person in front of such a browser can certainly do.
			 */
			if (sourceButton) {
				sourceButton.click();
			}

			return;
		}

		rememberSelection();

		var existing = currentLink();
		var selected = savedRange ? savedRange.toString() : '';

		var hrefField = dialog.querySelector('[name="href"]');
		hrefField.readOnly = false;
		hrefField.value = existing ? existing.getAttribute('href') || '' : '';
		dialog.querySelector('[name="text"]').value = existing ? existing.textContent : selected;
		dialog.querySelector('[name="title"]').value = existing ? existing.getAttribute('title') || '' : '';
		dialog.querySelector('[name="blank"]').checked = existing
			? existing.getAttribute('target') === '_blank'
			: false;

		dialog.querySelector('[data-link-remove]').hidden = !existing;
		showError('');

		dialog.showModal();
		dialog.querySelector('[name="href"]').focus();
	}

	/*
	 * What counts as an address.
	 *
	 * javascript: in an href is precisely the payload the sanitiser exists to
	 * remove, and there is no reason to help somebody type it. Everything else is
	 * allowed through, because a page on this site is a perfectly good link and
	 * this is not the place to have opinions about addresses.
	 */
	function acceptable(href) {
		return /^(https?:\/\/|mailto:|tel:|#|\/|[a-z0-9._~-]+(\/|$|\?))/i.test(href)
			&& !/^\s*javascript:/i.test(href);
	}

	function showError(message) {
		if (!dialog) {
			return;
		}

		var box = dialog.querySelector('[data-link-error]');
		box.textContent = message;
		box.hidden = !message;
	}

	if (dialog) {
		dialog.addEventListener('click', function (event) {
			var button = event.target.closest('button');
			if (!button) {
				return;
			}

			if (button.hasAttribute('data-link-cancel')) {
				dialog.close();
				editor.focus();
				restoreSelection();

				return;
			}

			if (button.hasAttribute('data-link-remove')) {
				dialog.close();
				editor.focus();
				restoreSelection();
				document.execCommand('unlink');
				sync();

				return;
			}

			if (!button.hasAttribute('data-link-apply')) {
				return;
			}

			var href = dialog.querySelector('[name="href"]').value.trim();
			var text = dialog.querySelector('[name="text"]').value.trim();
			var title = dialog.querySelector('[name="title"]').value.trim();
			var blank = dialog.querySelector('[name="blank"]').checked;

            if (!acceptable(href)) {
				showError(dialog.getAttribute('data-bad-address') || '');

				return;
			}

			if (!text) {
				showError(dialog.getAttribute('data-needs-text') || '');

				return;
			}

			dialog.close();
			editor.focus();
			restoreSelection();

			/* An existing link is replaced whole. Setting attributes on it would
			   leave the old text behind when somebody changed it. */
			var existing = currentLink();
			if (existing) {
				var range = document.createRange();
				range.selectNode(existing);
				var selection = window.getSelection();
				selection.removeAllRanges();
				selection.addRange(range);
			}

			document.execCommand('insertHTML', false, anchor(href, text, title, blank));
			sync();
		});

		dialog.addEventListener('cancel', function () {
			editor.focus();
			restoreSelection();
		});
	}

	function anchor(href, text, title, blank) {
		var html = '<a href="' + escapeAttribute(href) + '"';

		if (title) {
			html += ' title="' + escapeAttribute(title) + '"';
		}

		if (blank) {
			/* noopener because a page opened with target=_blank can otherwise
			   reach back through window.opener and navigate the page it came
			   from — the tab it replaces looking exactly as it did. */
			html += ' target="_blank" rel="noopener noreferrer"';
		}

		return html + '>' + escapeText(text) + '</a>';
	}

	function escapeAttribute(value) {
		return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
	}

	function escapeText(value) {
		var node = document.createTextNode(value);
		var box = document.createElement('div');
		box.appendChild(node);

		return box.innerHTML;
	}

	/* ---- colour -------------------------------------------------------- */

	/*
	 * A class, not a colour.
	 *
	 * The selection is wrapped in <span class="c-name">, which is what the
	 * sanitiser keeps — it strips style attributes and <font> deliberately. A
	 * colour written into the content lives exactly as long as the theme it was
	 * chosen against; a class survives a theme change, and changing what the
	 * colour means is then one rule in a stylesheet rather than forty pages.
	 */
	function wrappingColour(node) {
		while (node && node !== editor) {
			if (node.nodeType === 1 && /^c-[a-z0-9-]+$/.test(node.className || '')) {
				return node;
			}
			node = node.parentNode;
		}

		return null;
	}

	function applyColour(name) {
		var selection = window.getSelection();
		if (!selection || selection.rangeCount === 0) {
			return;
		}

		var range = selection.getRangeAt(0);
		var existing = wrappingColour(range.commonAncestorContainer);

		if (existing) {
			if (name === '') {
				while (existing.firstChild) {
					existing.parentNode.insertBefore(existing.firstChild, existing);
				}
				existing.parentNode.removeChild(existing);
			} else {
				existing.className = 'c-' + name;
			}

			return;
		}

		if (name === '' || selection.isCollapsed) {
			return;
		}

		var span = document.createElement('span');
		span.className = 'c-' + name;

		try {
			range.surroundContents(span);
		} catch (e) {
			/*
			 * surroundContents refuses a selection crossing an element boundary —
			 * half a paragraph and half the next. Doing nothing is better than
			 * flattening the structure between them, which is what the usual
			 * extract-and-reinsert fallback costs.
			 */
			return;
		}
	}

	toolbar.addEventListener('click', function (event) {
		var swatch = event.target.closest('[data-colour]');
		if (!swatch || !field.hidden) {
			return;
		}

		event.preventDefault();
		restoreSelection();
		applyColour(swatch.getAttribute('data-colour') || '');
		sync();

		var picker = swatch.closest('details');
		if (picker) {
			picker.removeAttribute('open');
		}
	});

	// The selection is lost the moment the picker takes focus, so it is kept
	// when the picker opens and put back when a swatch is chosen.
	toolbar.addEventListener('mousedown', function (event) {
		if (event.target.closest('.swatches')) {
			rememberSelection();
		}
	});

	/* ---- tables -------------------------------------------------------- */

	var tableDialog = document.getElementById('table-dialog');

	toolbar.addEventListener('click', function (event) {
		var button = event.target.closest('[data-table]');
		if (!button || !field.hidden || !tableDialog || !tableDialog.showModal) {
			return;
		}

		event.preventDefault();
		rememberSelection();
		tableDialog.showModal();
	});

	if (tableDialog) {
		tableDialog.addEventListener('click', function (event) {
			var button = event.target.closest('button');
			if (!button) {
				return;
			}

			if (button.hasAttribute('data-table-cancel')) {
				tableDialog.close();
				editor.focus();
				restoreSelection();

				return;
			}

			if (!button.hasAttribute('data-table-apply')) {
				return;
			}

			var columns = clamp(tableDialog.querySelector('[name="columns"]').value, 1, 12);
			var rows = clamp(tableDialog.querySelector('[name="rows"]').value, 1, 50);
			var header = tableDialog.querySelector('[name="header"]').checked;

			tableDialog.close();
			editor.focus();
			restoreSelection();

			document.execCommand('insertHTML', false, tableMarkup(columns, rows, header));
			sync();
		});

		tableDialog.addEventListener('cancel', function () {
			editor.focus();
			restoreSelection();
		});
	}

	function clamp(value, low, high) {
		var number = parseInt(value, 10);

		return isNaN(number) ? low : Math.min(high, Math.max(low, number));
	}

	/*
	 * A table with a colgroup.
	 *
	 * The widths live on <col> rather than on every cell, so dragging a column
	 * edge changes one number instead of one per row — and <col width> is on the
	 * allow-list, which a style attribute is not and should not be.
	 */
	function tableMarkup(columns, rows, header) {
		var share = (100 / columns).toFixed(2);
		var html = '<table><colgroup>';

		for (var c = 0; c < columns; c++) {
			html += '<col width="' + share + '%">';
		}

		html += '</colgroup>';

		if (header) {
			html += '<thead><tr>';
			for (var h = 0; h < columns; h++) {
				html += '<th scope="col"><br></th>';
			}
			html += '</tr></thead>';
		}

		html += '<tbody>';
		for (var r = 0; r < (header ? rows - 1 : rows); r++) {
			html += '<tr>';
			for (var d = 0; d < columns; d++) {
				/* An empty cell has no height and cannot be clicked into. A <br>
				   gives it a line to put the cursor on. */
				html += '<td><br></td>';
			}
			html += '</tr>';
		}

		return html + '</tbody></table><p><br></p>';
	}

	/* ---- dragging a column edge --------------------------------------- */

	/*
	 * Grab within a few pixels of the line between two columns and drag.
	 *
	 * Done on the <col> elements, so one drag is one attribute. The neighbouring
	 * column gives up exactly what this one gains, which keeps the table the same
	 * width and stops one drag from pushing everything else off the page.
	 */
	var EDGE = 6;
	var dragging = null;

	editor.addEventListener('mousedown', function (event) {
		var cell = event.target.closest('th, td');
		if (!cell) {
			return;
		}

		var box = cell.getBoundingClientRect();
		if (event.clientX < box.right - EDGE) {
			return;
		}

		var table = cell.closest('table');
		if (!table) {
			return;
		}

		/*
		 * The colgroup may not be there.
		 *
		 * execCommand('insertHTML') cleans up the fragment it is given and
		 * browsers routinely drop <colgroup> on the way in — so the table looked
		 * right, the cursor turned into arrows because that is only CSS, and
		 * nothing happened when you pulled. Built here instead of trusting it to
		 * have survived, which also means a table that arrived by paste or by
		 * migration can be dragged too.
		 */
		var cols = columnsOf(table);
		var index = cell.cellIndex;

		if (!cols.length || index >= cols.length - 1) {
			/* The last column has no neighbour to take width from, so its edge is
			   the edge of the table and not a thing to drag. */
			return;
		}

		event.preventDefault();

		dragging = {
			table: table,
			left: cols[index],
			right: cols[index + 1],
			startX: event.clientX,
			leftStart: percentOf(cols[index]),
			rightStart: percentOf(cols[index + 1]),
			width: table.getBoundingClientRect().width
		};

		editor.classList.add('is-resizing');
	});

	document.addEventListener('mousemove', function (event) {
		if (!dragging) {
			return;
		}

		var moved = ((event.clientX - dragging.startX) / dragging.width) * 100;
		/* Neither column may vanish: a column of zero width cannot be grabbed
		   again, and the only way back would be the markup view. */
		var lowest = 5;
		var room = dragging.leftStart + dragging.rightStart;

		var left = Math.min(room - lowest, Math.max(lowest, dragging.leftStart + moved));

		dragging.left.setAttribute('width', left.toFixed(2) + '%');
		dragging.right.setAttribute('width', (room - left).toFixed(2) + '%');
	});

	document.addEventListener('mouseup', function () {
		if (!dragging) {
			return;
		}

		dragging = null;
		editor.classList.remove('is-resizing');
		sync();
	});

	/**
	 * The <col> elements of a table, made if they are not there.
	 *
	 * Widths are read off what the browser is currently showing, so building them
	 * changes nothing on screen — the first drag then adjusts real numbers rather
	 * than starting from an even split that was never true.
	 */
	function columnsOf(table) {
		var existing = table.querySelectorAll('colgroup > col');
		var header = table.rows && table.rows.length ? table.rows[0] : null;
		var count = header ? header.cells.length : 0;

		if (existing.length === count && count > 0) {
			return existing;
		}

		if (!count) {
			return [];
		}

		var old = table.querySelector('colgroup');
		if (old) {
			old.remove();
		}

		var total = table.getBoundingClientRect().width || 1;
		var group = document.createElement('colgroup');

		for (var i = 0; i < count; i++) {
			var col = document.createElement('col');
			var width = header.cells[i].getBoundingClientRect().width;

			col.setAttribute('width', ((width / total) * 100).toFixed(2) + '%');
			group.appendChild(col);
		}

		table.insertBefore(group, table.firstChild);

		return group.querySelectorAll('col');
	}

	function percentOf(col) {
		var value = parseFloat(col.getAttribute('width') || '');

		return isNaN(value) ? 0 : value;
	}

	/* ---- resizing a picture -------------------------------------------- */

	/*
	 * Click a picture, drag its corner.
	 *
	 * The pictures people put in a page come off a camera at four thousand pixels
	 * wide, and telling somebody to open an image editor first is telling them to
	 * go away. This writes a width attribute, which is on the allow-list, and
	 * removes the height so the shape stays the shape.
	 *
	 * The handle lives outside the editable area on purpose. Anything inside a
	 * contenteditable can be selected, dragged into the text, or deleted with the
	 * backspace key — a resize handle that ends up in somebody's paragraph is a
	 * bug that looks like magic.
	 */
	var frame = document.createElement('div');
	frame.className = 'img-frame';
	frame.hidden = true;
	frame.innerHTML = '<span class="img-frame__size"></span><span class="img-frame__grip"></span>';

	var holder = editor.parentNode;
	holder.classList.add('editor-holder');
	holder.appendChild(frame);

	var chosen = null;
	var sizing = null;

	function showFrame() {
		if (!chosen || !chosen.isConnected) {
			frame.hidden = true;
			chosen = null;

			return;
		}

		var picture = chosen.getBoundingClientRect();
		var around = holder.getBoundingClientRect();

		frame.hidden = false;
		frame.style.left = (picture.left - around.left) + 'px';
		frame.style.top = (picture.top - around.top) + 'px';
		frame.style.width = picture.width + 'px';
		frame.style.height = picture.height + 'px';

		frame.querySelector('.img-frame__size').textContent = Math.round(picture.width) + ' px';
	}

	editor.addEventListener('click', function (event) {
		var picture = event.target.closest('img');

		chosen = picture && editor.contains(picture) ? picture : null;
		showFrame();
	});

	/* The frame is drawn over the page, so it has to follow whatever moves. */
	editor.addEventListener('scroll', showFrame);
	window.addEventListener('resize', showFrame);
	editor.addEventListener('input', function () {
		window.setTimeout(showFrame, 0);
	});

	frame.addEventListener('mousedown', function (event) {
		if (!event.target.closest('.img-frame__grip') || !chosen) {
			return;
		}

		event.preventDefault();

		sizing = {
			startX: event.clientX,
			startWidth: chosen.getBoundingClientRect().width,
			/* The natural size is the ceiling. Scaling a picture up past its own
			   pixels does not add any, it only makes the blur bigger. */
			natural: chosen.naturalWidth || 0,
			room: editor.clientWidth - 32
		};

		holder.classList.add('is-sizing');
	});

	document.addEventListener('mousemove', function (event) {
		if (!sizing || !chosen) {
			return;
		}

		var widest = Math.min(sizing.room, sizing.natural || sizing.room);
		var width = Math.round(Math.min(widest, Math.max(40, sizing.startWidth + (event.clientX - sizing.startX))));

		chosen.setAttribute('width', String(width));
		/* Height removed rather than calculated: with only a width, the browser
		   keeps the shape and nothing can drift out of proportion. */
		chosen.removeAttribute('height');
		chosen.style.width = width + 'px';
		chosen.style.height = '';

		showFrame();
	});

	document.addEventListener('mouseup', function () {
		if (!sizing) {
			return;
		}

		sizing = null;
		holder.classList.remove('is-sizing');

		/* The inline style was only there to make the drag smooth; the attribute
		   is what gets saved, and leaving both would put a style attribute in the
		   page for the sanitiser to strip. */
		if (chosen) {
			chosen.style.width = '';
		}

		showFrame();
		sync();
	});

	/* ---- pasting ------------------------------------------------------ */

	/*
	 * Word, Google Docs and every other editor paste a wall of inline styles and
	 * class names. The sanitiser would strip them on save, which means the page
	 * would look one way while being edited and another once saved — the single
	 * most confusing thing an editor can do.
	 *
	 * So paste is cleaned here, to the same shape the allow-list accepts, and
	 * what you see is what will be kept.
	 */
	/*
	 * What a paste may keep.
	 *
	 * Read from the page rather than written here. It used to be its own list and
	 * it had drifted from the sanitiser's — hr, sub, sup, mark, q and the
	 * definition list were dropped on paste even though a save keeps them, so the
	 * page changed appearance in the direction nobody expects.
	 */
	var KEEP = (function () {
		var source = document.querySelector('[data-allowed-tags]');
		var names = (source && source.getAttribute('data-allowed-tags') || '').split(',');
		var map = {};

		names.forEach(function (name) {
			if (name) {
				map[name.trim().toUpperCase()] = 1;
			}
		});

		return map;
	})();

	var KEEP_ATTRIBUTES = { A: ['href', 'title'], IMG: ['src', 'alt', 'width', 'height'] };

	editor.addEventListener('paste', function (event) {
		var data = event.clipboardData;
		if (!data) {
			return;
		}

		event.preventDefault();

		var html = data.getData('text/html');
		var text = data.getData('text/plain');

		if (!html) {
			document.execCommand('insertText', false, text);

			return;
		}

		document.execCommand('insertHTML', false, clean(html));
	});

	function clean(html) {
		/* Parsed in a detached document, so nothing in the pasted markup can run
		   or load while it is being looked at. */
		var doc = document.implementation.createHTMLDocument('');
		doc.body.innerHTML = html;

		walk(doc.body);

		return doc.body.innerHTML;
	}

	function walk(node) {
		var children = Array.prototype.slice.call(node.childNodes);

		children.forEach(function (child) {
			if (child.nodeType === 3) {
				return;
			}

			if (child.nodeType !== 1) {
				child.remove();

				return;
			}

			walk(child);

			if (!KEEP[child.tagName]) {
				/* Unwrapped rather than deleted: a <span> around a sentence is not
				   markup anybody wants, but the sentence is. */
				while (child.firstChild) {
					child.parentNode.insertBefore(child.firstChild, child);
				}
				child.remove();

				return;
			}

			var allowed = KEEP_ATTRIBUTES[child.tagName] || [];
			Array.prototype.slice.call(child.attributes).forEach(function (attribute) {
				if (allowed.indexOf(attribute.name.toLowerCase()) === -1) {
					child.removeAttribute(attribute.name);
				}
			});

			if (child.tagName === 'A' && /^\s*javascript:/i.test(child.getAttribute('href') || '')) {
				child.removeAttribute('href');
			}
		});
	}

	/* ---- inserting from the pickers ----------------------------------- */

	/*
	 * The media and module pickers write at the cursor. In the textarea that is
	 * a string operation; here it is a fragment, so the shared handler is told
	 * to hand the snippet over instead.
	 */
	document.addEventListener('pluck:insert', function (event) {
		if (field.hidden === false || !event.detail) {
			return;
		}

		editor.focus();
		document.execCommand('insertHTML', false, event.detail.snippet);
		sync();
	});
})();
