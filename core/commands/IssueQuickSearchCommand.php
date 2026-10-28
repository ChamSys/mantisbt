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
require_api( 'bug_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'current_user_api.php' );
require_api( 'filter_api.php' );
require_api( 'filter_constants_inc.php' );
require_api( 'helper_api.php' );
require_api( 'project_api.php' );
require_api( 'search_api.php' );
require_api( 'tokens_api.php' );
require_api( 'user_api.php' );

use Mantis\Exceptions\ClientException;

/**
 * A command that finds the issues that best match some text, to suggest them as
 * the user types. It uses the search backend, so it is only available when that
 * has an index (see $g_search_live_enabled), and the issues are the ones the
 * text search of the issue list would give the user: access rules, private
 * issues and private notes are applied by the filter query, not by the backend.
 */
class IssueQuickSearchCommand extends Command {
	/**
	 * The most issues that are returned.
	 */
	const MAX_RESULTS = 10;

	/**
	 * @var string The text to search for.
	 */
	private $text;

	/**
	 * @var integer The project to search in, ALL_PROJECTS for all the ones the user can see.
	 */
	private $projectId;

	/**
	 * @var boolean Whether to include the issues with a status that is hidden by default.
	 */
	private $includeClosed;

	/**
	 * @var integer The number of issues to return.
	 */
	private $limit;

	/**
	 * $p_data['query'] is expected to contain:
	 * - q: the text, 2 to 255 characters
	 * - project_id: optional, the project (default the current one, 0 for all)
	 * - include_closed: optional, 1 to include the issues with a status hidden by default
	 * - limit: optional, the number of issues, up to MAX_RESULTS
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
		if( ON != config_get( 'search_live_enabled' ) || !search_backend_get()->has_index() ) {
			throw new ClientException( 'Quick search is not available', ERROR_ACCESS_DENIED );
		}

		if( current_user_is_anonymous() ) {
			throw new ClientException( 'Access denied', ERROR_ACCESS_DENIED );
		}

		$this->text = trim( (string)$this->query( 'q', '' ) );
		if( mb_strlen( $this->text ) < 2 || mb_strlen( $this->text ) > 255 ) {
			throw new ClientException( "'q' must have 2 to 255 characters", ERROR_INVALID_FIELD_VALUE, array( 'q' ) );
		}

		$this->projectId = (int)$this->query( 'project_id', helper_get_current_project() );
		if( $this->projectId != ALL_PROJECTS
			&& ( !project_exists( $this->projectId ) || !access_has_project_level( VIEWER, $this->projectId ) ) ) {
			# The same answer whether the project exists or not
			throw new ClientException( 'Project not found', ERROR_PROJECT_NOT_FOUND, array( $this->projectId ) );
		}

		$this->includeClosed = (bool)$this->query( 'include_closed', 0 );
		$this->limit = max( 1, min( self::MAX_RESULTS, (int)$this->query( 'limit', self::MAX_RESULTS ) ) );

		if( !token_rate_limit_allow( TOKEN_RATE_ISSUE_SEARCH, (int)config_get_global( 'search_live_rate_limit' ) ) ) {
			throw new ClientException( 'Too many searches, try again in a moment', ERROR_SPAM_SUSPECTED );
		}
	}

	/**
	 * Process the command.
	 *
	 * @return array Command response: results, and more (whether there were more matches than returned).
	 */
	protected function process() {
		$t_filter = filter_get_default();
		$t_filter[FILTER_PROPERTY_SEARCH] = $this->text;
		if( $this->includeClosed ) {
			$t_filter[FILTER_PROPERTY_HIDE_STATUS] = array( META_FILTER_NONE );
		}

		$t_query = new BugFilterQuery( $t_filter, array(
			'query_type' => BugFilterQuery::QUERY_TYPE_IDS,
			'project_id' => $this->projectId,
		) );
		$t_ids = array_map( 'intval', array_column( $t_query->fetch_all(), 'id' ) );

		# Best match first: the query returns the issues in the order of the filter
		$t_rank = array_flip( $t_query->get_search_candidate_ids() ?: array() );
		usort( $t_ids, function( $p_a, $p_b ) use ( $t_rank ) {
			return ( $t_rank[$p_a] ?? PHP_INT_MAX ) <=> ( $t_rank[$p_b] ?? PHP_INT_MAX );
		} );

		$t_results = array();
		foreach( array_slice( $t_ids, 0, $this->limit ) as $t_id ) {
			$t_bug = bug_get( $t_id );
			$t_results[] = array(
				'id' => $t_id,
				'id_formatted' => bug_format_id( $t_id ),
				'summary' => $t_bug->summary,
				'status' => array(
					'id' => (int)$t_bug->status,
					'label' => get_enum_element( 'status', $t_bug->status, auth_get_current_user_id(), $t_bug->project_id ),
				),
				'project' => array( 'id' => (int)$t_bug->project_id, 'name' => project_get_name( $t_bug->project_id ) ),
				'updated_at' => (int)$t_bug->last_updated,
			);
		}

		return array( 'results' => $t_results, 'more' => count( $t_ids ) > $this->limit );
	}
}
