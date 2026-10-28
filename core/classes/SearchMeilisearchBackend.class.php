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

require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'error_api.php' );
require_once( __DIR__ . '/SearchBackend.class.php' );

/**
 * A search backend that keeps its index in Meilisearch (https://www.meilisearch.com).
 *
 * Meilisearch finds whole words and word prefixes, tolerates typos and ranks
 * the results by relevance. It is reached over HTTP with cURL, using the
 * $g_search_meilisearch_* settings.
 *
 * The index has one document per issue (see search_issue_documents()). Only
 * the issue id is ever returned by a search, never any text.
 *
 * @package MantisBT
 * @subpackage Classes
 */
class SearchMeilisearchBackend extends SearchBackend {
	/**
	 * The fields searched for every user, in order of importance.
	 */
	const PUBLIC_FIELDS = array(
		'summary', 'category', 'tags', 'description', 'steps_to_reproduce',
		'additional_information', 'notes',
	);

	/**
	 * The most results Meilisearch returns for a search (its default
	 * pagination.maxTotalHits).
	 */
	const MAX_RESULTS = 1000;

	/**
	 * @return string
	 */
	public function get_name() {
		return 'meilisearch';
	}

	/**
	 * @return bool
	 */
	public function has_index() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function is_available() {
		list( $t_status, $t_body ) = $this->request( 'GET', '/health' );
		return $t_status == 200 && isset( $t_body['status'] ) && $t_body['status'] == 'available';
	}

	/**
	 * Create the index if needed and apply its settings.
	 *
	 * @return bool true on success
	 */
	public function ensure_index() {
		list( $t_status, $t_body ) = $this->request( 'GET', $this->index_path( '' ) );
		if( $t_status == 404 ) {
			list( $t_status, $t_body ) = $this->request( 'POST', '/indexes', array(
				'uid' => $this->get_index_name(),
				'primaryKey' => 'id',
			) );
			if( !$this->wait_for_task( $t_status, $t_body ) ) {
				return false;
			}
		} elseif( $t_status != 200 ) {
			$this->log_failure( 'request', $t_status, $t_body );
			return false;
		}

		list( $t_status, $t_body ) = $this->request( 'PATCH', $this->index_path( '/settings' ), array(
			'searchableAttributes' => array_merge( self::PUBLIC_FIELDS, array( 'private_notes' ) ),
			'filterableAttributes' => array( 'project_id' ),
			# Searches only need ids: the text is never sent back.
			'displayedAttributes' => array( 'id' ),
		) );
		return $this->wait_for_task( $t_status, $t_body );
	}

	/**
	 * @param array $p_documents The documents to index.
	 * @return bool true on success
	 */
	public function index_issues( array $p_documents ) {
		if( empty( $p_documents ) ) {
			return true;
		}
		list( $t_status, $t_body ) = $this->request( 'POST', $this->index_path( '/documents' ), array_values( $p_documents ) );
		return $this->wait_for_task( $t_status, $t_body );
	}

	/**
	 * @param array $p_bug_ids The ids of the issues to remove.
	 * @return bool true on success
	 */
	public function remove_issues( array $p_bug_ids ) {
		if( empty( $p_bug_ids ) ) {
			return true;
		}
		list( $t_status, $t_body ) = $this->request( 'POST', $this->index_path( '/documents/delete-batch' ),
			array_map( 'intval', array_values( $p_bug_ids ) ) );
		return $this->wait_for_task( $t_status, $t_body );
	}

	/**
	 * @return bool true on success
	 */
	public function clear_index() {
		list( $t_status, $t_body ) = $this->request( 'DELETE', $this->index_path( '/documents' ) );
		return $this->wait_for_task( $t_status, $t_body );
	}

	/**
	 * Find the issues that match a search text.
	 *
	 * Text in private notes is only searched in the projects listed in the
	 * 'private_note_project_ids' option, so a user cannot find out what the
	 * private notes of other projects say.
	 *
	 * @param string $p_text    The text to search for.
	 * @param array  $p_options See SearchBackend::search().
	 *
	 * @return int[]|null The ids of the matching issues, or null on failure.
	 */
	public function search( $p_text, array $p_options ) {
		$t_limit = min( self::MAX_RESULTS, max( 1, (int)$p_options['limit'] ) );
		$t_index = $this->get_index_name();

		$t_queries = array( array(
			'indexUid' => $t_index,
			'q' => $p_text,
			'limit' => $t_limit,
			'attributesToSearchOn' => self::PUBLIC_FIELDS,
			'attributesToRetrieve' => array( 'id' ),
		) );

		$t_project_ids = array_values( array_unique( array_map( 'intval', $p_options['private_note_project_ids'] ) ) );
		if( !empty( $t_project_ids ) ) {
			$t_queries[] = array(
				'indexUid' => $t_index,
				'q' => $p_text,
				'limit' => $t_limit,
				'attributesToSearchOn' => array_merge( self::PUBLIC_FIELDS, array( 'private_notes' ) ),
				'attributesToRetrieve' => array( 'id' ),
				'filter' => 'project_id IN [' . implode( ', ', $t_project_ids ) . ']',
			);
		}

		list( $t_status, $t_body ) = $this->request( 'POST', '/multi-search', array( 'queries' => $t_queries ) );
		if( $t_status != 200 || !isset( $t_body['results'] ) || count( $t_body['results'] ) != count( $t_queries ) ) {
			$this->log_failure( 'search', $t_status, $t_body );
			return null;
		}

		# The first query is the same for everybody, the second one adds the
		# private notes of the projects where they may be read.
		$t_ids = array();
		foreach( $t_body['results'] as $t_result ) {
			foreach( $t_result['hits'] as $t_hit ) {
				$t_ids[(int)$t_hit['id']] = true;
			}
		}
		return array_slice( array_keys( $t_ids ), 0, $t_limit );
	}

