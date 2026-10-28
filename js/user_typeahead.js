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
 * Choose users by searching for them as the user types, in the lists of users
 * that are too long to be a drop-down (see $g_user_typeahead_min_users).
 *
 * It enhances the <select> elements that have a data-user-typeahead attribute
 * (the scope of the search), data-user-typeahead-url and
 * data-user-typeahead-project. The <select> stays in the page, hidden, and is
 * the only thing that is submitted: users that are chosen become selected
 * options, and a change event is sent, so forms and scripts that watch the
 * <select> keep working. Its options with a value of 0 or less, such as
 * [any], [none] and [myself], are buttons: choosing one removes the users and
 * choosing a user removes them.
 */
(function (window, document) {
	'use strict';

	function translate(key, fallback) {
		var t = window.translations || [];
		return t[key] || fallback;
	}

	function element(tag, className, text) {
		var el = document.createElement(tag);
		if (className) {
			el.className = className;
		}
		if (text !== undefined) {
			el.textContent = text;
		}
		return el;
	}

	function enhance(select) {
		select.setAttribute('data-user-typeahead-ready', '1');

		var multiple = select.multiple;
		var scope = select.getAttribute('data-user-typeahead');
		var url = select.getAttribute('data-user-typeahead-url');
		var project = select.getAttribute('data-user-typeahead-project');
		var labelled = select.closest('[data-label]');
		var name = (labelled && labelled.getAttribute('data-label')) || translate('user_typeahead_search', 'Search users');

		var wrapper = element('div', 'user-typeahead');
		var magic = element('div', 'user-typeahead-magic');
		var chips = element('ul', 'user-typeahead-chips');
		var input = element('input', 'user-typeahead-input input-xs');
		input.type = 'text';
		input.setAttribute('aria-label', name);
		input.setAttribute('inputmode', 'search');
		input.setAttribute('enterkeyhint', 'search');

		wrapper.appendChild(magic);
		wrapper.appendChild(chips);
		wrapper.appendChild(input);
		select.style.display = 'none';
		select.parentNode.insertBefore(wrapper, select.nextSibling);

		function isMagic(option) {
			return parseInt(option.value, 10) <= 0;
		}

		function changed() {
			select.dispatchEvent(new Event('change', { bubbles: true }));
			refresh();
		}

		function chooseMagic(option) {
			var selected = !option.selected;
			Array.prototype.slice.call(select.options).forEach(function (other) {
				if (isMagic(other) && other !== option && (!multiple || option.value === '0' || other.value === '0')) {
					other.selected = false;
				}
				if (!isMagic(other) && selected) {
					select.removeChild(other);
				}
			});
			option.selected = selected;
			changed();
		}

		function removeUser(option) {
			select.removeChild(option);
			// Without users, the first of the other choices (usually [any]) is chosen again
			if (!Array.prototype.some.call(select.options, function (o) { return o.selected; })) {
				var first = Array.prototype.filter.call(select.options, isMagic)[0];
				if (first) {
					first.selected = true;
				}
			}
			changed();
		}

		function addUser(item) {
			var existing = Array.prototype.filter.call(select.options, function (o) { return o.value === String(item.id); })[0];
			if (!multiple) {
				Array.prototype.slice.call(select.options).forEach(function (o) {
					if (!isMagic(o)) {
						select.removeChild(o);
					}
				});
			}
			Array.prototype.forEach.call(select.options, function (o) {
				if (isMagic(o)) {
					o.selected = false;
				} else if (!multiple) {
					o.selected = false;
				}
			});
			if (!existing) {
				existing = document.createElement('option');
				existing.value = String(item.id);
				existing.textContent = item.label;
				select.appendChild(existing);
			}
			existing.selected = true;
			changed();
		}

		function refresh() {
			magic.textContent = '';
			chips.textContent = '';

			Array.prototype.forEach.call(select.options, function (option) {
				if (isMagic(option)) {
					var button = element('button', 'btn btn-xs user-typeahead-magic-button' + (option.selected ? ' active' : ''), option.textContent);
					button.type = 'button';
					button.setAttribute('aria-pressed', option.selected ? 'true' : 'false');
					button.addEventListener('click', function () {
						chooseMagic(option);
					});
					magic.appendChild(button);
				} else if (option.selected) {
					var chip = element('li', 'user-typeahead-chip');
					chip.appendChild(element('span', 'user-typeahead-chip-name', option.textContent));
					var remove = element('button', 'user-typeahead-chip-remove', '×');
					remove.type = 'button';
					remove.setAttribute('aria-label', translate('user_typeahead_remove', 'Remove') + ' ' + option.textContent);
					remove.addEventListener('click', function () {
						removeUser(option);
						input.focus();
					});
					chip.appendChild(remove);
					chips.appendChild(chip);
				}
			});
		}

		window.MantisCombobox.attach(input, {
			source: function (query, signal) {
				var request = url + (url.indexOf('?') < 0 ? '?' : '&') + 'scope=' + encodeURIComponent(scope)
					+ '&project_id=' + encodeURIComponent(project) + '&q=' + encodeURIComponent(query);

				return window.fetch(request, {
					credentials: 'same-origin',
					headers: { 'Accept': 'application/json' },
					signal: signal
				}).then(function (response) {
					if (!response.ok) {
						var error = new Error('Search failed');
						error.status = response.status;
						throw error;
					}
					return response.json();
				}).then(function (data) {
					return {
						items: data.users.map(function (user) {
							return { id: user.id, label: user.label };
						})
					};
				});
			},
			onSelect: function (item) {
				input.value = '';
				addUser(item);
			}
		});

		refresh();
	}

	function init(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var selects = scope.querySelectorAll('select[data-user-typeahead]:not([data-user-typeahead-ready])');
		for (var i = 0; i < selects.length; i++) {
			enhance(selects[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(document); });
	} else {
		init(document);
	}
	document.addEventListener('mantis:content-loaded', function (event) {
		init(event.detail && event.detail.root);
	});
})(window, document);
