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

require_api( 'authentication_api.php' );
require_api( 'bug_api.php' );
require_api( 'constant_inc.php' );
require_api( 'config_api.php' );
require_api( 'helper_api.php' );
require_api( 'like_api.php' );

use Mantis\Exceptions\ClientException;

/**
 * A command that gets the likes of an issue, as far as the current user may see them.
 */
class IssueLikesGetCommand extends Command {
	/**
	 * @var integer The issue to get the likes of.
	 */
	private $issueId;

	/**
	 * $p_data['query'] is expected to contain:
	 * - issue_id
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
		$this->issueId = helper_parse_issue_id( $this->query( 'issue_id' ) );

		# Who can see the issue can see its likes
		access_ensure_bug_level( config_get( 'view_bug_threshold', null, null, bug_get_field( $this->issueId, 'project_id' ) ), $this->issueId );
	}

	/**
	 * Process the command.
	 *
	 * @return array Command response
	 */
	protected function process() {
		$t_project_id = bug_get_field( $this->issueId, 'project_id' );
		if( $t_project_id != helper_get_current_project() ) {
			# in case the current project is not the same project of the bug we are
			# viewing, override the current project.
			global $g_project_override;
			$g_project_override = $t_project_id;
		}

		return like_get_info( $this->issueId );
	}
}
