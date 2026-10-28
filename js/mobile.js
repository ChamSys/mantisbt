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
	 * way as the button of the navbar does, and shows whether it is open.
	 */
	function initMenuButton() {
		var button = document.querySelector('.mobile-tabbar-menu');
		var toggler = document.getElementById('menu-toggler');
		var sidebar = document.getElementById('sidebar');

		if (!button || !toggler || !sidebar) {
			return;
		}

		function sync() {
			button.setAttribute('aria-expanded', sidebar.classList.contains('display') ? 'true' : 'false');
		}

		button.hidden = false;
		button.addEventListener('click', function () {
			toggler.click();
		});

		if (typeof MutationObserver === 'function') {
			new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
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
			select(found ? found.name : tabs[0].getAttribute('data-panel-tab'));
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

	onReady(function () {
		initMenuButton();
		initPanels();
		initFilter();
	});
})(window, document);
