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
 * Suggest issues like the one being reported, as its summary is typed (see
 * $g_report_similar_issues). The summary input has a data-similar-issues
 * attribute with the URL of the search, and the suggestions go in the element
 * with the id given by its aria-describedby.
 */
(function (window, document) {
	'use strict';

	var MIN_CHARS = 3;
	var DELAY = 500;

	function init() {
		var input = document.querySelector('input[data-similar-issues]');
		if (!input) {
			return;
		}

		var box = document.getElementById(input.getAttribute('aria-describedby'));
		var list = box.querySelector('ul');
		var projectField = document.querySelector('input[name=project_id]');
		var timer = null;
		var controller = null;
		var last = '';

		function hide() {
			box.hidden = true;
			list.textContent = '';
		}

		function show(issues) {
			list.textContent = '';
			issues.forEach(function (issue) {
				var li = document.createElement('li');
				var link = document.createElement('a');
				link.href = 'view.php?id=' + issue.id;
				link.target = '_blank';
				link.rel = 'noopener';
				link.textContent = issue.id_formatted + ' ' + issue.summary;
				li.appendChild(link);
				list.appendChild(li);
			});
			box.hidden = issues.length === 0;
		}

		function search(query) {
			var url = input.getAttribute('data-similar-issues') + '?limit=5&q=' + encodeURIComponent(query);
			if (projectField) {
				url += '&project_id=' + encodeURIComponent(projectField.value);
			}

			if (controller) {
				controller.abort();
			}
			controller = typeof AbortController === 'function' ? new AbortController() : null;

			window.fetch(url, {
				credentials: 'same-origin',
				headers: { 'Accept': 'application/json' },
				signal: controller ? controller.signal : undefined
			}).then(function (response) {
				return response.ok ? response.json() : { results: [] };
			}).then(function (data) {
				if (input.value.trim() === query) {
					show(data.results);
				}
			}).catch(function () {
				// Suggestions are a convenience: say nothing when they can not be had
			});
		}

		input.addEventListener('input', function () {
			var query = input.value.trim();

			window.clearTimeout(timer);
			if (query.length < MIN_CHARS) {
				last = '';
				hide();
				return;
			}
			if (query === last) {
				return;
			}
			last = query;
			timer = window.setTimeout(function () {
				search(query);
			}, DELAY);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
