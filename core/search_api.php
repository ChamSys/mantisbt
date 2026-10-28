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
 * Search API
 *
 * @package CoreAPI
 * @subpackage SearchAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses database_api.php
 * @uses error_api.php
 */

require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'database_api.php' );
require_api( 'error_api.php' );

require_once( __DIR__ . '/classes/SearchBackend.class.php' );

/**
 * The issues added to the search queue by this request.
 * @global array $g_search_queued_ids
 */
$g_search_queued_ids = array();

/**
 * Get the search backend selected with $g_search_backend.
 *
 * @return SearchBackend The backend instance; the same one on every call.
 * @access public
 */
function search_backend_get() {
	static $s_backend = null;

	if( $s_backend === null ) {
		$t_name = config_get_global( 'search_backend' );

		# The name ends up in a file name, so only accept plain names.
		$t_class = 'Search' . ucfirst( $t_name ) . 'Backend';
		$t_file = __DIR__ . '/classes/' . $t_class . '.class.php';
		if( !preg_match( '/^[a-z]+$/', $t_name ) || !is_file( $t_file ) ) {
			error_parameters( 'search_backend', $t_name );
			trigger_error( ERROR_CONFIG_OPT_INVALID, ERROR );
		}

		require_once( $t_file );
		$s_backend = new $t_class();
	}

	return $s_backend;
}

/**
 * Build the documents that represent issues in the index of a search backend.
 *
 * The data is read straight from the database, not through the issue API, so
 * that it does not depend on who is logged in: access to the results is
 * decided later by the issue filter. Private note text is kept apart from
 * public note text, so that it can be left out of searches by users who may
 * not read it.
 *
 * @param array $p_bug_ids The ids of the issues.
 *
 * @return array The documents, by issue id. Issues that do not exist have none.
 *               Each document has the keys: id, project_id, summary,
 *               description, steps_to_reproduce, additional_information,
 *               category, tags, notes (the text of the public notes) and
 *               private_notes (the text of the private notes).
 * @access public
 */
function search_issue_documents( array $p_bug_ids ) {
	$t_bug_ids = array_values( array_unique( array_map( 'intval', $p_bug_ids ) ) );
	if( empty( $t_bug_ids ) ) {
		return array();
	}

	$t_documents = array();

	$t_query = new DbQuery();
	$t_query->sql( 'SELECT b.id, b.project_id, b.summary, c.name AS category,'
		. ' t.description, t.steps_to_reproduce, t.additional_information'
		. ' FROM {bug} b JOIN {bug_text} t ON b.bug_text_id = t.id'
		. ' LEFT JOIN {category} c ON b.category_id = c.id'
		. ' WHERE b.id IN ' . $t_query->param( $t_bug_ids ) );
	foreach( $t_query->fetch_all() as $t_row ) {
		$t_id = (int)$t_row['id'];
		$t_documents[$t_id] = array(
			'id' => $t_id,
			'project_id' => (int)$t_row['project_id'],
			'summary' => (string)$t_row['summary'],
			'description' => (string)$t_row['description'],
			'steps_to_reproduce' => (string)$t_row['steps_to_reproduce'],
			'additional_information' => (string)$t_row['additional_information'],
			'category' => (string)$t_row['category'],
			'tags' => array(),
			'notes' => '',
			'private_notes' => '',
		);
	}
	if( empty( $t_documents ) ) {
		return array();
	}
	$t_found_ids = array_keys( $t_documents );

	$t_query = new DbQuery();
	$t_query->sql( 'SELECT bt.bug_id, g.name FROM {bug_tag} bt JOIN {tag} g ON bt.tag_id = g.id'
		. ' WHERE bt.bug_id IN ' . $t_query->param( $t_found_ids ) );
	foreach( $t_query->fetch_all() as $t_row ) {
		$t_documents[(int)$t_row['bug_id']]['tags'][] = (string)$t_row['name'];
	}

	$t_query = new DbQuery();
	$t_query->sql( 'SELECT n.bug_id, n.view_state, x.note FROM {bugnote} n'
		. ' JOIN {bugnote_text} x ON n.bugnote_text_id = x.id'
		. ' WHERE n.bug_id IN ' . $t_query->param( $t_found_ids )
		. ' ORDER BY n.id' );
	foreach( $t_query->fetch_all() as $t_row ) {
		$t_field = ( (int)$t_row['view_state'] == VS_PRIVATE ) ? 'private_notes' : 'notes';
		$t_bug_id = (int)$t_row['bug_id'];
		$t_documents[$t_bug_id][$t_field] .= ( $t_documents[$t_bug_id][$t_field] === '' ? '' : "\n" ) . $t_row['note'];
	}

	return $t_documents;
}

