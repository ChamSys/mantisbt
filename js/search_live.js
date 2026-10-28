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
 * Suggest issues as the user types in a search box (see $g_search_live_enabled).
 *
 * It enhances the inputs that have a data-quick-search attribute, whose value is
 * the URL of the search. The input is part of a form that still works without
 * this script: Enter without choosing a suggestion submits it.
 */
(function (window, document) {
	'use strict';

	// The translations come from a script the browser may have cached from before they existed
	function translate(key, fallback) {
		var t = window.translations || [];
		return t[key] || fallback;
	}

	function strings() {
		return {
			results: translate('search_live_results', 'results available, use the up and down arrows to review'),
			noMatches: translate('search_live_no_matches', 'No matches'),
			failed: translate('search_live_failed', 'Search failed'),
			tooMany: translate('search_live_too_many', 'Too many searches, wait a moment'),
			typeMore: translate('search_live_type_more', 'Type more characters to search')
		};
	}

	function attach(input) {
		var url = input.getAttribute('data-quick-search');

		input.setAttribute('data-quick-search-ready', '1');

		window.MantisCombobox.attach(input, {
			strings: strings(),
			source: function (query, signal) {
				return window.fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + 'q=' + encodeURIComponent(query), {
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
						items: data.results.map(function (issue) {
							return {
								id: issue.id,
								label: issue.id_formatted + ' ' + issue.summary,
								detail: issue.status.label + ' · ' + issue.project.name,
								href: 'view.php?id=' + issue.id
							};
						}),
						footer: {
							label: translate('search_live_all_results', 'All results for') + ' “' + query + '”',
							href: 'search.php?search=' + encodeURIComponent(query)
						}
					};
				});
			}
		});
	}

	function init() {
		var inputs = document.querySelectorAll('input[data-quick-search]:not([data-quick-search-ready])');
		for (var i = 0; i < inputs.length; i++) {
			attach(inputs[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
	document.addEventListener('mantis:content-loaded', init);
})(window, document);
