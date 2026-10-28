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
 * User merge API
 *
 * Merging a user into another one gives everything the first user did and owns
 * to the second one, and deletes the first. It is for duplicate accounts: the
 * same person who signed up twice, or who got a new account when the way they
 * sign in changed.
 *
 * @package CoreAPI
 * @subpackage UserMergeAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses bug_api.php
 * @uses bugnote_api.php
 * @uses constant_inc.php
 * @uses database_api.php
 * @uses event_api.php
 * @uses logging_api.php
 * @uses user_api.php
 */

use Mantis\Exceptions\ClientException;

require_api( 'bug_api.php' );
require_api( 'bugnote_api.php' );
require_api( 'constant_inc.php' );
require_api( 'database_api.php' );
require_api( 'event_api.php' );
require_api( 'logging_api.php' );
require_api( 'user_api.php' );

/**
 * The columns that hold the identifier of the user who did or owns something,
 * where each row can simply be given to the user that remains: the table, as
 * it is written in a query, and the column.
 *
 * Not in the list: what the user needs to sign in and what is theirs alone and
 * is deleted with the account (the tokens, the preferences), and the rows that
 * can only be there once for each user, see user_merge().
 *
 * @return array A list of arrays with the table and the column.
 * @access public
 */
function user_merge_get_references() {
	return array(
		array( '{bug}', 'reporter_id' ),
		array( '{bug}', 'handler_id' ),
		array( '{bugnote}', 'reporter_id' ),
		array( '{bug_file}', 'user_id' ),
		array( '{bug_history}', 'user_id' ),
		array( '{bug_revision}', 'user_id' ),
		array( '{bug_tag}', 'user_id' ),
		array( '{category}', 'user_id' ),
		array( '{news}', 'poster_id' ),
		array( '{project_file}', 'user_id' ),
		array( '{sponsorship}', 'user_id' ),
		array( '{tag}', 'user_id' ),
		array( '{filters}', 'user_id' ),
		array( '{user_profile}', 'user_id' ),
	);
}

/**
 * The rows that a user can only have once, with the columns that tell what they
 * are for. They are given to the user that remains, unless that user already
 * has such a row: theirs is kept.
 *
 * @return array The table, as written in a query, and the columns of the rest of its key.
 * @access public
 */
function user_merge_get_unique_references() {
	return array(
		'{bug_monitor}' => array( 'bug_id' ),
		'{bug_like}' => array( 'bug_id' ),
		'{project_user_list}' => array( 'project_id' ),
		'{config}' => array( 'config_id', 'project_id' ),
	);
}

/**
 * Count what a merge of a user would give to the other user, to show it before.
 *
 * @param int $p_user_id A user identifier.
 *
 * @return array The number of issues reported, issues assigned and notes.
 * @access public
 */
function user_merge_count_references( $p_user_id ) {
	$t_counts = array();

	foreach( array( 'reported' => array( '{bug}', 'reporter_id' ),
			'assigned' => array( '{bug}', 'handler_id' ),
			'notes' => array( '{bugnote}', 'reporter_id' ) ) as $t_name => $t_reference ) {
		db_param_push();
		$t_query = 'SELECT COUNT(*) FROM ' . $t_reference[0] . ' WHERE ' . $t_reference[1] . '=' . db_param();
		$t_counts[$t_name] = (int)db_result( db_query( $t_query, array( (int)$p_user_id ) ) );
	}

	return $t_counts;
}

/**
 * Give what a user did and owns to another user, and delete the first user.
 *
 * The issues they reported or handle, their notes, files, history entries,
 * revisions, tags, categories, news, sponsorships, filters and profiles are
 * given to the other user. Their monitoring of issues, likes, project access
 * and configuration are given too, except where the other user has their own:
 * that is kept. The account, preferences, tokens and API tokens of the first user
 * are deleted. Plugins that keep rows for users do the same on
 * EVENT_MANAGE_USER_MERGE.
 *
 * Everything is done in a transaction. Things to know:
 * - the user that remains is not checked to have the access level needed to
 *   handle the issues that were assigned to the other user, nor is their access
 *   level changed;
 * - the history of the issues says who made each change, and now says the user
 *   that remains, but the old and the new value of a change of handler or
 *   reporter are the identifiers that were saved, and are not changed;
 * - the notes and the text of issues that mention the deleted user keep the
 *   name that was written.
 *
 * It does not check the access of the user who asks for the merge: the caller
 * does, see UserMergeCommand.
 *
 * @param int $p_source_user_id The user to merge: it is deleted.
 * @param int $p_target_user_id The user that gets everything.
 *
 * @return array For each table and column, the number of rows that were given to the other user.
 * @throws ClientException If the users are not valid for a merge.
 * @access public
 */
