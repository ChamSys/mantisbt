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
 * Search API
 *
 * @package CoreAPI
 * @subpackage SearchAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses error_api.php
 */

require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'error_api.php' );

require_once( __DIR__ . '/classes/SearchBackend.class.php' );

/**
 * Get the search backend selected with $g_search_backend.
 *
 * @return SearchBackend The backend instance; the same one on every call.
 * @access public
 */
function search_backend_get() {
	static $s_backend = null;

	if( $s_backend === null ) {
		$t_name = config_get_global( 'search_backend' );

		# The name ends up in a file name, so only accept plain names.
		$t_class = 'Search' . ucfirst( $t_name ) . 'Backend';
		$t_file = __DIR__ . '/classes/' . $t_class . '.class.php';
		if( !preg_match( '/^[a-z]+$/', $t_name ) || !is_file( $t_file ) ) {
			error_parameters( 'search_backend', $t_name );
			trigger_error( ERROR_CONFIG_OPT_INVALID, ERROR );
		}

		require_once( $t_file );
		$s_backend = new $t_class();
	}

	return $s_backend;
}
