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
 * Merge a user into another user, after manage_user_merge_page.php.
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses authentication_api.php
 * @uses form_api.php
 * @uses gpc_api.php
 * @uses html_api.php
 * @uses lang_api.php
 */

require_once( 'core.php' );
require_api( 'authentication_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );

form_security_validate( 'manage_user_merge' );

auth_reauthenticate();

$f_user_id = gpc_get_int( 'user_id' );
$f_target_id = gpc_get_int( 'target_id' );

# The command checks who may merge which users
$t_command = new UserMergeCommand( array( 'query' => array( 'id' => $f_user_id, 'target_id' => $f_target_id ) ) );
$t_command->execute();

form_security_purge( 'manage_user_merge' );

$t_redirect_url = 'manage_user_edit_page.php?user_id=' . $f_target_id;

layout_page_header( null, $t_redirect_url );
layout_page_begin( 'manage_overview_page.php' );

html_operation_successful( $t_redirect_url, lang_get( 'merge_user_done' ) );

layout_page_end();
