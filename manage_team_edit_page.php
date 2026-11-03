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
 * Edit a team: its name and description, its members, and delete it
 *
 * @package MantisBT
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses access_api.php
 * @uses compress_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses form_api.php
 * @uses gpc_api.php
 * @uses html_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses string_api.php
 * @uses team_api.php
 * @uses user_api.php
 * @uses user_picker_api.php
 */

require_once( 'core.php' );
require_api( 'access_api.php' );
require_api( 'compress_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );
require_api( 'team_api.php' );
require_api( 'user_api.php' );
require_api( 'user_picker_api.php' );

access_ensure_global_level( config_get( 'manage_team_threshold' ) );

compress_enable();

$f_team_id = gpc_get_int( 'team_id' );
$t_team = team_get_row( $f_team_id );
$t_member_ids = team_get_member_ids( $f_team_id );

layout_page_header( lang_get( 'manage_teams_link' ) );
layout_page_begin( 'manage_overview_page.php' );

print_manage_menu( 'manage_team_page.php' );
?>

<div class="col-md-12 col-xs-12">
	<div class="space-10"></div>

<form id="manage-team-update-form" method="post" action="manage_team_update.php">
<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<?php print_icon( 'fa-users', 'ace-icon' ); ?>
			<?php echo string_display_line( $t_team['name'] ) ?>
		</h4>
	</div>
	<div class="widget-body">
		<div class="widget-main no-padding">
		<div class="form-container">
		<div class="table-responsive">
		<fieldset>
			<?php echo form_security_field( 'manage_team_update' ); ?>
			<input type="hidden" name="team_id" value="<?php echo $f_team_id ?>" />
			<table class="table table-bordered table-condensed table-striped">
			<tr>
				<td class="category">
					<label for="team-name">
						<span class="required">*</span>
						<?php echo lang_get( 'team_name' ) ?>
					</label>
				</td>
				<td>
					<input type="text" id="team-name" name="name" class="input-sm" size="40" maxlength="128"
						value="<?php echo string_attribute( $t_team['name'] ) ?>" required />
				</td>
			</tr>
			<tr>
				<td class="category">
					<label for="team-description">
						<?php echo lang_get( 'description' ) ?>
					</label>
				</td>
				<td>
					<textarea class="form-control" id="team-description" name="description" cols="80" rows="6"><?php echo string_textarea( $t_team['description'] ) ?></textarea>
				</td>
			</tr>
			</table>
		</fieldset>
		</div>
		</div>
		</div>
		<div class="widget-toolbox padding-8 clearfix">
			<span class="required pull-right"> * <?php echo lang_get( 'required' ); ?></span>
			<input type="submit" class="btn btn-primary btn-sm btn-white btn-round"
				   value="<?php echo lang_get( 'team_update' ) ?>"/>
		</div>
	</div>
</div>
</form>

<div class="space-10"></div>
<div id="team-members" class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<?php print_icon( 'fa-user', 'ace-icon' ); ?>
			<?php echo lang_get( 'team_members' ) ?>
			<span class="badge"><?php echo count( $t_member_ids ) ?></span>
		</h4>
	</div>
	<div class="widget-body">
		<div class="widget-toolbox padding-8 clearfix">
			<form id="manage-team-member-add-form" method="post" action="manage_team_member_add.php" class="form-inline">
				<?php echo form_security_field( 'manage_team_member_add' ); ?>
				<input type="hidden" name="team_id" value="<?php echo $f_team_id ?>" />
				<select name="user_id" class="input-sm" required<?php print_user_typeahead_attributes( 'handler', ALL_PROJECTS ); ?>>
					<option value=""></option>
					<?php print_user_option_list( NO_USER, ALL_PROJECTS, config_get( 'handle_bug_threshold' ), user_typeahead_is_active( config_get( 'handle_bug_threshold' ), ALL_PROJECTS ) ); ?>
				</select>
				<input type="submit" class="btn btn-primary btn-sm btn-white btn-round"
					   value="<?php echo lang_get( 'team_add_member' ) ?>"/>
			</form>
		</div>
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-striped table-bordered table-condensed table-hover">
		<tbody>
<?php
	$t_security_token = form_security_token( 'manage_team_member_remove' );
	foreach( $t_member_ids as $t_user_id ) {
?>
			<tr>
				<td><?php echo string_display_line( user_get_name( $t_user_id ) ) ?></td>
				<td><?php echo string_display_line( user_get_username( $t_user_id ) ) ?></td>
				<td class="center"><?php
					print_form_button(
						'manage_team_member_remove.php',
						lang_get( 'team_remove_member' ),
						array( 'team_id' => $f_team_id, 'user_id' => $t_user_id ),
						$t_security_token
					);
				?></td>
			</tr>
<?php
	}
	if( empty( $t_member_ids ) ) {
?>
			<tr><td><?php echo lang_get( 'team_no_members' ) ?></td></tr>
<?php
	}
?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>

<div class="space-10"></div>
<?php
print_form_button(
	'manage_team_delete.php',
	lang_get( 'team_delete' ),
	array( 'team_id' => $f_team_id ),
	form_security_token( 'manage_team_delete' )
);
?>
</div>
<?php
layout_page_end();
