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
 * User Picker API
 *
 * Helps lists of users that are too long to be a drop-down become a search as
 * the user types, see js/user_typeahead.js and UserSearchCommand.
 *
 * @package CoreAPI
 * @subpackage UserPickerAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses config_api.php
 * @uses helper_api.php
 * @uses string_api.php
 * @uses user_api.php
 */

require_api( 'config_api.php' );
require_api( 'helper_api.php' );
require_api( 'string_api.php' );
require_api( 'user_api.php' );

/**
 * Get what a search for users in a field needs to know. The page asks for a scope
 * by name and never gives an access level, so a user can not search for more
 * users than the field would list.
 *
 * @param string $p_scope The scope: reporter (who can report issues), handler (who can
 *                        handle them), monitor (who can monitor them) or note (who can add notes).
 * @return array|null The configuration option with the access level of the users in the
 *                    scope ('access'), and the one with the access level needed to see
 *                    them ('gate', or null for none); null if there is no such scope.
 * @access public
 */
function user_typeahead_get_scope( $p_scope ) {
	$t_scopes = array(
		'reporter' => array( 'access' => 'report_bug_threshold', 'gate' => null ),
		'handler' => array( 'access' => 'handle_bug_threshold', 'gate' => 'view_handler_threshold' ),
		'monitor' => array( 'access' => 'monitor_bug_threshold', 'gate' => 'show_monitor_list_threshold' ),
		'note' => array( 'access' => 'add_bugnote_threshold', 'gate' => 'view_handler_threshold' ),
	);

	return isset( $t_scopes[$p_scope] ) ? $t_scopes[$p_scope] : null;
}

/**
 * Check if a list of users is too long to print, so that it is searched instead
 * (see $g_user_typeahead_min_users).
 *
 * @param int|array $p_access     An access level, or a list of them.
 * @param int       $p_project_id A project identifier.
 * @return bool
 * @access public
 */
function user_typeahead_is_active( $p_access, $p_project_id ) {
	$t_min = (int)config_get( 'user_typeahead_min_users' );

	return $t_min > 0 && count( user_get_option_rows( $p_project_id, $p_access ) ) > $t_min;
}

/**
 * Print the attributes that make a <select> of users a search as the user types,
 * when its list is too long.
 *
 * @param string    $p_scope      The scope, see user_typeahead_get_scope().
 * @param int       $p_project_id A project identifier.
 * @return bool true if the attributes were printed, that is the list is searched.
 * @access public
 */
function print_user_typeahead_attributes( $p_scope, $p_project_id ) {
	$t_scope = user_typeahead_get_scope( $p_scope );
	if( $t_scope === null ) {
		return false;
	}

	$t_access = config_get( $t_scope['access'], null, null, $p_project_id );
	if( !user_typeahead_is_active( $t_access, $p_project_id ) ) {
		return false;
	}

	echo ' data-user-typeahead="', string_attribute( $p_scope ), '"',
		' data-user-typeahead-url="', string_attribute( helper_mantis_url( 'api/rest/index.php/internal/user_search' ) ), '"',
		' data-user-typeahead-project="', (int)$p_project_id, '"';

	return true;
}
