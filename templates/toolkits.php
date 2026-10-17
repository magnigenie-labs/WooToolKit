<?php
/**
 * Admin View: WooToolKit - Installed Kits
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$objWootoolKit = $GLOBALS['wootoolkit'];

// Setting kit status to display (actions are handled in WooToolkit::handle_kit_actions on admin_init)
$kit_status = $objWootoolKit->sanitize_kit_status( isset( $_GET['kit_status'] ) ? wp_unslash( $_GET['kit_status'] ) : 'all' );
$message    = isset( $_GET['message'] ) ? absint( $_GET['message'] ) : 0;

$installed_kits = $objWootoolKit->get_kits();

?>

<div class="wrap">
	
	<h1>
		<?php esc_html_e( 'Toolkits', 'wootoolkit' ); ?>
	</h1>
	<?php
	$messages = array(
		1 => __( 'Kit <strong>activated</strong> successfully.', 'wootoolkit' ),
		2 => __( 'Kit <strong>deactivated</strong> successfully.', 'wootoolkit' ),
		3 => __( 'Selected kits <strong>activated</strong> successfully.', 'wootoolkit' ),
		4 => __( 'Selected kits <strong>deactivated</strong> successfully.', 'wootoolkit' ),
	);
	if ( isset( $messages[ $message ] ) ) { ?>
	<div id="message" class="updated notice is-dismissible">
		<p><?php echo wp_kses( $messages[ $message ], array( 'strong' => array() ) ); ?></p>
	</div>
	<?php } ?>

	<h2 class="screen-reader-text"><?php esc_html_e( 'Filter plugins list', 'wootoolkit' ); ?></h2>
	<ul class="subsubsub">
		
		<li class="all">
			<a href="admin.php?page=woo-toolkit&kit_status=all" class="current">
				<?php esc_html_e( 'All', 'wootoolkit' ); ?> <span class="count">(<?php echo absint( $objWootoolKit->get_kit_count( 'all' ) ); ?>)</span>
			</a>
			<?php if($objWootoolKit->get_kit_count('active') > 0 || $objWootoolKit->get_kit_count('inactive') > 0) { echo "|"; } ?>
		</li>

		<?php if( $objWootoolKit->get_kit_count( 'active' ) > 0 ) { ?>
		<li class="active">
			<a href="admin.php?page=woo-toolkit&kit_status=active">
				<?php esc_html_e( 'Active', 'wootoolkit' ); ?>
				<span class="count">(<?php echo absint( $objWootoolKit->get_kit_count( 'active' ) ); ?>)</span>
			</a>
			<?php if( $objWootoolKit->get_kit_count( 'inactive' ) > 0 ) { echo "|"; } ?>
		</li>
		<?php } ?>

		<?php if( $objWootoolKit->get_kit_count( 'inactive' ) > 0 ) { ?>
		<li class="active">
			<a href="admin.php?page=woo-toolkit&kit_status=inactive">
				<?php esc_html_e( 'Inactive', 'wootoolkit' ); ?>
				<span class="count">(<?php echo absint( $objWootoolKit->get_kit_count( 'inactive' ) ); ?>)</span>
			</a>
		</li>
		<?php } ?>

	</ul>

	<form method="post" id="bulk-action-form" action="<?php echo esc_url( add_query_arg( array( 'page' => 'woo-toolkit', 'kit_status' => $kit_status ), admin_url( 'admin.php' ) ) ); ?>">
		<?php wp_nonce_field( 'wootoolkit_bulk_kits' ); ?>

		<div class="tablenav top">
			<div class="alignleft actions bulkactions">
				<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wootoolkit' ); ?></label>
				<select name="action" id="bulk-action-selector-top">
					<option value="-1"><?php esc_html_e( 'Bulk Actions', 'wootoolkit' ); ?></option>
					<option value="activate-selected"><?php esc_html_e( 'Activate', 'wootoolkit' ); ?></option>
					<option value="deactivate-selected"><?php esc_html_e( 'Deactivate', 'wootoolkit' ); ?></option>
				</select>
				<input type="submit" id="doaction" class="button action" value="<?php esc_attr_e( 'Apply', 'wootoolkit' ); ?>">
			</div>
		</div>

		<h2 class="screen-reader-text"><?php esc_html_e( 'Kits List', 'wootoolkit' ); ?></h2>
		<table class="wp-list-table widefat plugins">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-1">
						<?php esc_html_e( 'Select All', 'wootoolkit' ); ?></label>
						<input id="cb-select-all-1" type="checkbox"></td>
					<th scope="col" id="name" class="manage-column column-name column-primary">
						<?php esc_html_e( 'Kit', 'wootoolkit' ); ?></th>
					<th scope="col" id="description" class="manage-column column-description">
						<?php esc_html_e( 'Description', 'wootoolkit' ); ?></th>
				</tr>
			</thead>

			<tbody id="the-list">

				<?php 

				if( count($installed_kits) > 0 ): 

				foreach( $installed_kits as $key => $kit):

				$active_status = $objWootoolKit->get_kit_status($key); 

				$slug = $kit['PluginSlug'];

				$actions = array(
					'deactivate' => '',
					'activate' => ''
				);

				?>
				
				<tr class="<?php echo esc_attr( $active_status ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>" data-plugin="<?php echo esc_attr( $key ); ?>">
					<th scope="row" class="check-column">
						<label class="screen-reader-text" for="checkbox_<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( __( 'Select', 'wootoolkit' ) . ' ' . $kit['Name'] ); ?></label>
						<input type="checkbox" name="checked[]" value="<?php echo esc_attr( $key ); ?>" id="checkbox_<?php echo esc_attr( $slug ); ?>"></th>
					<td class="plugin-title column-primary"> <strong><?php echo esc_html( $kit['Name'] ); ?></strong>
						
					<?php 

						if( 'inactive' == $active_status ) {

					    $url = wp_nonce_url( add_query_arg( array( 'page' => 'woo-toolkit', 'kit_status' => $kit_status, 'action' => 'activate', 'plugin' => $key ), admin_url( 'admin.php' ) ), 'wootoolkit_activate_' . $key );
					    $actions['activate'] = '<a href="' . esc_url( $url ) . '" class="edit">' . esc_html__( 'Activate', 'wootoolkit' ) . '</a>';

						} else if ( 'active' == $active_status ) {
						
						$url = wp_nonce_url( add_query_arg( array( 'page' => 'woo-toolkit', 'kit_status' => $kit_status, 'action' => 'deactivate', 'plugin' => $key ), admin_url( 'admin.php' ) ), 'wootoolkit_deactivate_' . $key );
						$actions['deactivate'] = '<a href="' . esc_url( $url ) . '" class="edit">' . esc_html__( 'Deactivate', 'wootoolkit' ) . '</a>';
						} 

						$actions = array_filter( $actions );
						$actions = apply_filters( "kit_action_links_{$slug}", $actions, $slug );

						echo $objWootoolKit->kit_row_actions( $actions, true);

					?></td>

					<td class="column-description desc">
						<div class="plugin-description">
							<p>
								<?php echo wp_kses_post( $kit['Description'] ); ?>
							</p>
						</div>
						<div class="inactive second plugin-version-author-uri">
							<?php echo esc_html( sprintf( __( 'Version %s', 'wootoolkit' ), $kit['Version'] ) ); ?> | <?php esc_html_e( 'By', 'wootoolkit' ); ?>
							<a href="<?php echo esc_url( $kit['AuthorURI'] ); ?>"><?php echo esc_html( $kit['Author'] ); ?></a>
						</div>
					</td>
				</tr>

				<?php endforeach; else: ?>

				<tr class="no-items"><td class="colspanchange" colspan="3"><?php esc_html_e( 'No kits found.', 'wootoolkit' ); ?></td></tr>
				
				<?php endif; ?>

			</tbody>

			<tfoot>
				<tr>
					<td class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-2">
						<?php esc_html_e( 'Select All', 'wootoolkit' ); ?></label>
						<input id="cb-select-all-2" type="checkbox"></td>
					<th scope="col" class="manage-column column-name column-primary">
						<?php esc_html_e( 'Kit', 'wootoolkit' ); ?></th>
					<th scope="col" class="manage-column column-description">
						<?php esc_html_e( 'Description', 'wootoolkit' ); ?>
					</th>
				</tr>
			</tfoot>

		</table>

	</form>
</div>