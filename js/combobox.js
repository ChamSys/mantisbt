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
 * An editable combobox with a list of suggestions (WAI-ARIA 1.2, "list
 * autocomplete"), used for searching as the user types.
 *
 *   MantisCombobox.attach( input, {
 *       source: function( query, signal ) { return Promise of { items: [...], footer: {...} } },
 *       onSelect: function( item ) { ... }
 *   } );
 *
 * An item is { id, label, detail, href }: label and detail are shown as plain
 * text. The footer is an item that is always last, like "All results". The list
 * is added to the body, so no container can clip it, and positioned under the
 * input.
 *
 * Keyboard: Down and Up move in the list (opening it), Home and End go to the
 * first and last item, Enter chooses the active item (or submits the form, if
 * none is active), Escape closes the list, Tab closes it and moves on.
 */
(function (window, document) {
	'use strict';

	var counter = 0;

	var defaults = {
		minChars: 2,
		debounce: 250,
		cacheSize: 20,
		strings: {
			results: 'results available, use the up and down arrows to review',
			noMatches: 'No matches',
			failed: 'Search failed',
			tooMany: 'Too many searches, wait a moment',
			typeMore: 'Type more characters to search'
		}
	};

	function extend(target, source) {
		Object.keys(source || {}).forEach(function (key) {
			target[key] = source[key];
		});
		return target;
	}

	function Combobox(input, options) {
		this.input = input;
		this.options = extend(extend({}, defaults), options);
		this.options.strings = extend(extend({}, defaults.strings), (options || {}).strings);
		this.id = 'mantis-combobox-' + (++counter);
		this.items = [];
		this.active = -1;
		this.requestId = 0;
		this.controller = null;
		this.timer = null;
		this.cache = [];
		this.lastQuery = null;
		this.build();
		this.bind();
	}

	Combobox.prototype.build = function () {
		var input = this.input;

		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('aria-haspopup', 'listbox');
		input.setAttribute('aria-controls', this.id + '-list');
		input.setAttribute('autocomplete', 'off');

		this.list = document.createElement('ul');
		this.list.id = this.id + '-list';
		this.list.className = 'mantis-combobox-list';
		this.list.setAttribute('role', 'listbox');
		this.list.hidden = true;
		document.body.appendChild(this.list);

		this.status = document.createElement('div');
		this.status.className = 'mantis-combobox-status sr-only';
		this.status.setAttribute('aria-live', 'polite');
		this.status.setAttribute('role', 'status');
		document.body.appendChild(this.status);
	};

	Combobox.prototype.bind = function () {
		var self = this;

		this.input.addEventListener('input', function () {
			self.schedule();
		});
		this.input.addEventListener('keydown', function (event) {
			self.keydown(event);
		});
		this.input.addEventListener('focusout', function (event) {
			if (!self.list.contains(event.relatedTarget)) {
				self.close();
			}
		});

		// The input keeps the focus while the pointer is used on the list
		this.list.addEventListener('mousedown', function (event) {
			event.preventDefault();
		});
		this.list.addEventListener('click', function (event) {
			var option = event.target.closest('[role=option]');
			if (option && self.list.contains(option)) {
				self.choose(parseInt(option.getAttribute('data-index'), 10));
			}
		});
		this.list.addEventListener('mousemove', function (event) {
			var option = event.target.closest('[role=option]');
			if (option) {
				self.setActive(parseInt(option.getAttribute('data-index'), 10), true);
			}
		});

		window.addEventListener('resize', function () {
			self.position();
		});
		window.addEventListener('scroll', function () {
			self.position();
		}, true);
	};

	Combobox.prototype.keydown = function (event) {
		var count = this.items.length;
		var open = !this.list.hidden;

		switch (event.key) {
			case 'ArrowDown':
				if (count > 0) {
					event.preventDefault();
					this.open();
					this.setActive(this.active + 1 >= count ? 0 : this.active + 1);
				}
				break;
			case 'ArrowUp':
				if (count > 0) {
					event.preventDefault();
					this.open();
					this.setActive(this.active <= 0 ? count - 1 : this.active - 1);
				}
				break;
			case 'Home':
				if (open && this.active >= 0) {
					event.preventDefault();
					this.setActive(0);
				}
				break;
			case 'End':
				if (open && this.active >= 0) {
					event.preventDefault();
					this.setActive(count - 1);
				}
				break;
			case 'Enter':
				// Only an item that was moved to is chosen, otherwise the form is submitted as usual
				if (open && this.active >= 0) {
					event.preventDefault();
					this.choose(this.active);
				}
				break;
			case 'Escape':
				if (open) {
					event.preventDefault();
					this.close();
				}
				break;
			case 'Tab':
				this.close();
				break;
		}
	};

	Combobox.prototype.schedule = function () {
		var self = this;
		var query = this.input.value.trim();

		window.clearTimeout(this.timer);
		this.cancel();

		if (query.length < this.options.minChars) {
			this.items = [];
			this.close();
			this.announce(query.length > 0 ? this.options.strings.typeMore : '');
			return;
		}

		this.timer = window.setTimeout(function () {
			self.search(query);
		}, this.options.debounce);
	};

	Combobox.prototype.cancel = function () {
		if (this.controller) {
			this.controller.abort();
			this.controller = null;
		}
		// Answers of requests that were started before are ignored
		this.requestId++;
	};

	Combobox.prototype.search = function (query) {
		var self = this;
		var cached = this.cacheGet(query);

		if (cached) {
			this.show(query, cached);
			return;
		}

		var requestId = ++this.requestId;
		this.controller = typeof AbortController === 'function' ? new AbortController() : null;

		Promise.resolve(this.options.source(query, this.controller ? this.controller.signal : null))
			.then(function (result) {
				if (requestId !== self.requestId) {
					return;
				}
				self.cachePut(query, result);
				self.show(query, result);
			})
			.catch(function (error) {
				if (requestId !== self.requestId || (error && error.name === 'AbortError')) {
					return;
				}
				// The next input searches again: a failed query is not kept
				self.lastQuery = null;
				self.items = [];
				self.showMessage(error && error.status === 429 ? self.options.strings.tooMany : self.options.strings.failed);
			});
	};

	Combobox.prototype.cacheGet = function (query) {
		for (var i = 0; i < this.cache.length; i++) {
			if (this.cache[i].query === query) {
				return this.cache[i].result;
			}
		}
		return null;
	};

	Combobox.prototype.cachePut = function (query, result) {
		this.cache.push({ query: query, result: result });
		if (this.cache.length > this.options.cacheSize) {
			this.cache.shift();
		}
	};

	Combobox.prototype.show = function (query, result) {
		var items = (result.items || []).slice();
		if (result.footer) {
			items.push(extend({ footer: true }, result.footer));
		}

		this.lastQuery = query;
		this.items = items;
		this.active = -1;
		this.render();

		if ((result.items || []).length === 0) {
			this.announce(this.options.strings.noMatches);
		} else {
			this.announce(result.items.length + ' ' + this.options.strings.results);
		}

		if (items.length > 0) {
			this.open();
		} else {
			this.close();
		}
	};

	Combobox.prototype.showMessage = function (message) {
		this.list.textContent = '';
		var li = document.createElement('li');
		li.className = 'mantis-combobox-message';
		li.setAttribute('role', 'presentation');
		li.textContent = message;
		this.list.appendChild(li);
		this.announce(message);
		this.open();
	};

	Combobox.prototype.render = function () {
		this.list.textContent = '';

		for (var i = 0; i < this.items.length; i++) {
			var item = this.items[i];
			var li = document.createElement('li');
			li.id = this.id + '-option-' + i;
			li.className = 'mantis-combobox-option' + (item.footer ? ' mantis-combobox-footer' : '');
			li.setAttribute('role', 'option');
			li.setAttribute('aria-selected', 'false');
			li.setAttribute('data-index', String(i));

			var label = document.createElement('span');
			label.className = 'mantis-combobox-label';
			label.textContent = item.label;
			li.appendChild(label);

			if (item.detail) {
				var detail = document.createElement('span');
				detail.className = 'mantis-combobox-detail';
				detail.textContent = item.detail;
				li.appendChild(detail);
			}

			this.list.appendChild(li);
		}
	};

	Combobox.prototype.setActive = function (index, fromPointer) {
		var options = this.list.querySelectorAll('[role=option]');

		if (this.active >= 0 && options[this.active]) {
			options[this.active].setAttribute('aria-selected', 'false');
			options[this.active].classList.remove('active');
		}

		this.active = index;

		if (index >= 0 && options[index]) {
			options[index].setAttribute('aria-selected', 'true');
			options[index].classList.add('active');
			this.input.setAttribute('aria-activedescendant', options[index].id);
			if (!fromPointer) {
				options[index].scrollIntoView({ block: 'nearest' });
			}
		} else {
			this.input.removeAttribute('aria-activedescendant');
		}
	};

	Combobox.prototype.choose = function (index) {
		var item = this.items[index];
		if (!item) {
			return;
		}

		this.close();

		if (typeof this.options.onSelect === 'function') {
			this.options.onSelect(item);
		} else if (item.href) {
			window.location.href = item.href;
		}
	};

	Combobox.prototype.open = function () {
		this.list.hidden = false;
		this.input.setAttribute('aria-expanded', 'true');
		this.position();
	};

	Combobox.prototype.close = function () {
		this.list.hidden = true;
		this.input.setAttribute('aria-expanded', 'false');
		this.input.removeAttribute('aria-activedescendant');
		this.active = -1;
	};

	Combobox.prototype.position = function () {
		if (this.list.hidden) {
			return;
		}

		var rect = this.input.getBoundingClientRect();
		var width = Math.max(rect.width, 280);

		this.list.style.top = rect.bottom + 'px';
		this.list.style.left = Math.max(0, Math.min(rect.left, window.innerWidth - width)) + 'px';
		this.list.style.minWidth = width + 'px';
		this.list.style.maxWidth = (window.innerWidth - 8) + 'px';
	};

	Combobox.prototype.announce = function (message) {
		this.status.textContent = message;
	};

	window.MantisCombobox = {
		attach: function (input, options) {
			return new Combobox(input, options);
		}
	};
})(window, document);
