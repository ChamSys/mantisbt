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
 * Let the reporter of an issue edit its text.
 *
 * CALLERS
 *	This page is called from the issue page, see bug_view_action_buttons().
 *
 * EXPECTED BEHAVIOUR
 *	Show the description, the steps to reproduce and the additional
 *	information of the issue in a form, then submit to bug_edit_text.php.
 *
 * RESTRICTIONS & PERMISSIONS
 *	- the user must be the reporter of the issue and have
 *	  $g_bug_user_edit_threshold, see access_can_edit_bug_text()
 *
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses core.php
 * @uses access_api.php
 * @uses bug_api.php
 * @uses columns_api.php
 * @uses config_api.php
 * @uses form_api.php
 * @uses gpc_api.php
 * @uses helper_api.php
 * @uses html_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses string_api.php
 */

require_once( 'core.php' );
require_api( 'access_api.php' );
require_api( 'bug_api.php' );
require_api( 'columns_api.php' );
require_api( 'config_api.php' );
require_api( 'form_api.php' );
require_api( 'gpc_api.php' );
require_api( 'helper_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );

$f_bug_id = gpc_get_int( 'bug_id' );
$t_bug = bug_get( $f_bug_id, true );

if( $t_bug->project_id != helper_get_current_project() ) {
	# in case the current project is not the same project of the bug we are viewing...
	# ... override the current project. This to avoid problems with categories and handlers lists etc.
	$g_project_override = $t_bug->project_id;
}

if( !access_can_edit_bug_text( $f_bug_id ) ) {
	access_denied();
}

# Same fields as the issue page shows
$t_fields = columns_filter_disabled( config_get( 'bug_view_page_fields' ) );
$t_max_textarea_length = config_get_global( 'max_textarea_length' );

layout_page_header( bug_format_summary( $f_bug_id, SUMMARY_CAPTION ) );

layout_page_begin();
?>
<div class="col-md-12 col-xs-12">

<form method="post" action="bug_edit_text.php">
<?php echo form_security_field( 'bug_edit_text' ) ?>
<input type="hidden" name="bug_id" value="<?php echo $f_bug_id ?>" />
<input type="hidden" name="last_updated" value="<?php echo $t_bug->last_updated ?>" />
<div class="widget-box widget-color-blue2">
<div class="widget-header widget-header-small">
	<h4 class="widget-title lighter">
		<?php print_icon( 'fa-edit', 'ace-icon' ); ?>
		<?php echo lang_get( 'edit_bug_text_title' ) ?>
	</h4>
</div>
<div class="widget-body">
	<div class="widget-main no-padding">
		<div class="table-responsive">
		<table class="table table-bordered table-condensed table-striped">
<?php
if( in_array( 'description', $t_fields ) ) {
	echo '<tr>';
	echo '<th class="category">';
	echo '<span class="required">*</span> ';
	echo '<label for="description">', lang_get( 'description' ), '</label>';
	echo '</th>';
	echo '<td>';
	echo '<textarea class="form-control" required ', helper_get_tab_index(),
		' cols="80" rows="10"',
		' maxlength="', $t_max_textarea_length, '"',
		' id="description" name="description">', "\n",
		string_textarea( $t_bug->description ), '</textarea>';
	echo '</td></tr>';
}

if( in_array( 'steps_to_reproduce', $t_fields ) ) {
	echo '<tr>';
	echo '<th class="category"><label for="steps_to_reproduce">', lang_get( 'steps_to_reproduce' ), '</label></th>';
	echo '<td>';
	echo '<textarea class="form-control" ', helper_get_tab_index(),
		' cols="80" rows="10"',
		' maxlength="', $t_max_textarea_length, '"',
		' id="steps_to_reproduce" name="steps_to_reproduce">', "\n",
		string_textarea( $t_bug->steps_to_reproduce ), '</textarea>';
	echo '</td></tr>';
}

if( in_array( 'additional_info', $t_fields ) ) {
	echo '<tr>';
	echo '<th class="category"><label for="additional_information">', lang_get( 'additional_information' ), '</label></th>';
	echo '<td>';
	echo '<textarea class="form-control" ', helper_get_tab_index(),
		' cols="80" rows="10"',
		' maxlength="', $t_max_textarea_length, '"',
		' id="additional_information" name="additional_information">', "\n",
		string_textarea( $t_bug->additional_information ), '</textarea>';
	echo '</td></tr>';
}
?>
		</table>
		</div>
	</div>
	<div class="widget-toolbox padding-8 clearfix">
		<p class="small lighter no-margin-top"><?php echo lang_get( 'edit_bug_text_hint' ) ?></p>
		<input type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo lang_get( 'update_information_button' ) ?>" />
		<?php print_link_button( string_get_bug_view_url( $f_bug_id ), lang_get( 'go_back' ) ) ?>
	</div>
</div>
</div>
</form>
</div>

<?php
layout_page_end();
