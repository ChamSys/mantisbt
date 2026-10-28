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
 * Theme API
 *
 * A theme is a set of stylesheets that is loaded after the ones of MantisBT,
 * so it can restyle the pages, and an id that is set on the page as the
 * data-theme attribute of the html tag, for CSS selectors. Themes are provided
 * by plugins, with the EVENT_THEMES event, and selected with $g_theme.
 *
 * @package CoreAPI
 * @subpackage ThemeAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses config_api.php
 * @uses event_api.php
 * @uses html_api.php
 * @uses lang_api.php
 */

require_api( 'config_api.php' );
require_api( 'event_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );

/**
 * The id of the theme that has no stylesheets of its own.
 */
define( 'THEME_DEFAULT', 'default' );

/**
 * Get the themes that can be used.
 *
 * Plugins provide them with the EVENT_THEMES event. The callback returns an
 * array of themes by theme id (letters, digits, hyphens and underscores). Each
 * theme is an array with:
 * - 'name': the name to show to users,
 * - 'css': the stylesheets to load, as paths or URLs, in order,
 * - 'color_schemes': optionally, the color schemes the theme styles, 'light'
 *   and/or 'dark'. Users of a theme that has them can choose between
 *   them and the automatic one, which follows their system, see
 *   theme_get_color_scheme().
 *
 * @return array The themes by id, starting with the default one.
 * @access public
 */
function theme_get_all() {
	static $s_themes = null;

	if( $s_themes === null ) {
		$s_themes = array( THEME_DEFAULT => array( 'name' => lang_get( 'theme_default' ), 'css' => array(), 'color_schemes' => array() ) );

		foreach( event_signal( 'EVENT_THEMES' ) as $t_plugin_results ) {
			foreach( $t_plugin_results as $t_result ) {
				if( !is_array( $t_result ) ) {
					continue;
				}
				foreach( $t_result as $t_id => $t_theme ) {
					$t_id = (string)$t_id;
					if( !preg_match( '/^[A-Za-z0-9_-]+$/', $t_id ) || $t_id === THEME_DEFAULT || !is_array( $t_theme ) ) {
						continue;
					}
					$s_themes[$t_id] = array(
						'name' => isset( $t_theme['name'] ) ? (string)$t_theme['name'] : $t_id,
						'css' => isset( $t_theme['css'] ) ? array_values( (array)$t_theme['css'] ) : array(),
						'color_schemes' => isset( $t_theme['color_schemes'] )
							? array_values( array_intersect( (array)$t_theme['color_schemes'], array( 'light', 'dark' ) ) )
							: array(),
					);
				}
			}
		}
	}

	return $s_themes;
}

/**
 * Get the id of the theme in use: the one set in $g_theme, if there is such a theme.
 *
 * @return string The theme id.
 * @access public
 */
function theme_get_current() {
	$t_id = (string)config_get( 'theme', null, null, ALL_PROJECTS );

	return array_key_exists( $t_id, theme_get_all() ) ? $t_id : THEME_DEFAULT;
}

/**
 * Print the links to the stylesheets of the theme in use.
 *
 * @return void
 * @access public
 */
function theme_print_css() {
	$t_themes = theme_get_all();

	foreach( $t_themes[theme_get_current()]['css'] as $t_stylesheet ) {
		if( preg_match( '#^https?://#i', $t_stylesheet ) ) {
			html_css_cdn_link( string_attribute( $t_stylesheet ) );
		} else {
			html_css_link( $t_stylesheet );
		}
	}
}

/**
 * Check if any of the themes styles more than one color scheme, so that users
 * can choose one.
 *
 * @return bool
 * @access public
 */
function theme_has_color_schemes() {
	foreach( theme_get_all() as $t_theme ) {
		if( !empty( $t_theme['color_schemes'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Get the color scheme to use, from $g_color_scheme ('auto', 'light' or 'dark').
 *
 * @return string The color scheme, or an empty string if the theme in use does
 *                not style any color scheme.
 * @access public
 */
function theme_get_color_scheme() {
	$t_themes = theme_get_all();
	if( empty( $t_themes[theme_get_current()]['color_schemes'] ) ) {
		return '';
	}

	$t_scheme = (string)config_get( 'color_scheme', null, null, ALL_PROJECTS );

	return in_array( $t_scheme, array( 'light', 'dark' ), true ) ? $t_scheme : 'auto';
}

/**
 * Print the meta tag that tells the browser which color schemes the page
 * supports, so that form controls and scrollbars match.
 *
 * @return void
 * @access public
 */
function theme_print_meta() {
	$t_scheme = theme_get_color_scheme();
	if( $t_scheme === '' ) {
		return;
	}

	$t_themes = theme_get_all();
	$t_supported = $t_themes[theme_get_current()]['color_schemes'];
	$t_content = ( $t_scheme === 'auto' ) ? implode( ' ', $t_supported ) : $t_scheme;

	echo "\t", '<meta name="color-scheme" content="', $t_content, '">', "\n";
}

/**
 * Get the attributes that the theme in use sets on the html tag: the theme id
 * and the color scheme.
 *
 * @return array Attribute values by attribute name.
 * @access public
 */
function theme_get_html_attributes() {
	$t_id = theme_get_current();

	$t_attributes = array();
	if( $t_id !== THEME_DEFAULT ) {
		$t_attributes['data-theme'] = $t_id;
	}
	$t_scheme = theme_get_color_scheme();
	if( $t_scheme !== '' ) {
		$t_attributes['data-color-scheme'] = $t_scheme;
	}

	return $t_attributes;
}
