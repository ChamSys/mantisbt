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
 * Delete a team, after asking
 *
 * @package MantisBT
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses access_api.php
 * @uses authentication_api.php
 * @uses config_api.php
 * @uses form_api.php
 * @uses gpc_api.php
 * @uses helper_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses string_api.php
 * @uses team_api.php
 */

require_once( 'core.php' );
require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'config_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'helper_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );
require_api( 'team_api.php' );

form_security_validate( 'manage_team_delete' );

auth_reauthenticate();

team_ensure_enabled();
access_ensure_global_level( config_get( 'manage_team_threshold' ) );

$f_team_id = gpc_get_int( 'team_id' );
$t_issue_counts = team_get_issue_counts();

# Confirm with the user
helper_ensure_confirmed(
	sprintf( lang_get( 'team_delete_confirm_msg' ), string_display_line( team_get_name( $f_team_id ) ), isset( $t_issue_counts[$f_team_id] ) ? $t_issue_counts[$f_team_id] : 0 ),
	lang_get( 'team_delete' ) );

team_delete( $f_team_id );

form_security_purge( 'manage_team_delete' );

print_header_redirect( 'manage_team_page.php' );
