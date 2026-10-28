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

require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'constant_inc.php' );
require_api( 'config_api.php' );
require_api( 'helper_api.php' );
require_api( 'user_api.php' );
require_api( 'user_merge_api.php' );

use Mantis\Exceptions\ClientException;

/**
 * A command that merges a user into another one: everything the first user did
 * and owns is given to the second, and the first is deleted. See user_merge().
 *
 * Sample:
 * {
 *   "query": {
 *      "id": 1234,
 *      "target_id": 5678
 *   }
 * }
 */
class UserMergeCommand extends Command {
	/**
	 * @var integer The id of the user to merge: it is deleted.
	 */
	private $source_user_id;

	/**
	 * @var integer The id of the user that gets everything.
	 */
	private $target_user_id;

	/**
	 * Constructor
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
		$this->source_user_id = (int)$this->query( 'id', null );
		if( $this->source_user_id <= 0 ) {
			throw new ClientException( 'Invalid user id', ERROR_INVALID_FIELD_VALUE, array( 'id' ) );
		}

		$this->target_user_id = (int)$this->query( 'target_id', null );
		if( $this->target_user_id <= 0 ) {
			throw new ClientException( 'Invalid target user id', ERROR_INVALID_FIELD_VALUE, array( 'target_id' ) );
		}

		user_ensure_exists( $this->source_user_id );
		user_ensure_exists( $this->target_user_id );

		if( $this->source_user_id == $this->target_user_id ) {
			throw new ClientException( 'A user cannot be merged into itself', ERROR_INVALID_FIELD_VALUE, array( 'target_id' ) );
		}

		# As with the deletion of a user, which a merge does, the own account is not merged
		if( $this->source_user_id == auth_get_current_user_id() ) {
			throw new ClientException( 'Merging own account not allowed', ERROR_INVALID_FIELD_VALUE, array( 'id' ) );
		}

		if( !access_has_global_level( config_get_global( 'manage_user_threshold' ) ) ) {
			throw new ClientException( 'Access denied to merge users', ERROR_ACCESS_DENIED );
		}

		# Both accounts must be of equal or lower access to the current user: the one that
		# is deleted, and the one that gets what the other had.
		foreach( array( $this->source_user_id, $this->target_user_id ) as $t_user_id ) {
			if( !access_has_global_level( user_get_field( $t_user_id, 'access_level' ) ) ) {
				throw new ClientException( 'Access denied to merge users with higher access level', ERROR_ACCESS_DENIED );
			}
		}

		# Check that we are not deleting the last administrator account
		$t_admin_threshold = config_get_global( 'admin_site_threshold' );
		if( user_is_administrator( $this->source_user_id ) &&
			user_count_level( $t_admin_threshold, /* enabled */ true ) <= 1 ) {
			throw new ClientException(
				'Deleting last administrator not allowed',
				ERROR_USER_CHANGE_LAST_ADMIN );
		}
	}

	/**
	 * Process the command.
	 *
	 * @return array Command response: for each table and column, the number of rows that were given to the other user.
	 */
	protected function process() {
		return array( 'given' => user_merge( $this->source_user_id, $this->target_user_id ) );
	}
}
