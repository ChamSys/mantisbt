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
 * Team API
 *
 * A team is a named group of users that an issue can be assigned to. The team
 * of an issue is kept in {bug}.team_id, next to its handler: the team owns the
 * issue, and one of its members can take it on as the handler. A team does not
 * give its members access to anything, who may see and change an issue stays
 * what the access levels of the project say.
 *
 * Teams belong to no project and a user can be in any number of them. The
 * teams are in {team} and the members in {team_user}.
 *
 * @package CoreAPI
 * @subpackage TeamAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses authentication_api.php
 * @uses bug_api.php
 * @uses constant_inc.php
 * @uses database_api.php
 * @uses error_api.php
 * @uses history_api.php
 * @uses user_api.php
 * @uses utility_api.php
 */

require_api( 'authentication_api.php' );
require_api( 'bug_api.php' );
require_api( 'constant_inc.php' );
require_api( 'database_api.php' );
require_api( 'error_api.php' );
require_api( 'history_api.php' );
require_api( 'user_api.php' );
require_api( 'utility_api.php' );

# The teams, by id, as loaded by team_cache_all(), and the ids of the teams of
# the users that have been asked for.
$g_cache_teams = null;
$g_cache_user_team_ids = array();

/**
 * Load all the teams, ordered by name, into the cache.
 *
 * @return array The rows of the teams, by team id.
 * @access public
 */
function team_cache_all() {
	global $g_cache_teams;

	if( $g_cache_teams === null ) {
		$t_query = new DbQuery( 'SELECT * FROM {team} ORDER BY name' );
		$t_query->execute();

		$g_cache_teams = array();
		while( $t_row = $t_query->fetch() ) {
			$t_row['id'] = (int)$t_row['id'];
			$g_cache_teams[$t_row['id']] = $t_row;
		}
	}

	return $g_cache_teams;
}

/**
 * Forget what was loaded about the teams, for after they were changed.
 *
 * @return void
 * @access public
 */
function team_clear_cache() {
	global $g_cache_teams, $g_cache_user_team_ids;

	$g_cache_teams = null;
	$g_cache_user_team_ids = array();
}

/**
 * Get all the teams.
 *
 * @return array The rows of the teams (id, name, description, date_created),
 *               ordered by name, with the team id as the key.
 * @access public
 */
function team_get_all() {
	return team_cache_all();
}

/**
 * Check if a team exists.
 *
 * @param int $p_team_id A team identifier.
 * @return bool
 * @access public
 */
function team_exists( $p_team_id ) {
	return isset( team_cache_all()[(int)$p_team_id] );
}

/**
 * Fail unless a team exists.
 *
 * @param int $p_team_id A team identifier.
 * @return void
 * @access public
 */
function team_ensure_exists( $p_team_id ) {
	if( !team_exists( $p_team_id ) ) {
		error_parameters( (int)$p_team_id );
		trigger_error( ERROR_TEAM_NOT_FOUND, ERROR );
	}
}

/**
 * Get the row of a team.
 *
 * @param int $p_team_id A team identifier.
 * @return array The id, name, description and date_created of the team.
 * @access public
 */
function team_get_row( $p_team_id ) {
	team_ensure_exists( $p_team_id );

	return team_cache_all()[(int)$p_team_id];
}

/**
 * Get one field of a team.
 *
 * @param int    $p_team_id    A team identifier.
 * @param string $p_field_name A column of {team}.
 * @return mixed
 * @access public
 */
function team_get_field( $p_team_id, $p_field_name ) {
	$t_row = team_get_row( $p_team_id );

	if( !isset( $t_row[$p_field_name] ) ) {
		error_parameters( $p_field_name );
		trigger_error( ERROR_DB_FIELD_NOT_FOUND, WARNING );
		return '';
	}

	return $t_row[$p_field_name];
}

/**
 * Get the name of a team, to show it.
 *
 * @param int $p_team_id A team identifier, 0 for no team.
 * @return string The name, or an empty string for no team or one that was deleted.
 * @access public
 */
