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
 * Like API
 *
 * Users can like an issue, to say they are affected by it or want it done. An
 * issue has one like per user, kept in {bug_like}, and the number of likes is
 * kept in {bug}.like_count so that lists can show and sort on it.
 *
 * @package CoreAPI
 * @subpackage LikeAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses access_api.php
 * @uses authentication_api.php
 * @uses bug_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses database_api.php
 * @uses event_api.php
 * @uses user_api.php
 */

require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'bug_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'database_api.php' );
require_api( 'event_api.php' );
require_api( 'user_api.php' );

/**
 * Check if likes are enabled.
 *
 * @param int $p_project_id A project identifier, or null for the current one.
 * @return bool
 * @access public
 */
function like_is_enabled( $p_project_id = null ) {
	return ON == config_get( 'enable_likes', null, null, $p_project_id );
}

/**
 * Check if a user can like an issue, or take their like back: likes are enabled,
 * the user is not the anonymous account, has the access level, and the issue
 * is not their own or read only if that is not allowed.
 *
 * @param int $p_bug_id  An issue identifier.
 * @param int $p_user_id A user identifier, or null for the current user.
 * @return bool
 * @access public
 */
function like_can_like( $p_bug_id, $p_user_id = null ) {
	$t_user_id = ( $p_user_id === null ) ? auth_get_current_user_id() : (int)$p_user_id;
	$t_project_id = bug_get_field( $p_bug_id, 'project_id' );

	if( !like_is_enabled( $t_project_id ) || user_is_anonymous( $t_user_id ) ) {
		return false;
	}

	if( !access_has_bug_level( config_get( 'like_bug_threshold', null, $t_user_id, $t_project_id ), $p_bug_id, $t_user_id ) ) {
		return false;
	}

	if( OFF == config_get( 'like_own_issue', null, $t_user_id, $t_project_id )
		&& bug_get_field( $p_bug_id, 'reporter_id' ) == $t_user_id ) {
		return false;
	}

	return ON == config_get( 'like_readonly_issues', null, $t_user_id, $t_project_id ) || !bug_is_readonly( $p_bug_id );
}

/**
 * Check if a user can see who likes an issue.
 *
 * @param int $p_bug_id  An issue identifier.
 * @param int $p_user_id A user identifier, or null for the current user.
 * @return bool
 * @access public
 */
function like_can_view_likers( $p_bug_id, $p_user_id = null ) {
	$t_user_id = ( $p_user_id === null ) ? auth_get_current_user_id() : (int)$p_user_id;

	return like_is_enabled( bug_get_field( $p_bug_id, 'project_id' ) )
		&& access_has_bug_level( config_get( 'show_likers_threshold' ), $p_bug_id, $t_user_id );
}

/**
 * Check if a user likes an issue.
 *
 * @param int $p_user_id A user identifier.
 * @param int $p_bug_id  An issue identifier.
 * @return bool
 * @access public
 */
function user_is_liking_bug( $p_user_id, $p_bug_id ) {
	db_param_push();
	$t_query = 'SELECT COUNT(*) FROM {bug_like} WHERE user_id=' . db_param() . ' AND bug_id=' . db_param();
	$t_result = db_query( $t_query, array( (int)$p_user_id, (int)$p_bug_id ) );

	return db_result( $t_result ) > 0;
}

/**
 * Get the ids of the users that like an issue, in the order they liked it.
 * The caller has to check that the user may see them, see like_can_view_likers().
 *
 * @param int $p_bug_id An issue identifier.
 * @return int[]
 * @access public
 */
function bug_get_likers( $p_bug_id ) {
	db_param_push();
	$t_query = 'SELECT user_id FROM {bug_like} WHERE bug_id=' . db_param() . ' ORDER BY date_submitted, user_id';
	$t_result = db_query( $t_query, array( (int)$p_bug_id ) );

	$t_user_ids = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_user_ids[] = (int)$t_row['user_id'];
	}

	return $t_user_ids;
}

/**
 * Get the number of users that like an issue.
 *
 * @param int $p_bug_id An issue identifier.
 * @return int
 * @access public
 */
function bug_get_like_count( $p_bug_id ) {
	return (int)bug_get_field( $p_bug_id, 'like_count' );
}

/**
 * Make a user like an issue. Nothing happens if they already do.
 *
 * It does not check what the user may do, see like_can_like(), and it does not
 * change the date the issue was last updated.
 *
 * @param int $p_bug_id  An issue identifier.
 * @param int $p_user_id A user identifier.
 * @return bool true if the user likes the issue now, false if they could not
 *              (the anonymous account).
 * @access public
 */
