/*
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Behaviour of the layout for phones, loaded when $g_mobile_layout is ON. The
 * markup is rendered by the server, with its strings translated: this script
 * only connects it. Without it the pages are still usable, one panel after the
 * other.
 */
(function (window, document) {
	'use strict';

	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
		} else {
			callback();
		}
	}

	/**
	 * The Menu button of the bottom bar opens and closes the sidebar, the same
	 * way as the button of the navbar does, and shows whether it is open. A theme
	 * that draws its own sidebar marks the sidebar with data-sidebar and its button
	 * with data-sidebar-toggle: the button keeps aria-expanded up to date, or the
	 * sidebar gets the class display when it is open.
	 */
	function initMenuButton() {
		var button = document.querySelector('.mobile-tabbar-menu');
		var toggler = document.getElementById('menu-toggler') || document.querySelector('[data-sidebar-toggle]');
		var sidebar = document.getElementById('sidebar') || document.querySelector('[data-sidebar]');

		if (!button || !toggler || !sidebar) {
			return;
		}

		function sync() {
			var open = sidebar.classList.contains('display') || toggler.getAttribute('aria-expanded') === 'true';
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
		}

		if (sidebar.id) {
			button.setAttribute('aria-controls', sidebar.id);
		}
		button.hidden = false;
		button.addEventListener('click', function () {
			toggler.click();
		});

		if (typeof MutationObserver === 'function') {
			var observer = new MutationObserver(sync);
			observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
			observer.observe(toggler, { attributes: true, attributeFilter: ['aria-expanded'] });
		}
		sync();
	}

	/**
	 * The issue page shows one of its panels (details, notes, history) at a
	 * time, chosen with tabs. A link to something inside a panel that is not
	 * shown (#c123, #history, #attachments) shows that panel first.
	 */
	function initPanels() {
		var tablist = document.querySelector('.mobile-panel-tabs');
		var query = typeof window.matchMedia === 'function' ? window.matchMedia('(max-width: 767px)') : null;

		if (!tablist || !query) {
			return;
		}

		var tabs = [];
		var panels = {};
		var current = null;

		// What an address with a hash is for each panel, when it is chosen with a tab
		var hashes = { details: '', notes: '#bugnotes', history: '#history' };

		[].slice.call(tablist.querySelectorAll('[data-panel-tab]')).forEach(function (tab) {
			var name = tab.getAttribute('data-panel-tab');
			var elements = [].slice.call(document.querySelectorAll('[data-panel="' + name + '"]'));

			// There is no history panel for those who cannot see it
			if (elements.length === 0) {
				tab.parentNode.removeChild(tab);
				return;
			}

			elements.forEach(function (element, index) {
				if (!element.id) {
					element.id = 'panel-' + name + '-' + index;
				}
			});
			tab.setAttribute('aria-controls', elements.map(function (element) {
				return element.id;
			}).join(' '));

			panels[name] = elements;
			tabs.push(tab);
		});

		if (tabs.length < 2) {
			return;
		}

		function select(name) {
			current = name;

			tabs.forEach(function (tab) {
				var selected = tab.getAttribute('data-panel-tab') === name;
				tab.setAttribute('aria-selected', selected ? 'true' : 'false');
				tab.tabIndex = selected ? 0 : -1;
			});

			Object.keys(panels).forEach(function (panel) {
				panels[panel].forEach(function (element) {
					element.hidden = panel !== name;
				});
			});
		}

		// After a change of tab the top of the panel must not be under the tabs
		function revealTop() {
			var limit = tablist.getBoundingClientRect().bottom;
			var top = panels[current][0].getBoundingClientRect().top;

			if (top < limit) {
				window.scrollBy(0, top - limit - 4);
			}
		}

		function choose(name) {
			select(name);
			revealTop();

			if (window.history && typeof window.history.replaceState === 'function') {
				window.history.replaceState(null, '', window.location.pathname + window.location.search + hashes[name]);
			}
		}

		// The panel and the element that the hash of the address is about
		function fromHash() {
			var id = '';
			try {
				id = decodeURIComponent(window.location.hash.slice(1));
			} catch (error) {
				return null;
			}

			var target = id ? document.getElementById(id) : null;
			var panel = target ? target.closest('[data-panel]') : null;

			return panel && panels[panel.getAttribute('data-panel')] ? { name: panel.getAttribute('data-panel'), target: target } : null;
		}

		function followHash() {
			var found = fromHash();

			if (found) {
				select(found.name);
				// scroll-margin-top in css/mobile.css keeps it clear of the navbar and the tabs
				found.target.scrollIntoView();
			}
		}

		function onKeydown(event) {
			var index = tabs.indexOf(event.target);
			var rtl = window.getComputedStyle(tablist).direction === 'rtl';
			var next = -1;

			if (index < 0 || event.altKey || event.ctrlKey || event.metaKey) {
				return;
			}

			switch (event.key) {
				case 'ArrowRight':
					next = rtl ? index - 1 : index + 1;
					break;
				case 'ArrowLeft':
					next = rtl ? index + 1 : index - 1;
					break;
				case 'Home':
					next = 0;
					break;
				case 'End':
					next = tabs.length - 1;
					break;
				default:
					return;
			}

			event.preventDefault();
			next = (next + tabs.length) % tabs.length;
			tabs[next].focus();
			choose(tabs[next].getAttribute('data-panel-tab'));
		}

		// The panel the user was on before the page was left to save something in it, see initSheets()
		function rememberedPanel() {
			var name = null;

			try {
				var saved = JSON.parse(window.sessionStorage.getItem('mantis:mobile-panel'));
				window.sessionStorage.removeItem('mantis:mobile-panel');
				if (saved && saved.url === window.location.pathname + window.location.search && panels[saved.panel]) {
					name = saved.panel;
				}
			} catch (error) {
				// No storage, or nothing saved: the first panel
			}

			return name;
		}

		function enable() {
			tablist.hidden = false;

			tabs.forEach(function (tab) {
				var name = tab.getAttribute('data-panel-tab');
				panels[name].forEach(function (element) {
					element.setAttribute('role', 'tabpanel');
					element.setAttribute('aria-labelledby', tab.id);
				});
			});

			var found = fromHash();
			select(found ? found.name : (rememberedPanel() || tabs[0].getAttribute('data-panel-tab')));
			if (found) {
				followHash();
			}
		}

		function disable() {
			tablist.hidden = true;

			Object.keys(panels).forEach(function (name) {
				panels[name].forEach(function (element) {
					element.hidden = false;
					element.removeAttribute('role');
					element.removeAttribute('aria-labelledby');
				});
			});
		}

		function update() {
			if (query.matches) {
				enable();
			} else {
				disable();
			}
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				choose(tab.getAttribute('data-panel-tab'));
			});
		});
		tablist.addEventListener('keydown', onKeydown);
		window.addEventListener('hashchange', function () {
			if (query.matches) {
				followHash();
			}
		});

		if (typeof query.addEventListener === 'function') {
			query.addEventListener('change', update);
		} else if (typeof query.addListener === 'function') {
			query.addListener(update);
		}

		update();
	}

	/**
	 * On a phone the filter, which is as long as the screen several times over, starts
	 * collapsed when there are issues to look at, so that the list is what the page opens
	 * on. It is collapsed the way the page restores the state it saved, which is not saved
	 * itself: what the user chooses on a larger screen is not changed by this.
	 */
	function collapseOnPhone(box, query) {
		var widget = document.getElementById('filter');
		var toggle = document.getElementById('filter-toggle');

		if (!query.matches || !widget || !toggle || widget.classList.contains('collapsed') ||
			!document.querySelector('#buglist tbody tr[data-bug-id]')) {
			return;
		}

		widget.classList.add('collapsed');
		toggle.setAttribute('aria-expanded', 'false');

		var icon = toggle.querySelector('.ace-icon');
		if (icon) {
			icon.classList.remove('fa-chevron-up');
			icon.classList.add('fa-chevron-down');
		}
	}

	/**
	 * The filter is a list of fields, each with its value. A tap on a value opens the field for
	 * editing, as the link of its label does (the labels are not displayed as such on a phone, the
	 * name of the field is shown above the value instead).
	 */
	function initFilter() {
		var box = document.querySelector('.filter-box');
		var query = typeof window.matchMedia === 'function' ? window.matchMedia('(max-width: 767px)') : null;

		if (!box || !query) {
			return;
		}

		collapseOnPhone(box, query);

		box.addEventListener('click', function (event) {
			var cell = event.target.closest ? event.target.closest('td[data-field]') : null;

			if (!query.matches || !cell || !box.contains(cell)) {
				return;
			}

			// A control, or a field that is already open, is used as it is
			if (event.target.closest('a, button, label, select, input, textarea') ||
				cell.querySelector('select, textarea, input:not([type="hidden"])')) {
				return;
			}

			var expander = document.getElementById(cell.getAttribute('data-field') + '_filter');
			if (expander) {
				expander.click();
			}
		});
	}

	/**
	 * The form to add a note and the buttons of an issue are in sheets that open from the bottom
	 * of the screen, from a button: the page stays as it is, and the form or the buttons are
	 * the ones of the page, drawn as a sheet (they are not copied or moved). While a sheet is
	 * open it is a modal dialog: the focus goes in and stays in, Escape and a tap on what is
	 * behind close it, and the focus goes back to the button that opened it.
	 */
	function initSheets() {
		var query = typeof window.matchMedia === 'function' ? window.matchMedia('(max-width: 767px)') : null;
		var sheets = [];
		var scrim = null;
		var active = null;

		[].slice.call(document.querySelectorAll('[data-sheet-open]')).forEach(function (opener) {
			var element = document.getElementById(opener.getAttribute('aria-controls'));
			if (element && element.hasAttribute('data-sheet')) {
				sheets.push({ opener: opener, element: element });
			}
		});

		if (sheets.length === 0 || !query) {
			return;
		}

		var controls = 'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])';

		function visible(element) {
			return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
		}

		function focusables(sheet) {
			return [].slice.call(sheet.element.querySelectorAll(controls)).filter(visible);
		}

		function close(returnFocus) {
			if (!active) {
				return;
			}

			var sheet = active;
			active = null;

			sheet.element.classList.remove('is-open');
			sheet.opener.setAttribute('aria-expanded', 'false');
			scrim.hidden = true;
			document.body.classList.remove('mobile-sheet-open');

			if (returnFocus && visible(sheet.opener)) {
				sheet.opener.focus();
			}
		}

		function open(sheet) {
			if (active) {
				close(false);
			}

			active = sheet;
			sheet.element.classList.add('is-open');
			sheet.opener.setAttribute('aria-expanded', 'true');
			scrim.hidden = false;
			document.body.classList.add('mobile-sheet-open');

			// A text area is what a sheet with a form is opened for, otherwise the first control
			var first = sheet.element.querySelector('textarea') ||
				focusables(sheet).filter(function (item) {
					return !item.classList.contains('mobile-sheet-close');
				})[0] || focusables(sheet)[0];
			if (first) {
				first.focus();
			}
		}

		function enable() {
			sheets.forEach(function (sheet) {
				// Buttons are not shown to those who cannot do anything with the sheet
				if (!sheet.element.querySelector(controls.replace('button:not([disabled])', 'button:not([disabled]):not(.mobile-sheet-close)'))) {
					return;
				}

				var title = sheet.element.querySelector('.mobile-sheet-title, .widget-title');
				sheet.element.classList.add('is-sheet');
				sheet.element.setAttribute('role', 'dialog');
				sheet.element.setAttribute('aria-modal', 'true');
				if (title && title.id) {
					sheet.element.setAttribute('aria-labelledby', title.id);
				}

				// The table row that holds the sheet has nothing else in it
				var row = sheet.element.closest('tr');
				if (row) {
					row.classList.add('mobile-sheet-host');
				}

				sheet.opener.hidden = false;
				sheet.enabled = true;
			});

			if (window.location.hash === '#addbugnote') {
				var wanted = document.getElementById('bugnote_add');
				sheets.forEach(function (sheet) {
					if (sheet.enabled && sheet.element === wanted) {
						open(sheet);
					}
				});
			}
		}

		function disable() {
			close(false);

			sheets.forEach(function (sheet) {
				sheet.enabled = false;
				sheet.opener.hidden = true;
				sheet.element.classList.remove('is-sheet');
				sheet.element.removeAttribute('role');
				sheet.element.removeAttribute('aria-modal');
				sheet.element.removeAttribute('aria-labelledby');

				var row = sheet.element.closest('tr');
				if (row) {
					row.classList.remove('mobile-sheet-host');
				}
			});
		}

		function update() {
			if (query.matches) {
				enable();
			} else {
				disable();
			}
		}

		scrim = document.createElement('div');
		scrim.className = 'mobile-sheet-scrim';
		scrim.hidden = true;
		document.body.appendChild(scrim);
		scrim.addEventListener('click', function () {
			close(true);
		});

		// After a note is sent the page loads again: it opens on the notes, where the new note is
		var noteForm = document.getElementById('bugnoteadd');
		if (noteForm) {
			noteForm.addEventListener('submit', function () {
				try {
					window.sessionStorage.setItem('mantis:mobile-panel',
						JSON.stringify({ url: window.location.pathname + window.location.search, panel: 'notes' }));
				} catch (error) {
					// No storage: the page opens on the first panel
				}
			});
		}

		sheets.forEach(function (sheet) {
			sheet.opener.addEventListener('click', function () {
				open(sheet);
			});

			[].slice.call(sheet.element.querySelectorAll('.mobile-sheet-close')).forEach(function (button) {
				button.addEventListener('click', function () {
					close(true);
				});
			});
		});

		document.addEventListener('keydown', function (event) {
			if (!active) {
				return;
			}

			if (event.key === 'Escape') {
				event.preventDefault();
				close(true);
				return;
			}

			if (event.key !== 'Tab') {
				return;
			}

			// Tab goes round inside the sheet
			var items = focusables(active);
			if (items.length === 0) {
				event.preventDefault();
				return;
			}

			var first = items[0];
			var last = items[items.length - 1];
			if (!active.element.contains(document.activeElement)) {
				event.preventDefault();
				first.focus();
			} else if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});

		window.addEventListener('hashchange', function () {
			if (query.matches && window.location.hash === '#addbugnote') {
				var wanted = document.getElementById('bugnote_add');
				sheets.forEach(function (sheet) {
					if (sheet.enabled && sheet.element === wanted) {
						open(sheet);
					}
				});
			}
		});

		if (typeof query.addEventListener === 'function') {
			query.addEventListener('change', update);
		} else if (typeof query.addListener === 'function') {
			query.addListener(update);
		}

		update();
	}

	onReady(function () {
		initMenuButton();
		initPanels();
		initFilter();
		initSheets();
	});
})(window, document);
