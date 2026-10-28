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

	onReady(function () {
		initMenuButton();
	});
})(window, document);
