<?php
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

require_once( __DIR__ . '/SearchBackend.class.php' );

/**
 * The built-in search backend.
 *
 * It has no index and returns no candidates: the text search is added to the
 * issue filter query as SQL conditions (LIKE on the summary, description,
 * steps to reproduce, additional information and notes) by
 * BugFilterQuery::build_prop_search_sql().
 *
 * @package MantisBT
 * @subpackage Classes
 */
class SearchDatabaseBackend extends SearchBackend {
	/**
	 * @return string
	 */
	public function get_name() {
		return 'database';
	}
}
