/*
 * Everything here is an improvement on something that already works.
 *
 * The admin is server-rendered forms; this file makes them nicer to use. If it
 * fails to load, or a browser refuses to run it, every screen still functions —
 * which is the reason there is no framework in it and no build step to produce
 * it. Destructive actions confirm here as a courtesy; the real guard is the
 * permission check and the CSRF token on the server.
 */
(function () {
	'use strict';

	/* Confirm before a delete. */
	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!(form instanceof HTMLFormElement)) return;

		var question = form.getAttribute('data-confirm');
		if (question && !window.confirm(question)) {
			event.preventDefault();
			return;
		}

		/* A submitted form is no longer unsaved. */
		var guarded = form.closest('[data-guard-unsaved]') || (form.hasAttribute('data-guard-unsaved') ? form : null);
		if (guarded) guarded.dataset.dirty = '';
	});

	/*
	 * Suggest an address from the title, but only while the field is untouched
	 * and only for a new page. Silently rewriting the address of a published page
	 * would break every link to it.
	 */
	var source = document.querySelector('[data-slug-source]');
	var target = document.querySelector('[data-slug-target]');
	if (source && target && target.value === '') {
		var edited = false;
		target.addEventListener('input', function () { edited = true; });
		source.addEventListener('input', function () {
			if (edited) return;
			target.value = source.value
				.toLowerCase()
				.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
				.replace(/[^a-z0-9]+/g, '-')
				.replace(/^-+|-+$/g, '')
				.slice(0, 120);
		});
	}

	/* Warn before leaving an edited form. */
	document.querySelectorAll('[data-guard-unsaved]').forEach(function (form) {
		form.addEventListener('input', function () { form.dataset.dirty = '1'; });
	});
	window.addEventListener('beforeunload', function (event) {
		var dirty = document.querySelector('[data-guard-unsaved][data-dirty="1"]');
		if (dirty) event.preventDefault();
	});

	/* The formatting toolbar: wrap the selection, or insert an empty pair. */
	document.querySelectorAll('[data-toolbar-for]').forEach(function (toolbar) {
		var field = document.getElementById(toolbar.getAttribute('data-toolbar-for'));
		if (!field) return;

		toolbar.addEventListener('click', function (event) {
			var button = event.target.closest('button');
			if (!button) return;

			/*
			 * When the visible editor is showing, it owns the toolbar.
			 *
			 * preventDefault does not stop another listener on the same element,
			 * so both handlers ran and a prompt() appeared in front of the
			 * dialogue. Whoever is showing does the work; the other stands aside.
			 */
			if (field.hidden) {
				return;
			}

			if (button.hasAttribute('data-link')) {
				/* Wording comes from the template, which has the translator. The
				   fallbacks only matter if this script is used on a page that
				   forgot the attributes. */
				var askFor = toolbar.getAttribute('data-link-prompt') || 'Link to which address?';
				var refused = toolbar.getAttribute('data-link-refused')
					|| 'Use an http:// or https:// address, a mailto: address, or a page on this site.';

				var href = window.prompt(askFor, 'https://');
				if (!href) return;
				/* Only http(s), mailto and site-relative addresses: javascript: in an
				   href is exactly the payload the sanitiser exists to remove, and
				   there is no reason to help someone type it. */
				if (!/^(https?:\/\/|mailto:|\/|[a-z0-9._~-]+(\/|$))/i.test(href)) {
					window.alert(refused);
					return;
				}
				surround('<a href="' + href.replace(/"/g, '&quot;') + '">', '</a>');
				return;
			}

			var tag = button.getAttribute('data-wrap');
			if (!tag) return;

			if (tag === 'ul>li') {
				surround('<ul>\n\t<li>', '</li>\n</ul>');
			} else {
				surround('<' + tag + '>', '</' + tag + '>');
			}
		});

		function surround(before, after) {
			var start = field.selectionStart;
			var end = field.selectionEnd;
			var selected = field.value.slice(start, end);

			field.setRangeText(before + selected + after, start, end, 'end');
			if (selected === '') {
				var caret = start + before.length;
				field.setSelectionRange(caret, caret);
			}
			field.focus();
			field.dispatchEvent(new Event('input', { bubbles: true }));
		}
	});
})();

/*
 * Insert a media reference at the cursor.
 *
 * Pluck 4 did this through a menu added to TinyMCE, which worked well and cost a
 * dependency — and when a high-severity CVE landed in it, a forced major upgrade.
 * A plain textarea needs none of that: selectionStart and selectionEnd are all
 * the API required, and the editor stays a textarea that works with JavaScript
 * switched off.
 */
document.addEventListener('click', function (event) {
	var trigger = event.target.closest('[data-insert-target]');
	if (!trigger) {
		return;
	}

	event.preventDefault();

	var field = document.getElementById(trigger.getAttribute('data-insert-target'));
	var picker = document.getElementById(trigger.getAttribute('data-insert-source'));
	if (!field || !picker || !picker.value) {
		return;
	}

	var name = picker.value;
	var snippet;

	if (trigger.hasAttribute('data-insert-raw')) {
		// A module marker is already exactly what belongs in the text.
		snippet = name;
	} else {
		var isImage = /\.(jpe?g|png|gif|webp|avif|svg)$/i.test(name);
		snippet = isImage
			? '<img src="media/' + encodeURIComponent(name) + '" alt="">'
			: '<a href="media/' + encodeURIComponent(name) + '">' + name + '</a>';
	}

	// A visible editor may be showing instead of the textarea. It listens for
	// this and inserts at its own cursor; when it is not there, the textarea does
	// the work exactly as before.
	document.dispatchEvent(new CustomEvent('pluck:insert', { detail: { snippet: snippet } }));

	if (field.hidden) {
		return;
	}

	var start = field.selectionStart;
	var end = field.selectionEnd;

	field.value = field.value.slice(0, start) + snippet + field.value.slice(end);

	// Leave the cursor after what was inserted, so typing continues where the
	// person was rather than at the top of the field.
	field.focus();
	field.selectionStart = field.selectionEnd = start + snippet.length;
	field.dispatchEvent(new Event('input', { bubbles: true }));
});

