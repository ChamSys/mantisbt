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
 * @noinspection PhpUnhandledExceptionInspection
 */

require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'current_user_api.php' );
require_api( 'helper_api.php' );
require_api( 'project_api.php' );
require_api( 'tokens_api.php' );
require_api( 'user_api.php' );
require_api( 'user_picker_api.php' );

use Mantis\Exceptions\ClientException;

/**
 * A command that searches for the users a field may offer, for choosing one as
 * the user types. It only returns users that the drop-down of the same field would
 * have listed for the user: the scope gives the access level of the users and
 * the one needed to see them, never the caller, the list is limited like the
 * drop-downs are (see $g_show_user_list_threshold), and neither email addresses nor
 * real names that are not shown elsewhere are returned or searched.
 */
class UserSearchCommand extends Command {
	/**
	 * The most users that are returned.
	 */
	const MAX_RESULTS = 20;

	/**
	 * @var array The scope, see user_typeahead_get_scope().
	 */
	private $scope;

	/**
	 * @var string The text to search for.
	 */
	private $text;

	/**
	 * @var integer The project, ALL_PROJECTS for all those the user can access.
	 */
	private $projectId;

	/**
	 * @var integer The number of users to return.
	 */
	private $limit;

	/**
	 * $p_data['query'] is expected to contain:
	 * - scope: reporter, handler, monitor or note
	 * - q: the text, 2 to 50 characters
	 * - project_id: optional, the project (default the current one, 0 for all)
	 * - limit: optional, the number of users, up to MAX_RESULTS
	 *
	 * @param array $p_data The command data.
	 */
	function __construct( array $p_data ) {
		parent::__construct( $p_data );
	}

	/**
	 * Validate the data.
	 */
	function validate() {
		if( current_user_is_anonymous() ) {
			throw new ClientException( 'Access denied', ERROR_ACCESS_DENIED );
		}

		$this->scope = user_typeahead_get_scope( (string)$this->query( 'scope', '' ) );
		if( $this->scope === null ) {
			throw new ClientException( "Invalid 'scope'", ERROR_INVALID_FIELD_VALUE, array( 'scope' ) );
		}

		$this->text = trim( (string)$this->query( 'q', '' ) );
		if( mb_strlen( $this->text ) < 2 || mb_strlen( $this->text ) > 50 ) {
			throw new ClientException( "'q' must have 2 to 50 characters", ERROR_INVALID_FIELD_VALUE, array( 'q' ) );
		}

		$this->projectId = (int)$this->query( 'project_id', helper_get_current_project() );
		if( $this->projectId != ALL_PROJECTS
			&& ( !project_exists( $this->projectId ) || !access_has_project_level( VIEWER, $this->projectId ) ) ) {
			# The same answer whether the project exists or not
			throw new ClientException( 'Project not found', ERROR_PROJECT_NOT_FOUND, array( $this->projectId ) );
		}

		# The same access that is needed to see the users in the field
		if( $this->scope['gate'] !== null ) {
			$t_gate = config_get( $this->scope['gate'], null, null, $this->projectId );
			if( $this->projectId == ALL_PROJECTS ? !access_has_any_project_level( $t_gate ) : !access_has_project_level( $t_gate, $this->projectId ) ) {
				throw new ClientException( 'Access denied', ERROR_ACCESS_DENIED );
			}
		}

		$this->limit = max( 1, min( self::MAX_RESULTS, (int)$this->query( 'limit', self::MAX_RESULTS ) ) );

		if( !token_rate_limit_allow( TOKEN_RATE_USER_SEARCH, (int)config_get_global( 'user_typeahead_rate_limit' ) ) ) {
			throw new ClientException( 'Too many searches, try again in a moment', ERROR_SPAM_SUSPECTED );
		}
	}

	/**
	 * Process the command.
	 *
	 * @return array Command response: users, and more (whether there were more matches than returned).
	 */
	protected function process() {
		$t_access = config_get( $this->scope['access'], null, null, $this->projectId );
		$t_rows = user_get_option_rows( $this->projectId, $t_access, $this->text );

		# Users with a limited view only see themselves as reporters, as in the filter
		if( $this->scope['access'] == 'report_bug_threshold' && access_has_limited_view( $this->projectId ) ) {
			$t_user_id = auth_get_current_user_id();
			$t_rows = isset( $t_rows[$t_user_id] ) ? array( $t_user_id => $t_rows[$t_user_id] ) : array();
		}

		# Best match first: the user name that is the text, then one that starts with it, then the rest
		$t_text = mb_strtolower( $this->text );
		$t_ranked = array();
		foreach( $t_rows as $t_row ) {
			$t_username = mb_strtolower( $t_row['username'] );
			$t_rank = ( $t_username === $t_text ) ? 0 : ( strpos( $t_username, $t_text ) === 0 ? 1 : 2 );
			$t_ranked[] = array( $t_rank, user_get_name_for_sorting_from_row( $t_row ), $t_row );
		}
		usort( $t_ranked, function( $p_a, $p_b ) {
			return array( $p_a[0], $p_a[1] ) <=> array( $p_b[0], $p_b[1] );
		} );

		$t_users = array();
		foreach( array_slice( $t_ranked, 0, $this->limit ) as $t_entry ) {
			$t_row = $t_entry[2];
			$t_users[] = array(
				'id' => (int)$t_row['id'],
				'name' => user_get_name_from_row( $t_row ),
				'username' => $t_row['username'],
				'label' => user_get_expanded_name_from_row( $t_row ),
			);
		}

		return array( 'users' => $t_users, 'more' => count( $t_ranked ) > $this->limit );
	}
}