/**
 * Note that an issue has changed, or was deleted, so that its entry in the
 * index of the search backend is updated by search_process_queue(). Does
 * nothing when the backend has no index.
 *
 * @param int $p_bug_id The issue identifier.
 *
 * @return void
 * @access public
 */
function search_queue_add( $p_bug_id ) {
	global $g_search_queued_ids;

	# One entry per issue is enough for as long as the queue is not processed.
	$c_bug_id = (int)$p_bug_id;
	if( isset( $g_search_queued_ids[$c_bug_id] ) || !search_backend_get()->has_index() ) {
		return;
	}
	$g_search_queued_ids[$c_bug_id] = true;

	db_param_push();
	$t_query = 'INSERT INTO {search_queue} ( bug_id, date_queued ) VALUES ( ' . db_param() . ', ' . db_param() . ' )';
	db_query( $t_query, array( $c_bug_id, db_now() ) );
}

/**
 * Update the index of the search backend with the issues in the queue.
 *
 * Issues that exist are added to the index, or replaced, and the ones that
 * no longer exist are removed from it. Entries are only taken off the queue
 * once the backend has accepted the change, so it is tried again later if the
 * backend is not available.
 *
 * @param int $p_max_issues The maximum number of issues to process.
 *
 * @return int|false The number of issues processed, or false if the backend
 *                   could not be updated.
 * @access public
 */
function search_process_queue( $p_max_issues = 100 ) {
	global $g_search_queued_ids;

	$t_backend = search_backend_get();
	if( !$t_backend->has_index() ) {
		return 0;
	}

	# Issues that change from now on need to be queued again.
	$g_search_queued_ids = array();

	# Take the oldest entries, up to the maximum number of different issues.
	$t_query = new DbQuery( 'SELECT id, bug_id FROM {search_queue} ORDER BY id' );
	$t_queue_ids = array();
	$t_bug_ids = array();
	$t_query->execute();
	while( $t_row = $t_query->fetch() ) {
		if( !isset( $t_bug_ids[(int)$t_row['bug_id']] ) && count( $t_bug_ids ) >= $p_max_issues ) {
			break;
		}
		$t_bug_ids[(int)$t_row['bug_id']] = true;
		$t_queue_ids[] = (int)$t_row['id'];
	}
	if( empty( $t_queue_ids ) ) {
		return 0;
	}

	$t_documents = search_issue_documents( array_keys( $t_bug_ids ) );
	$t_removed_ids = array_diff( array_keys( $t_bug_ids ), array_keys( $t_documents ) );
	if( !$t_backend->index_issues( $t_documents ) || !$t_backend->remove_issues( $t_removed_ids ) ) {
		return false;
	}

	# Entries added after the ones taken above are kept: the issue may have
	# changed since its document was built.
	foreach( array_chunk( $t_queue_ids, 500 ) as $t_chunk ) {
		$t_query = new DbQuery();
		$t_query->sql( 'DELETE FROM {search_queue} WHERE id IN ' . $t_query->param( $t_chunk ) );
		$t_query->execute();
	}

	return count( $t_bug_ids );
}