function bug_like( $p_bug_id, $p_user_id ) {
	$c_bug_id = (int)$p_bug_id;
	$c_user_id = (int)$p_user_id;

	if( user_is_anonymous( $c_user_id ) ) {
		return false;
	}

	if( user_is_liking_bug( $c_user_id, $c_bug_id ) ) {
		return true;
	}

	db_param_push();
	$t_query = 'INSERT INTO {bug_like} ( bug_id, user_id, date_submitted ) VALUES ( '
		. db_param() . ', ' . db_param() . ', ' . db_param() . ' )';
	db_query( $t_query, array( $c_bug_id, $c_user_id, db_now() ) );

	bug_like_update_count( $c_bug_id );

	event_signal( 'EVENT_BUG_LIKE', array( $c_bug_id, $c_user_id ) );

	return true;
}

/**
 * Take back the like of a user for an issue. Nothing happens if they do not like it.
 *
 * @param int $p_bug_id  An issue identifier.
 * @param int $p_user_id A user identifier.
 * @return bool true
 * @access public
 */
function bug_unlike( $p_bug_id, $p_user_id ) {
	$c_bug_id = (int)$p_bug_id;
	$c_user_id = (int)$p_user_id;

	if( !user_is_liking_bug( $c_user_id, $c_bug_id ) ) {
		return true;
	}

	db_param_push();
	$t_query = 'DELETE FROM {bug_like} WHERE bug_id=' . db_param() . ' AND user_id=' . db_param();
	db_query( $t_query, array( $c_bug_id, $c_user_id ) );

	bug_like_update_count( $c_bug_id );

	event_signal( 'EVENT_BUG_UNLIKE', array( $c_bug_id, $c_user_id ) );

	return true;
}

/**
 * Set the number of likes of an issue from its likes.
 *
 * @param int $p_bug_id An issue identifier.
 * @return void
 * @access public
 */
function bug_like_update_count( $p_bug_id ) {
	db_param_push();
	$t_query = 'UPDATE {bug} SET like_count=( SELECT COUNT(*) FROM {bug_like} WHERE bug_id=' . db_param() . ' ) WHERE id=' . db_param();
	db_query( $t_query, array( (int)$p_bug_id, (int)$p_bug_id ) );

	bug_clear_cache( $p_bug_id );
}

/**
 * Delete all the likes of an issue, for when it is deleted.
 *
 * @param int $p_bug_id An issue identifier.
 * @return void
 * @access public
 */
function bug_like_delete_all( $p_bug_id ) {
	db_param_push();
	$t_query = 'DELETE FROM {bug_like} WHERE bug_id=' . db_param();
	db_query( $t_query, array( (int)$p_bug_id ) );
}

/**
 * Delete all the likes of a user, for when the user is deleted, and update the
 * count of the issues they liked.
 *
 * @param int $p_user_id A user identifier.
 * @return void
 * @access public
 */
function user_like_delete_all( $p_user_id ) {
	db_param_push();
	$t_query = 'SELECT bug_id FROM {bug_like} WHERE user_id=' . db_param();
	$t_result = db_query( $t_query, array( (int)$p_user_id ) );

	$t_bug_ids = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_bug_ids[] = (int)$t_row['bug_id'];
	}

	db_param_push();
	$t_query = 'DELETE FROM {bug_like} WHERE user_id=' . db_param();
	db_query( $t_query, array( (int)$p_user_id ) );

	foreach( $t_bug_ids as $t_bug_id ) {
		bug_like_update_count( $t_bug_id );
	}
}

/**
 * Get what the current user can know about the likes of an issue.
 *
 * @param int $p_bug_id An issue identifier.
 * @return array With: count (the number of likes), liked_by_me, can_like (whether
 *               the user can like or unlike it now) and, if the user may see who
 *               likes it, likers (an array of id and name).
 * @access public
 */
function like_get_info( $p_bug_id ) {
	$t_user_id = auth_get_current_user_id();

	$t_info = array(
		'count' => bug_get_like_count( $p_bug_id ),
		'liked_by_me' => user_is_liking_bug( $t_user_id, $p_bug_id ),
		'can_like' => like_can_like( $p_bug_id, $t_user_id ),
	);

	if( like_can_view_likers( $p_bug_id, $t_user_id ) ) {
		$t_info['likers'] = array();
		foreach( bug_get_likers( $p_bug_id ) as $t_liker_id ) {
			$t_info['likers'][] = array( 'id' => $t_liker_id, 'name' => user_get_name( $t_liker_id ) );
		}
	}

	return $t_info;
}
