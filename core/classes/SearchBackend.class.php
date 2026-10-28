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

/**
 * The base class for search backend implementations.
 *
 * A search backend answers the text search of the issue filter. The built-in
 * one (SearchDatabaseBackend) searches with SQL; the others keep their own
 * index of the issues, which is filled and updated from the search queue
 * (see search_api.php) and only ever returns candidate issue ids. Those are
 * still run through the normal filter query, so access rules, projects and
 * all other filter properties apply as usual and a backend can never show an
 * issue the user would not otherwise see.
 *
 * @package MantisBT
 * @subpackage Classes
 */
abstract class SearchBackend {
	/**
	 * The name of the backend, as used in $g_search_backend.
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Whether the backend keeps its own index, which needs to be kept in sync
	 * with the issues.
	 *
	 * @return bool
	 */
	public function has_index() {
		return false;
	}

	/**
	 * Whether the backend is configured and can be reached.
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Create the index, or bring its settings up to date. It is safe to call
	 * this more than once.
	 *
	 * @return bool true on success
	 */
	public function ensure_index() {
		return true;
	}

	/**
	 * Add or replace issues in the index.
	 *
	 * @param array $p_documents The documents to index, as built by
	 *                           search_issue_document().
	 *
	 * @return bool true on success
	 */
	public function index_issues( array $p_documents ) {
		return true;
	}

	/**
	 * Remove issues from the index.
	 *
	 * @param array $p_bug_ids The ids of the issues to remove.
	 *
	 * @return bool true on success
	 */
	public function remove_issues( array $p_bug_ids ) {
		return true;
	}

	/**
	 * Remove all issues from the index.
	 *
	 * @return bool true on success
	 */
	public function clear_index() {
		return true;
	}

	/**
	 * Find the issues that match a search text.
	 *
	 * @param string $p_text    The text to search for, as typed by the user.
	 * @param array  $p_options Search options:
	 *        'limit'                      => int, the maximum number of ids to return;
	 *        'private_note_project_ids'   => int[], the projects in which the user
	 *                                        may search in private notes.
	 *
	 * @return int[]|null The matching issue ids, best match first, or null if
	 *                    the backend cannot answer, in which case the issue
	 *                    filter searches with SQL instead.
	 */
	public function search( $p_text, array $p_options ) {
		return null;
	}
}
