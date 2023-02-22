<?php
/**
 * Admin View: WooToolKit - Installed Kits
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$objWootoolKit = new WooToolkit();

// Setting kit status to display
if( isset( $_GET['kit_status'] ) && $_GET['kit_status'] != '' ) {
	$kit_status = $_GET['kit_status'];
} else {
	$kit_status = 'all';
}

if( isset( $_REQUEST['action'] ) && $_REQUEST['action'] != '' ) {

	// Checking for activating the kit
	if( 'activate' == $_REQUEST['action'] ) {

		// Activating the plugin
		$objWootoolKit->activate_kit( $_REQUEST['plugin'] );

		// Setting the success message
		wp_redirect( admin_url() . 'admin.php?page=woo-toolkit&kit_status='.$kit_status.'&message=1' );
	}

	// Checking for deacivationg the kit
	if( 'deactivate' == $_REQUEST['action'] ) {

		// Deactivating the plugin
		$objWootoolKit->deactivate_kit( $_REQUEST['plugin'] );

		// Setting the success message
		wp_redirect( admin_url() . 'admin.php?page=woo-toolkit&kit_status='.$kit_status.'&message=2' );
	}

	// Activation of multiple kits
	if( 'activate-selected' == $_REQUEST['action'] ) {

		// Activating the plugin
		$objWootoolKit->activate_selected_kits( $_REQUEST['checked'] );

		// Setting the success message
		wp_redirect( admin_url() . 'admin.php?page=woo-toolkit&kit_status='.$kit_status.'&message=3' );
	}

	// Deactivation of multiple kits
	if( 'deactivate-selected' == $_REQUEST['action'] ) {

		// Deactivating the plugin
		$objWootoolKit->deactivate_selected_kits( $_REQUEST['checked'] );

		// Setting the success message
		wp_redirect( admin_url() . 'admin.php?page=woo-toolkit&kit_status='.$kit_status.'&message=4' );
	}
}

$installed_kits = $objWootoolKit->get_kits();

?>

<div class="wrap">
	
	<h1>
		<?php echo __( 'Toolkits', 'wootoolkit' ) ?>
	</h1>
	<?php if( isset( $_GET['message'] ) ) { ?>
	<div id="message" class="updated notice is-dismissible">
		
		<?php if( $_GET['message'] == 1 ) { ?>
			<p>Kit <strong>activated</strong> successfully.</p> 
		<?php } ?>

		<?php if( $_GET['message'] == 2 ) { ?>
			<p>Kit <strong>deactivated</strong> successfully.</p> 
		<?php } ?>

		<?php if( $_GET['message'] == 3 ) { ?>
			<p>Selected kits <strong>activated</strong> successfully.</p> 
		<?php } ?>

		<?php if( $_GET['message'] == 4 ) { ?>
			<p>Selected kits <strong>deactivated</strong> successfully.</p> 
		<?php } ?>

	</div>
	<?php } ?>

	<h2 class="screen-reader-text"><?php echo __( 'Filter plugins list', 'wootoolkit' ) ?></h2>
	<ul class="subsubsub">
		
		<li class="all">
			<a href="admin.php?page=woo-toolkit&kit_status=all" class="current">
				<?php echo __( 'All', 'wootoolkit' ) ?> <span class="count">(<?php echo $objWootoolKit->get_kit_count('all'); ?>)</span>
			</a>
			<?php if($objWootoolKit->get_kit_count('active') > 0 || $objWootoolKit->get_kit_count('inactive') > 0) { echo "|"; } ?>
		</li>

		<?php if( $objWootoolKit->get_kit_count( 'active' ) > 0 ) { ?>
		<li class="active">
			<a href="admin.php?page=woo-toolkit&kit_status=active">
				<?php echo __( 'Active', 'wootoolkit' ) ?>
				<span class="count">(<?php echo $objWootoolKit->get_kit_count( 'active' ); ?>)</span>
			</a>
			<?php if( $objWootoolKit->get_kit_count( 'inactive' ) > 0 ) { echo "|"; } ?>
		</li>
		<?php } ?>

		<?php if( $objWootoolKit->get_kit_count( 'inactive' ) > 0 ) { ?>
		<li class="active">
			<a href="admin.php?page=woo-toolkit&kit_status=inactive">
				<?php echo __( 'Inactive', 'wootoolkit' ) ?>
				<span class="count">(<?php echo $objWootoolKit->get_kit_count( 'inactive' ); ?>)</span>
			</a>
		</li>
		<?php } ?>

	</ul>

	<form method="post" id="bulk-action-form">

		<div class="tablenav top">
			<div class="alignleft actions bulkactions">
				<label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
				<select name="action" id="bulk-action-selector-top">
					<option value="">Bulk Actions</option>
					<option value="activate-selected">Activate</option>
					<option value="deactivate-selected">Deactivate</option>
				</select>
				<input type="submit" id="doaction" class="button action" value="Apply">
			</div>
		</div>

		<h2 class="screen-reader-text"><?php echo __( 'Kits List', 'wootoolkit' ) ?></h2>
		<table class="wp-list-table widefat plugins">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-1">
						<?php echo __( 'Select All', 'wootoolkit' ) ?></label>
						<input id="cb-select-all-1" type="checkbox"></td>
					<th scope="col" id="name" class="manage-column column-name column-primary">
						<?php echo __( 'Kit', 'wootoolkit' ) ?></th>
					<th scope="col" id="description" class="manage-column column-description">
						<?php echo __( 'Description', 'wootoolkit' ) ?></th>
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
				
				<tr class="<?php echo $active_status; ?>" data-slug="<?php echo $slug ?>" data-plugin="<?php echo $key; ?>">
					<th scope="row" class="check-column">
						<label class="screen-reader-text" for="checkbox_<?php echo $kit['PluginSlug'] ?>"><?php echo __( 'Select', 'wootoolkit' ) . ' ' . $kit['Name'] ?></label>
						<input type="checkbox" name="checked[]" value="<?php echo $key; ?>" id="checkbox_<?php echo $kit['PluginSlug'] ?>"></th>
					<td class="plugin-title column-primary"> <strong><?php echo $kit['Name'] ?></strong>
						
					<?php 

						if( 'inactive' == $active_status ) {

					    $actions['activate'] = '<a href="admin.php?page=woo-toolkit&kit_status='. $kit_status .'&action=activate&amp;plugin='.$key.'" class="edit">'.__( 'Activate', 'wootoolkit' ).'</a>';

						} else if ( 'active' == $active_status ) {
						
						$actions['deactivate'] = '<a href="admin.php?page=woo-toolkit&kit_status='.$kit_status.'&action=deactivate&amp;plugin='.$key.'" class="edit">'.__( 'Deactivate', 'wootoolkit' ).'</a>';
						} 

						$actions = array_filter( $actions );
						$actions = apply_filters( "kit_action_links_{$slug}", $actions, $slug );

						echo $objWootoolKit->kit_row_actions( $actions, true);

					?></td>

					<td class="column-description desc">
						<div class="plugin-description">
							<p>
								<?php echo $kit['Description'] ?>
							</p>
						</div>
						<div class="inactive second plugin-version-author-uri">
							Version <?php echo $kit['Version'] ?> | By
							<a href="<?php echo $kit['AuthorURI'] ?>"><?php echo $kit['Author'] ?></a>
						</div>
					</td>
				</tr>

				<?php endforeach; else: ?>

				<tr class="no-items"><td class="colspanchange" colspan="3">No kits found.</td></tr>
				
				<?php endif; ?>

			</tbody>

			<tfoot>
				<tr>
					<td class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-2">
						<?php echo __( 'Select All', 'wootoolkit' ) ?></label>
						<input id="cb-select-all-2" type="checkbox"></td>
					<th scope="col" class="manage-column column-name column-primary">
						<?php echo __( 'Kit', 'wootoolkit' ) ?></th>
					<th scope="col" class="manage-column column-description">
						<?php echo __( 'Description', 'wootoolkit' ); ?>
					</th>
				</tr>
			</tfoot>

		</table>

	</form>
</div>