/*
 * Live preview, showing what the sanitiser will leave behind.
 *
 * Rendered by the server rather than in the browser on purpose. Dropping the raw
 * HTML into a div would show what the author typed, and what the author typed is
 * not what gets saved — the sanitiser runs on save, and the gap between the two
 * is the whole reason this exists.
 *
 * The result goes into a sandboxed iframe. It is sanitised markup, so the risk is
 * small, but "small" is not a reason to give page content the run of the admin
 * page: sandbox="" means no scripts, no forms, no navigation, no access to this
 * document.
 */
(function () {
	var field = document.getElementById('content');
	var panel = document.getElementById('preview');
	if (!field || !panel) return;

	var frame = panel.querySelector('iframe');
	var note = panel.querySelector('[data-preview-note]');
	var form = field.closest('form');
	var token = form ? form.querySelector('input[name="_token"]') : null;
	if (!frame || !token) return;

	var timer = null;
	var last = null;
	var inFlight = false;

	function render() {
		var titleField = form.querySelector('[name="title"]');
		var signature = field.value + '\u0000' + (titleField ? titleField.value : '');

		if (inFlight || signature === last) return;

		last = signature;
		inFlight = true;

		var body = new FormData();
		body.append('content', field.value);
		body.append('_token', token.value);

		/* The title and the address as well, because the preview now renders the
		   whole page: the theme puts the title in the heading and the menu marks
		   which page you are on. */
		var title = form.querySelector('[name="title"]');
		var path = form.querySelector('[name="path"]');
		body.append('title', title ? title.value : '');
		body.append('path', path ? path.value : '');

		fetch(panel.getAttribute('data-preview-url'), {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json' }
		}).then(function (response) {
			if (!response.ok) throw new Error('preview failed');
			return response.json();
		}).then(function (data) {
			/* srcdoc, not document.write: the iframe is sandboxed into its own
			   origin and this is the only way to put content in it. */
			/* The whole page, rendered by the site's own renderer with the site's
			   own theme — menu, header, footer and all. sandbox="" means nothing in
			   it runs and no link in it goes anywhere, which is exactly what a
			   preview should be: the page, and not a way to leave it. */
			frame.srcdoc = data.document || '';

			if (note) {
				note.textContent = data.removed
					? (note.getAttribute('data-removed-prefix') || 'Removed on save:') + ' ' + data.removed
					: '';
				note.hidden = !data.removed;
			}
		}).catch(function () {
			/* A failed preview is a preview, not an error worth interrupting
			   somebody's writing for. The next keystroke tries again. */
			last = null;
		}).then(function () {
			inFlight = false;
		});
	}

	var titleInput = form.querySelector('[name="title"]');
	if (titleInput) {
		titleInput.addEventListener('input', function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(render, 600);
		});
	}

	field.addEventListener('input', function () {
		window.clearTimeout(timer);
		/* Long enough that typing a sentence is one request rather than forty. */
		timer = window.setTimeout(render, 600);
	});

	render();
})();

/*
 * Keyboard shortcuts for the editor toolbar.
 *
 * The same three every editor has had for thirty years. Anything more inventive
 * is a shortcut people trigger by accident.
 */
document.addEventListener('keydown', function (event) {
	if (!event.ctrlKey && !event.metaKey) return;

	var field = event.target;
	if (!field || field.id !== 'content') return;

	var tag = { b: 'strong', i: 'em', k: 'link' }[event.key.toLowerCase()];
	if (!tag) return;

	var toolbar = document.querySelector('[data-toolbar-for="content"]');
	var button = toolbar && toolbar.querySelector(
		tag === 'link' ? '[data-link]' : '[data-wrap="' + tag + '"]'
	);
	if (!button) return;

	event.preventDefault();
	button.click();
});

/*
 * Dark, light, or whatever the machine says.
 *
 * The stylesheet does the work through prefers-color-scheme; this only overrides
 * it with a class on <html>, so with JavaScript off the system setting is
 * followed and nothing is broken.
 *
 * Read and applied as early as the script runs rather than on DOMContentLoaded,
 * because applying it later means a visible flash of the other theme.
 */
(function () {
	var KEY = 'pluck-appearance';
	var root = document.documentElement;

	function apply(value) {
		root.classList.remove('is-light', 'is-dark');

		if (value === 'light' || value === 'dark') {
			root.classList.add('is-' + value);
		}

		var label = document.querySelector('[data-appearance-label]');
		if (label) {
			label.textContent = label.getAttribute('data-' + (value || 'system'))
				|| label.textContent;
		}
	}

	try {
		apply(window.localStorage.getItem(KEY) || 'system');
	} catch (e) { /* private mode, or storage refused: the system setting stands */ }

	var ORDER = ['system', 'light', 'dark'];

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-appearance-cycle]');
		if (!button) {
			return;
		}

		var current = 'system';
		try {
			current = window.localStorage.getItem(KEY) || 'system';
		} catch (e) { /* nothing stored is the same as system */ }

		var value = ORDER[(ORDER.indexOf(current) + 1) % ORDER.length];
		apply(value);

		try {
			window.localStorage.setItem(KEY, value);
		} catch (e) { /* the choice holds for this page either way */ }
	});
})();
