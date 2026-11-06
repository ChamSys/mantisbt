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
 * Save the text of an issue edited by the user who reported it.
 *
 * CALLERS
 *	This page is submitted to by the following pages:
 *	- bug_edit_text_page.php
 *
 * EXPECTED BEHAVIOUR
 *	Replace the description, the steps to reproduce and the additional
 *	information of the issue. The previous text is kept as a revision, and
 *	the usual update events and notifications are sent. Nothing else of the
 *	issue is read from the form, so this page cannot be used to change the
 *	status, the handler or any other field.
 *
 * RESTRICTIONS & PERMISSIONS
 *	- the user must be the reporter of the issue and have
 *	  $g_bug_user_edit_threshold, see access_can_edit_bug_text()
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses access_api.php
 * @uses bug_api.php
 * @uses constant_inc.php
 * @uses email_api.php
 * @uses error_api.php
 * @uses event_api.php
 * @uses form_api.php
 * @uses gpc_api.php
 * @uses helper_api.php
 * @uses print_api.php
 */

require_once( 'core.php' );
require_api( 'access_api.php' );
require_api( 'bug_api.php' );
require_api( 'constant_inc.php' );
require_api( 'email_api.php' );
require_api( 'error_api.php' );
require_api( 'event_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'helper_api.php' );
require_api( 'print_api.php' );

form_security_validate( 'bug_edit_text' );

$f_bug_id = gpc_get_int( 'bug_id' );
$t_existing_bug = bug_get( $f_bug_id, true );

if( helper_get_current_project() != $t_existing_bug->project_id ) {
	$g_project_override = $t_existing_bug->project_id;
}

if( !access_can_edit_bug_text( $f_bug_id ) ) {
	access_denied();
}

# Somebody changed the issue since the form was shown
if( $t_existing_bug->last_updated != gpc_get_string( 'last_updated' ) ) {
	trigger_error( ERROR_BUG_CONFLICTING_EDIT, ERROR );
}

# Only these three fields are read, whatever else the form carries
$t_updated_bug = clone $t_existing_bug;
$t_updated_bug->description = gpc_get_string( 'description', $t_existing_bug->description );
$t_updated_bug->steps_to_reproduce = gpc_get_string( 'steps_to_reproduce', $t_existing_bug->steps_to_reproduce );
$t_updated_bug->additional_information = gpc_get_string( 'additional_information', $t_existing_bug->additional_information );

$t_text_changed = ( $t_existing_bug->description != $t_updated_bug->description ) ||
	( $t_existing_bug->steps_to_reproduce != $t_updated_bug->steps_to_reproduce ) ||
	( $t_existing_bug->additional_information != $t_updated_bug->additional_information );

if( $t_text_changed ) {
	# Allow plugins to validate/modify the update prior to it being committed.
	$t_updated_bug = event_signal( 'EVENT_UPDATE_BUG_DATA', $t_updated_bug, $t_existing_bug );

	# Validates the text, stores the revisions and writes the history
	$t_updated_bug->update( true, true );

	event_signal( 'EVENT_UPDATE_BUG', array( $t_existing_bug, $t_updated_bug ) );
	email_bug_updated( $f_bug_id );
}

form_security_purge( 'bug_edit_text' );

print_header_redirect_view( $f_bug_id );
