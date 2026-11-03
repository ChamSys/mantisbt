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
 * Manage Teams: the list of teams, and a form to create one
 *
 * @package MantisBT
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses access_api.php
 * @uses compress_api.php
 * @uses config_api.php
 * @uses form_api.php
 * @uses html_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses string_api.php
 * @uses team_api.php
 */

require_once( 'core.php' );
require_api( 'access_api.php' );
require_api( 'compress_api.php' );
require_api( 'config_api.php' );
require_api( 'form_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );
require_api( 'team_api.php' );

team_ensure_enabled();
access_ensure_global_level( config_get( 'manage_team_threshold' ) );

compress_enable();

$t_teams = team_get_all();
$t_member_counts = team_get_member_counts();
$t_issue_counts = team_get_issue_counts();

layout_page_header( lang_get( 'manage_teams_link' ) );
layout_page_begin( 'manage_overview_page.php' );

print_manage_menu( 'manage_team_page.php' );
?>

<div class="col-md-12 col-xs-12">
	<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<?php print_icon( 'fa-users', 'ace-icon' ); ?>
			<?php echo lang_get( 'manage_teams_link' ) ?>
			<span class="badge"><?php echo count( $t_teams ) ?></span>
		</h4>
	</div>

	<div class="widget-body">
		<div class="widget-toolbox padding-8 clearfix">
			<?php print_small_button( '#team-create', lang_get( 'team_create' ) ) ?>
		</div>
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-striped table-bordered table-condensed table-hover">
		<thead>
			<tr>
				<th><?php echo lang_get( 'team_name' ) ?></th>
				<th><?php echo lang_get( 'description' ) ?></th>
				<th><?php echo lang_get( 'team_members' ) ?></th>
				<th><?php echo lang_get( 'team_issues' ) ?></th>
				<th><?php echo lang_get( 'date_created' ) ?></th>
			</tr>
		</thead>
		<tbody>
<?php
	foreach( $t_teams as $t_team_id => $t_team ) {
?>
			<tr>
				<td><a href="manage_team_edit_page.php?team_id=<?php echo $t_team_id ?>"><?php echo string_display_line( $t_team['name'] ) ?></a></td>
				<td><?php echo string_display_line( $t_team['description'] ) ?></td>
				<td><?php echo isset( $t_member_counts[$t_team_id] ) ? $t_member_counts[$t_team_id] : 0 ?></td>
				<td><?php echo isset( $t_issue_counts[$t_team_id] ) ? $t_issue_counts[$t_team_id] : 0 ?></td>
				<td><?php echo date( config_get( 'normal_date_format' ), $t_team['date_created'] ) ?></td>
			</tr>
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
<form id="manage-team-create-form" method="post" action="manage_team_create.php">
<div id="team-create" class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<?php print_icon( 'fa-users', 'ace-icon' ); ?>
			<?php echo lang_get( 'team_create' ) ?>
		</h4>
	</div>
	<div class="widget-body">
		<div class="widget-main no-padding">
		<div class="form-container">
		<div class="table-responsive">
		<fieldset>
			<?php echo form_security_field( 'manage_team_create' ); ?>
			<table class="table table-bordered table-condensed table-striped">
			<tr>
				<td class="category">
					<label for="team-name">
						<span class="required">*</span>
						<?php echo lang_get( 'team_name' ) ?>
					</label>
				</td>
				<td>
					<input type="text" id="team-name" name="name" class="input-sm" size="40" maxlength="128" required />
				</td>
			</tr>
			<tr>
				<td class="category">
					<label for="team-description">
						<?php echo lang_get( 'description' ) ?>
					</label>
				</td>
				<td>
					<textarea class="form-control" id="team-description" name="description" cols="80" rows="6"></textarea>
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
				   value="<?php echo lang_get( 'team_create' ) ?>"/>
		</div>
	</div>
</div>
</form>
</div>
<?php
layout_page_end();