function team_get_name( $p_team_id ) {
	$t_teams = team_cache_all();

	return isset( $t_teams[(int)$p_team_id] ) ? $t_teams[(int)$p_team_id]['name'] : '';
}

/**
 * Check that no other team has a name. Names are compared the way the
 * database compares them, which is without regard to case.
 *
 * @param string $p_name       A team name.
 * @param int    $p_exclude_id A team that may have the name already, for when it is renamed.
 * @return bool
 * @access public
 */
function team_is_name_unique( $p_name, $p_exclude_id = 0 ) {
	$t_query = new DbQuery();
	$t_query->sql( 'SELECT COUNT(*) FROM {team} WHERE name=' . $t_query->param( $p_name ) );
	if( $p_exclude_id ) {
		$t_query->append_sql( ' AND id <> ' . $t_query->param( (int)$p_exclude_id ) );
	}
	$t_query->execute();

	return $t_query->value() == 0;
}

/**
 * Fail if the name of a team is blank or taken.
 *
 * @param string $p_name       A team name.
 * @param int    $p_exclude_id A team that may have the name already, for when it is renamed.
 * @return void
 * @access public
 */
function team_ensure_name_valid( $p_name, $p_exclude_id = 0 ) {
	if( is_blank( $p_name ) ) {
		trigger_error( ERROR_TEAM_NAME_INVALID, ERROR );
	}

	if( !team_is_name_unique( $p_name, $p_exclude_id ) ) {
		trigger_error( ERROR_TEAM_NAME_NOT_UNIQUE, ERROR );
	}
}

/**
 * Create a team.
 *
 * @param string $p_name        The name, it has to be unique.
 * @param string $p_description What the team is for.
 * @return int The identifier of the new team.
 * @access public
 */
function team_create( $p_name, $p_description = '' ) {
	$t_name = trim( $p_name );
	team_ensure_name_valid( $t_name );

	db_param_push();
	$t_query = 'INSERT INTO {team} ( name, description, date_created ) VALUES ( '
		. db_param() . ', ' . db_param() . ', ' . db_param() . ' )';
	db_query( $t_query, array( $t_name, (string)$p_description, db_now() ) );
	$t_team_id = db_insert_id( db_get_table( 'team' ) );

	team_clear_cache();

	return (int)$t_team_id;
}

/**
 * Change the name and description of a team.
 *
 * @param int    $p_team_id      A team identifier.
 * @param string $p_name         The new name, it has to be unique.
 * @param string $p_description  The new description.
 * @return void
 * @access public
 */
function team_update( $p_team_id, $p_name, $p_description ) {
	team_ensure_exists( $p_team_id );

	$t_name = trim( $p_name );
	team_ensure_name_valid( $t_name, (int)$p_team_id );

	db_param_push();
	$t_query = 'UPDATE {team} SET name=' . db_param() . ', description=' . db_param() . ' WHERE id=' . db_param();
	db_query( $t_query, array( $t_name, (string)$p_description, (int)$p_team_id ) );

	team_clear_cache();
}

/**
 * Delete a team and its memberships. The issues assigned to it are left
 * without a team, with a line in the history of each. Their last update date
 * is kept.
 *
 * @param int $p_team_id A team identifier.
 * @return void
 * @access public
 */
function team_delete( $p_team_id ) {
	team_ensure_exists( $p_team_id );
	$c_team_id = (int)$p_team_id;

	db_param_push();
	$t_query = 'SELECT id FROM {bug} WHERE team_id=' . db_param();
	$t_result = db_query( $t_query, array( $c_team_id ) );
	$t_bug_ids = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_bug_ids[] = (int)$t_row['id'];
	}

	db_param_push();
	$t_query = 'UPDATE {bug} SET team_id=0 WHERE team_id=' . db_param();
	db_query( $t_query, array( $c_team_id ) );

	foreach( $t_bug_ids as $t_bug_id ) {
		history_log_event_direct( $t_bug_id, 'team_id', $c_team_id, 0 );
		bug_clear_cache( $t_bug_id );
	}

	db_param_push();
	$t_query = 'DELETE FROM {team_user} WHERE team_id=' . db_param();
	db_query( $t_query, array( $c_team_id ) );

	db_param_push();
	$t_query = 'DELETE FROM {team} WHERE id=' . db_param();
	db_query( $t_query, array( $c_team_id ) );

	team_clear_cache();
}