function user_merge( $p_source_user_id, $p_target_user_id ) {
	$t_source_id = (int)$p_source_user_id;
	$t_target_id = (int)$p_target_user_id;

	user_ensure_exists( $t_source_id );
	user_ensure_exists( $t_target_id );

	if( $t_source_id == $t_target_id ) {
		throw new ClientException( 'A user cannot be merged into itself', ERROR_INVALID_FIELD_VALUE, array( 'target' ) );
	}

	# The first user is deleted, and nothing is given to the anonymous user
	user_ensure_unprotected( $t_source_id );
	if( user_is_anonymous( $t_target_id ) ) {
		throw new ClientException( 'Nothing can be merged into the anonymous user', ERROR_PROTECTED_ACCOUNT );
	}

	$t_source = user_get_row( $t_source_id );
	$t_target = user_get_row( $t_target_id );
	$t_moved = array();

	db_begin();

	try {
		foreach( user_merge_get_references() as $t_reference ) {
			list( $t_table, $t_column ) = $t_reference;

			db_param_push();
			$t_query = 'UPDATE ' . $t_table . ' SET ' . $t_column . '=' . db_param() . ' WHERE ' . $t_column . '=' . db_param();
			db_query( $t_query, array( $t_target_id, $t_source_id ) );

			$t_moved[trim( $t_table, '{}' ) . '.' . $t_column] = db_affected_rows();
		}

		foreach( user_merge_get_unique_references() as $t_table => $t_key_columns ) {
			$t_moved[trim( $t_table, '{}' )] = user_merge_give_unique_rows( $t_table, $t_key_columns, $t_source_id, $t_target_id );
		}

		# Plugins give their own rows, in the same transaction: an error stops the merge
		$t_source_info = $t_source;
		unset( $t_source_info['password'], $t_source_info['cookie_string'] );
		event_signal( 'EVENT_MANAGE_USER_MERGE', array( $t_source_id, $t_target_id, $t_source_info ) );

		# What is left of the first user: the rows that the other user has too, the
		# preferences, the tokens. The likes they had where the other user has one
		# are deleted, and the count of likes of the issues is updated.
		db_param_push();
		$t_query = 'DELETE FROM {config} WHERE user_id=' . db_param();
		db_query( $t_query, array( $t_source_id ) );

		user_delete( $t_source_id );

		db_commit();
	} catch( Exception $e ) {
		db_rollback();
		throw $e;
	}

	user_clear_cache();
	bug_clear_cache_all();
	bugnote_clear_cache();
	user_pref_clear_cache();

	log_event( LOG_USER_MANAGEMENT, 'User @U%d (%s) merged into @U%d (%s): %s',
		$t_source_id, $t_source['username'], $t_target_id, $t_target['username'], json_encode( $t_moved ) );

	return $t_moved;
}

/**
 * Give the rows of a user to another user, in a table where a user can only
 * have one row for what the other columns of the key tell: the rows that the
 * other user has already are not changed, and the rows of the first user are
 * left in the table.
 *
 * It selects what the other user has instead of excluding it in the update, a
 * query that selects from the table it updates is not accepted by MySQL.
 *
 * @param string $p_table       The table, as written in a query.
 * @param array  $p_key_columns The columns of the key of the table except the user.
 * @param int    $p_source_id   The user that gives the rows.
 * @param int    $p_target_id   The user that gets them.
 *
 * @return int The number of rows that were given.
 */
function user_merge_give_unique_rows( $p_table, array $p_key_columns, $p_source_id, $p_target_id ) {
	$t_columns = implode( ',', $p_key_columns );

	$t_rows = array();
	foreach( array( $p_target_id, $p_source_id ) as $t_user_id ) {
		db_param_push();
		$t_query = 'SELECT ' . $t_columns . ' FROM ' . $p_table . ' WHERE user_id=' . db_param();
		$t_result = db_query( $t_query, array( (int)$t_user_id ) );

		$t_rows[$t_user_id] = array();
		while( $t_row = db_fetch_array( $t_result ) ) {
			$t_rows[$t_user_id][] = $t_row;
		}
	}

	$t_kept = array();
	foreach( $t_rows[$p_target_id] as $t_row ) {
		$t_kept[implode( '|', $t_row )] = true;
	}

	$t_given = 0;
	foreach( $t_rows[$p_source_id] as $t_row ) {
		if( isset( $t_kept[implode( '|', $t_row )] ) ) {
			continue;
		}

		db_param_push();
		$t_query = 'UPDATE ' . $p_table . ' SET user_id=' . db_param() . ' WHERE user_id=' . db_param();
		$t_parameters = array( (int)$p_target_id, (int)$p_source_id );
		foreach( $p_key_columns as $t_column ) {
			$t_query .= ' AND ' . $t_column . '=' . db_param();
			$t_parameters[] = $t_row[$t_column];
		}
		db_query( $t_query, $t_parameters );

		$t_given++;
	}

	return $t_given;
}