	/**
	 * @return string The name of the Meilisearch index.
	 */
	protected function get_index_name() {
		$t_index = config_get_global( 'search_meilisearch_index' );
		if( !preg_match( '/^[A-Za-z0-9_-]+$/', $t_index ) ) {
			error_parameters( 'search_meilisearch_index', $t_index );
			trigger_error( ERROR_CONFIG_OPT_INVALID, ERROR );
		}
		return $t_index;
	}

	/**
	 * @param string $p_path A path below the index, for example '/documents'.
	 * @return string
	 */
	protected function index_path( $p_path ) {
		return '/indexes/' . $this->get_index_name() . $p_path;
	}

	/**
	 * Send a request to Meilisearch.
	 *
	 * @param string     $p_method The HTTP method.
	 * @param string     $p_path   The path of the API endpoint.
	 * @param array|null $p_body   The data to send as JSON, if any.
	 *
	 * @return array The HTTP status code (0 if there was no answer) and the
	 *               decoded response.
	 */
	protected function request( $p_method, $p_path, $p_body = null ) {
		$t_url = rtrim( config_get_global( 'search_meilisearch_url' ), '/' ) . $p_path;
		$t_timeout = max( 1, (int)config_get_global( 'search_meilisearch_timeout' ) );

		$t_headers = array( 'Content-Type: application/json' );
		$t_key = config_get_global( 'search_meilisearch_key' );
		if( !is_blank( $t_key ) ) {
			$t_headers[] = 'Authorization: Bearer ' . $t_key;
		}

		$t_curl = curl_init( $t_url );
		curl_setopt_array( $t_curl, array(
			CURLOPT_CUSTOMREQUEST => $p_method,
			CURLOPT_HTTPHEADER => $t_headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $t_timeout,
			CURLOPT_CONNECTTIMEOUT => min( 5, $t_timeout ),
			CURLOPT_USERAGENT => 'mantisbt/' . MANTIS_VERSION . ' php-curl',
		) );
		if( $p_body !== null ) {
			curl_setopt( $t_curl, CURLOPT_POSTFIELDS, json_encode( $p_body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}

		$t_response = curl_exec( $t_curl );
		$t_status = (int)curl_getinfo( $t_curl, CURLINFO_RESPONSE_CODE );
		$t_error = curl_error( $t_curl );

		if( $t_response === false ) {
			return array( 0, array( 'message' => $t_error ) );
		}
		$t_decoded = json_decode( $t_response, true );
		return array( $t_status, is_array( $t_decoded ) ? $t_decoded : array() );
	}

	/**
	 * Wait for the task Meilisearch started for a request to finish, as the
	 * index is changed in the background.
	 *
	 * @param integer $p_status The HTTP status of the request that started the task.
	 * @param array   $p_body   The response to that request.
	 *
	 * @return bool true if the task succeeded
	 */
	protected function wait_for_task( $p_status, array $p_body ) {
		if( $p_status != 202 || !isset( $p_body['taskUid'] ) ) {
			$this->log_failure( 'request', $p_status, $p_body );
			return false;
		}

		$t_deadline = time() + max( 1, (int)config_get_global( 'search_meilisearch_timeout' ) );
		do {
			list( $t_status, $t_task ) = $this->request( 'GET', '/tasks/' . (int)$p_body['taskUid'] );
			if( $t_status == 200 && isset( $t_task['status'] ) ) {
				if( $t_task['status'] == 'succeeded' ) {
					return true;
				}
				if( $t_task['status'] == 'failed' || $t_task['status'] == 'canceled' ) {
					$this->log_failure( 'task', $t_status, $t_task['error'] ?? $t_task );
					return false;
				}
			}
			usleep( 100000 );
		} while( time() < $t_deadline );

		$this->log_failure( 'task did not finish in time', $p_status, $p_body );
		return false;
	}

	/**
	 * Record a failure in the server log.
	 *
	 * @param string $p_what   What failed.
	 * @param int    $p_status The HTTP status.
	 * @param array  $p_body   The response.
	 * @return void
	 */
	protected function log_failure( $p_what, $p_status, $p_body ) {
		error_log( 'MantisBT Meilisearch ' . $p_what . ' failed (HTTP ' . $p_status . '): ' . json_encode( $p_body ) );
	}
}