/**
 * Get the members of a team.
 *
 * @param int $p_team_id A team identifier.
 * @return int[] The user ids, ordered by user name.
 * @access public
 */
function team_get_member_ids( $p_team_id ) {
	db_param_push();
	$t_query = 'SELECT tu.user_id FROM {team_user} tu JOIN {user} u ON u.id = tu.user_id'
		. ' WHERE tu.team_id=' . db_param() . ' ORDER BY u.username';
	$t_result = db_query( $t_query, array( (int)$p_team_id ) );

	$t_user_ids = array();
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_user_ids[] = (int)$t_row['user_id'];
	}

	return $t_user_ids;
}

/**
 * Get the teams a user is in.
 *
 * @param int $p_user_id A user identifier, or null for the current user.
 * @return int[] The team ids.
 * @access public
 */
function user_get_team_ids( $p_user_id = null ) {
	global $g_cache_user_team_ids;

	$c_user_id = ( $p_user_id === null ) ? auth_get_current_user_id() : (int)$p_user_id;

	if( !isset( $g_cache_user_team_ids[$c_user_id] ) ) {
		db_param_push();
		$t_query = 'SELECT team_id FROM {team_user} WHERE user_id=' . db_param() . ' ORDER BY team_id';
		$t_result = db_query( $t_query, array( $c_user_id ) );

		$g_cache_user_team_ids[$c_user_id] = array();
		while( $t_row = db_fetch_array( $t_result ) ) {
			$g_cache_user_team_ids[$c_user_id][] = (int)$t_row['team_id'];
		}
	}

	return $g_cache_user_team_ids[$c_user_id];
}

/**
 * Check if a user is in a team.
 *
 * @param int $p_team_id A team identifier.
 * @param int $p_user_id A user identifier, or null for the current user.
 * @return bool
 * @access public
 */
function team_has_member( $p_team_id, $p_user_id = null ) {
	return in_array( (int)$p_team_id, user_get_team_ids( $p_user_id ), true );
}

/**
 * Put a user in a team. Nothing happens if they are in it already.
 *
 * @param int $p_team_id A team identifier.
 * @param int $p_user_id A user identifier.
 * @return void
 * @access public
 */
function team_add_member( $p_team_id, $p_user_id ) {
	team_ensure_exists( $p_team_id );
	user_ensure_exists( $p_user_id );

	if( team_has_member( $p_team_id, $p_user_id ) ) {
		return;
	}

	db_param_push();
	$t_query = 'INSERT INTO {team_user} ( team_id, user_id ) VALUES ( ' . db_param() . ', ' . db_param() . ' )';
	db_query( $t_query, array( (int)$p_team_id, (int)$p_user_id ) );

	team_clear_cache();
}

/**
 * Take a user out of a team. Nothing happens if they are not in it.
 *
 * @param int $p_team_id A team identifier.
 * @param int $p_user_id A user identifier.
 * @return void
 * @access public
 */
function team_remove_member( $p_team_id, $p_user_id ) {
	db_param_push();
	$t_query = 'DELETE FROM {team_user} WHERE team_id=' . db_param() . ' AND user_id=' . db_param();
	db_query( $t_query, array( (int)$p_team_id, (int)$p_user_id ) );

	team_clear_cache();
}

/**
 * Take a user out of every team, for when the user is deleted.
 *
 * @param int $p_user_id A user identifier.
 * @return void
 * @access public
 */
function user_team_delete_all( $p_user_id ) {
	db_param_push();
	$t_query = 'DELETE FROM {team_user} WHERE user_id=' . db_param();
	db_query( $t_query, array( (int)$p_user_id ) );

	team_clear_cache();
}
