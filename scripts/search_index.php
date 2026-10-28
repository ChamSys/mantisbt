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
 * Script to keep the index of the search backend up to date.
 *
 * Run it regularly (for example every minute) from cron, like send_emails.php:
 *
 *   php scripts/search_index.php            update the index with the issues that changed
 *   php scripts/search_index.php --setup    create the index, or update its settings
 *   php scripts/search_index.php --reindex  rebuild the index from all the issues
 *   php scripts/search_index.php --status   show the backend and the queue
 *
 * It does nothing for search backends that have no index, such as the
 * default 'database' one, see $g_search_backend.
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 */

/**
 * Global Bypass http headers
 */
global $g_bypass_headers;
$g_bypass_headers = 1;

require_once( dirname( __DIR__ ) . '/core.php' );

require_api( 'search_api.php' );

# Make sure this script doesn't run via the webserver
if( php_sapi_name() != 'cli' ) {
	echo "search_index.php is not allowed to run through the webserver.\n";
	exit( 1 );
}

$t_options = getopt( '', array( 'setup', 'reindex', 'status', 'help' ) );
if( isset( $t_options['help'] ) ) {
	echo "Usage: php scripts/search_index.php [--setup | --reindex | --status]\n";
	exit( 0 );
}

$t_backend = search_backend_get();
if( !$t_backend->has_index() ) {
	echo "The '" . $t_backend->get_name() . "' search backend has no index, there is nothing to do.\n";
	exit( 0 );
}

if( isset( $t_options['status'] ) ) {
	echo 'Search backend: ' . $t_backend->get_name() . "\n";
	echo 'Available: ' . ( $t_backend->is_available() ? 'yes' : 'no' ) . "\n";
	echo 'Issues waiting to be indexed: ' . search_queue_count() . "\n";
	exit( 0 );
}

if( isset( $t_options['setup'] ) || isset( $t_options['reindex'] ) ) {
	echo "Preparing the index...\n";
	if( !$t_backend->ensure_index() ) {
		echo "Could not prepare the index, see the server log.\n";
		exit( 1 );
	}
}

if( isset( $t_options['reindex'] ) ) {
	echo "Rebuilding the index...\n";
	if( !$t_backend->clear_index() ) {
		echo "Could not clear the index, see the server log.\n";
		exit( 1 );
	}
	search_queue_add_all();
} elseif( isset( $t_options['setup'] ) ) {
	echo "Done.\n";
	exit( 0 );
}

$t_total = 0;
while( search_queue_count() > 0 ) {
	$t_count = search_process_queue();
	if( $t_count === false ) {
		echo "Could not update the index, see the server log. Issues indexed so far: $t_total.\n";
		exit( 1 );
	}
	$t_total += $t_count;
}

echo "Issues indexed: $t_total.\n";
exit( 0 );
