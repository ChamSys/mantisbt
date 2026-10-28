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
 * Merge a user into another user: choose the other user, then confirm.
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses access_api.php
 * @uses authentication_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses form_api.php
 * @uses gpc_api.php
 * @uses html_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses string_api.php
 * @uses user_api.php
 * @uses user_merge_api.php
 */

require_once( 'core.php' );
require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );
require_api( 'user_api.php' );
require_api( 'user_merge_api.php' );

auth_reauthenticate();

access_ensure_global_level( config_get( 'manage_user_threshold' ) );

$f_user_id = gpc_get_int( 'user_id' );
$f_target = trim( gpc_get_string( 'target', '' ) );

user_ensure_exists( $f_user_id );
$t_user = user_get_row( $f_user_id );

# The same rules as the merge command has: nothing is merged that cannot be deleted
access_ensure_global_level( $t_user['access_level'] );
user_ensure_unprotected( $f_user_id );
if( $f_user_id == auth_get_current_user_id() ) {
	trigger_error( ERROR_ACCESS_DENIED, ERROR );
}

# The user that gets everything, when one was chosen
$t_target_id = false;
$t_not_found = false;
if( !is_blank( $f_target ) ) {
	$t_target_id = user_get_id_by_name( $f_target );
	if( $t_target_id === false ) {
		$t_target_id = user_get_id_by_email( $f_target );
	}
	if( $t_target_id === false || $t_target_id == $f_user_id || user_is_anonymous( $t_target_id ) ) {
		$t_target_id = false;
		$t_not_found = true;
	} else {
		access_ensure_global_level( user_get_field( $t_target_id, 'access_level' ) );
	}
}

layout_page_header( lang_get( 'merge_user_title' ) );
layout_page_begin( 'manage_overview_page.php' );

print_manage_menu( 'manage_user_page.php' );

$t_source_name = string_display_line( user_get_name( $f_user_id ) );
?>

<div class="col-md-12 col-xs-12">
	<div class="space-10"></div>

<?php if( $t_target_id === false ) { ?>
	<div id="merge-user-div" class="form-container">
		<form id="merge-user-form" method="get" action="manage_user_merge_page.php">
			<div class="widget-box widget-color-blue2">
				<div class="widget-header widget-header-small">
					<h4 class="widget-title lighter">
						<?php print_icon( 'fa-compress', 'ace-icon' ); ?>
						<?php echo lang_get( 'merge_user_title' ) ?>
					</h4>
				</div>
				<div class="widget-body">
					<div class="widget-main">
						<input type="hidden" name="user_id" value="<?php echo (int)$f_user_id ?>" />
						<p><?php echo sprintf( lang_get( 'merge_user_choose' ), $t_source_name, $t_source_name ) ?></p>
<?php if( $t_not_found ) { ?>
						<p class="alert alert-danger" role="alert"><?php echo lang_get( 'merge_user_not_found' ) ?></p>
<?php } ?>
						<div class="form-group">
							<label for="merge-user-target"><?php echo lang_get( 'merge_user_target' ) ?></label>
							<input type="text" id="merge-user-target" name="target" class="form-control input-sm" required
								value="<?php echo string_attribute( $f_target ) ?>" />
						</div>
					</div>
					<div class="widget-toolbox padding-8 clearfix">
						<input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'merge_user_continue' ) ?>" />
					</div>
				</div>
			</div>
		</form>
	</div>
<?php
} else {
	$t_target_name = string_display_line( user_get_name( $t_target_id ) );
	$t_counts = user_merge_count_references( $f_user_id );
?>
	<div id="merge-user-div" class="form-container">
		<form id="merge-user-form" method="post" action="manage_user_merge.php">
			<?php echo form_security_field( 'manage_user_merge' ) ?>
			<input type="hidden" name="user_id" value="<?php echo (int)$f_user_id ?>" />
			<input type="hidden" name="target_id" value="<?php echo (int)$t_target_id ?>" />
			<div class="widget-box widget-color-blue2">
				<div class="widget-header widget-header-small">
					<h4 class="widget-title lighter">
						<?php print_icon( 'fa-compress', 'ace-icon' ); ?>
						<?php echo lang_get( 'merge_user_title' ) ?>
					</h4>
				</div>
				<div class="widget-body">
					<div class="widget-main">
						<p class="alert alert-warning" role="alert"><?php echo sprintf( lang_get( 'merge_user_confirm' ), $t_source_name, $t_target_name, $t_source_name ) ?></p>
						<p><?php echo sprintf( lang_get( 'merge_user_details' ), $t_counts['reported'], $t_counts['assigned'], $t_counts['notes'], $t_target_name ) ?></p>
					</div>
					<div class="widget-toolbox padding-8 clearfix">
						<input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'merge_user_submit' ) ?>" />
					</div>
				</div>
			</div>
		</form>
	</div>
<?php } ?>
</div>

<?php
layout_page_end();
