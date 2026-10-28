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
 * Loads plugin files
 * @package MantisBT
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses gpc_api.php
 * @uses plugin_api.php
 */

$g_bypass_headers = true;
header( 'Content-type: ' );

const DISABLE_INLINE_ERROR_REPORTING = true;

# Static files do not need the database, the plugins, a session or a user:
# serve them without starting any of those, which also sets no cookie.
define( 'MANTIS_MAINTENANCE_MODE', true );

require_once( 'core.php' );

# Loading the APIs starts a session, but a static file does not need its cookie
header_remove( 'Set-Cookie' );

require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'gpc_api.php' );
require_api( 'plugin_api.php' );

$t_plugin_path = config_get_global( 'plugin_path' );

$f_file = gpc_get_string( 'file' );

$t_regex = '/^'
	# File must start with plugin name, ending with /
	. '([a-zA-Z0-9_-]+)\/'
	# Path must not start with a '.' to avoid arbitrary includes higher in the file system
	. '('. '(?:(?:[a-zA-Z0-9_-][.a-zA-Z0-9_-]*\/)*)'
	# Same goes for filename
	. '(?:[a-zA-Z0-9_-][.a-zA-Z0-9_-]*)'
	. ')$/';

if( !preg_match( $t_regex, $f_file, $t_matches ) ) {
	error_parameters( $f_file );
	trigger_error( ERROR_PLUGIN_INVALID_FILE, ERROR );
}

$t_basename = $t_matches[1];
$t_file = $t_matches[2];

# The plugins are not loaded here, so check that the plugin is there, not that it is installed
if( !is_dir( $t_plugin_path . $t_basename ) ) {
	error_parameters( $t_basename, $t_file );
	trigger_error( ERROR_PLUGIN_FILE_NOT_FOUND, ERROR );
}

plugin_file_include( $t_file, $t_basename );
