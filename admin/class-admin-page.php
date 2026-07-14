<?php
/**
 * Admin Page Class
 * Handles the admin interface for image consolidation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GIC_Admin_Page {

	/**
	 * Initialize admin page
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_form_submission' ) );
	}

	/**
	 * Add admin menu
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'tools.php',
			'Gliffen Image Consolidator',
			'Image Consolidator',
			'manage_options',
			'gliffen-image-consolidator',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Handle form submission
	 */
	public static function handle_form_submission() {
		if ( ! isset( $_POST['gic_nonce'] ) || ! wp_verify_nonce( $_POST['gic_nonce'], 'gic_form_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		if ( ! isset( $_POST['gic_form_submitted'] ) ) {
			return;
		}

		// Collect all size mappings from submitted form
		$disabled_sizes = array();
		$size_mappings = array();

		if ( isset( $_POST['gic_size_mappings'] ) && is_array( $_POST['gic_size_mappings'] ) ) {
			foreach ( $_POST['gic_size_mappings'] as $size_name => $replacement_size ) {
				$size_name = sanitize_text_field( $size_name );
				$replacement_size = sanitize_text_field( $replacement_size );

				// Only add to consolidation if replacement is selected
				if ( $replacement_size ) {
					$disabled_sizes[] = $size_name;
					$size_mappings[ $size_name ] = $replacement_size;
				}
			}
		}

		// Save settings
		GIC_Settings::update_option( 'disabled_sizes', $disabled_sizes );
		GIC_Settings::update_option( 'size_mappings', $size_mappings );
		wp_cache_flush();

		// Log action
		if ( class_exists( 'GIC_Audit_Logger' ) ) {
			GIC_Audit_Logger::log( 'settings_updated', array(
				'disabled_sizes_count' => count( $disabled_sizes ),
				'mappings' => count( $size_mappings ),
			) );
		}

		// Redirect to show success message
		set_transient( 'gic_settings_updated', true, 30 );
		wp_safe_redirect( admin_url( 'tools.php?page=gliffen-image-consolidator' ) );
		exit;
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'tools_page_gliffen-image-consolidator' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'gic-admin',
			GIC_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			gliffen_image_consolidator_get_version(),
			true
		);

		// Localize script with nonce
		wp_localize_script(
			'gic-admin',
			'gicAdmin',
			array(
				'nonce' => wp_create_nonce( 'gic_admin_nonce' ),
			)
		);

		wp_enqueue_style(
			'gic-admin-style',
			GIC_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			gliffen_image_consolidator_get_version()
		);
	}

	/**
	 * Render admin page
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$settings = GIC_Settings::get_all_settings();
		$all_sizes = GIC_Size_Manager::get_all_sizes();
		$size_mappings = $settings['size_mappings'];
		$consolidated_count = count( $settings['disabled_sizes'] );
		$settings_updated = get_transient( 'gic_settings_updated' );
		?>
		<div class="wrap gic-admin-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php if ( $settings_updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully!', 'gliffen-image-consolidator' ); ?></p>
				</div>
				<?php delete_transient( 'gic_settings_updated' ); ?>
			<?php endif; ?>
			
			<div class="gic-intro">
				<p><?php esc_html_e( 'Consolidate your WordPress image sizes to reduce disk bloat. Select a replacement size for image sizes you want to stop generating.', 'gliffen-image-consolidator' ); ?></p>
			</div>

			<form method="POST" id="gic-settings-form">
				<div class="gic-content">
					<div class="gic-sizes-container">
						<h2><?php esc_html_e( 'Image Sizes', 'gliffen-image-consolidator' ); ?></h2>
						<p class="gic-description"><?php esc_html_e( 'Select a replacement size to consolidate each image size. Sizes without a replacement will continue to be generated normally.', 'gliffen-image-consolidator' ); ?></p>

						<?php wp_nonce_field( 'gic_form_nonce', 'gic_nonce' ); ?>
						<input type="hidden" name="gic_form_submitted" value="1" />

						<table class="wp-list-table widefat fixed striped gic-sizes-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Size Name', 'gliffen-image-consolidator' ); ?></th>
								<th width="12%"><?php esc_html_e( 'Dimensions', 'gliffen-image-consolidator' ); ?></th>
								<th width="10%"><?php esc_html_e( 'Aspect Ratio', 'gliffen-image-consolidator' ); ?></th>
								<th width="15%"><?php esc_html_e( 'Source', 'gliffen-image-consolidator' ); ?></th>
								<th width="30%"><?php esc_html_e( 'Replace With', 'gliffen-image-consolidator' ); ?></th>
								<th width="5%"><?php esc_html_e( 'Crop', 'gliffen-image-consolidator' ); ?></th>
							</tr>
						</thead>
						<tbody id="gic-sizes-list">
							<?php foreach ( $all_sizes as $size ) : ?>
								<tr class="gic-size-row" data-size-name="<?php echo esc_attr( $size['name'] ); ?>">
									<td>
										<strong><?php echo esc_html( $size['name'] ); ?></strong>
									</td>
									<td>
										<?php echo esc_html( $size['width'] . 'x' . $size['height'] ); ?>
									</td>
									<td>
										<?php echo esc_html( $size['aspect_ratio'] ); ?>
									</td>
									<td>
										<?php echo esc_html( $size['source'] ); ?>
									</td>
									<td>
										<select name="gic_size_mappings[<?php echo esc_attr( $size['name'] ); ?>]" class="gic-replacement-select" data-size="<?php echo esc_attr( $size['name'] ); ?>">
											<option value=""><?php esc_html_e( '-- None (Keep generating) --', 'gliffen-image-consolidator' ); ?></option>
											<?php foreach ( $all_sizes as $replacement_size ) : ?>
												<?php if ( $replacement_size['name'] !== $size['name'] ) : ?>
													<?php 
														$current_replacement = isset( $size_mappings[ $size['name'] ] ) ? $size_mappings[ $size['name'] ] : ''; 
														$is_selected = $current_replacement === $replacement_size['name'];
													?>
													<option value="<?php echo esc_attr( $replacement_size['name'] ); ?>" <?php echo $is_selected ? 'selected="selected"' : ''; ?>>
														<?php echo esc_html( $replacement_size['name'] . ' (' . $replacement_size['width'] . 'x' . $replacement_size['height'] . ')' ); ?>
													</option>
												<?php endif; ?>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<?php echo $size['crop'] ? '✓' : '−'; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div class="gic-actions">
						<button type="submit" id="gic-save-settings" class="button button-primary">
							<?php esc_html_e( 'Save Settings', 'gliffen-image-consolidator' ); ?>
						</button>
					</div>
				</div>

				<div class="gic-sidebar">
					<div class="gic-box">
						<h3><?php esc_html_e( 'Info', 'gliffen-image-consolidator' ); ?></h3>
						<p><?php esc_html_e( 'Total image sizes registered:', 'gliffen-image-consolidator' ); ?> <strong><?php echo count( $all_sizes ); ?></strong></p>
						<p><?php esc_html_e( 'Sizes being consolidated:', 'gliffen-image-consolidator' ); ?> <strong><?php echo $consolidated_count; ?></strong></p>
					</div>

					<div class="gic-box">
						<h3><?php esc_html_e( 'How It Works', 'gliffen-image-consolidator' ); ?></h3>
						<p><?php esc_html_e( 'Select a replacement size for any image size you want to consolidate. New images uploaded will skip generating that size, and the replacement will be served instead when requested.', 'gliffen-image-consolidator' ); ?></p>
					</div>
				</div>
			</div>
		</form>

		<!-- Cleanup Section -->
		<?php
		$storage_data = GIC_Cleanup_Handler::calculate_storage_savings();
		$cleanup_stats = GIC_Cleanup_Handler::get_pending_cleanup_stats();
		?>
		<div class="gic-cleanup-section">
			<h2><?php esc_html_e( 'Storage Cleanup', 'gliffen-image-consolidator' ); ?></h2>
			
			<?php if ( $storage_data['total_files'] > 0 ) : ?>
				<div class="gic-content">
					<div class="gic-savings-display">
						<h3><?php esc_html_e( 'Potential Savings', 'gliffen-image-consolidator' ); ?></h3>
						<div class="gic-savings-box">
							<div class="gic-savings-stat">
								<span class="gic-savings-label"><?php esc_html_e( 'Disabled Size Files Found:', 'gliffen-image-consolidator' ); ?></span>
								<span class="gic-savings-value"><?php echo intval( $storage_data['total_files'] ); ?></span>
							</div>
							<div class="gic-savings-stat">
								<span class="gic-savings-label"><?php esc_html_e( 'Potential Disk Savings:', 'gliffen-image-consolidator' ); ?></span>
								<span class="gic-savings-value gic-savings-size"><?php echo esc_html( $storage_data['potential_savings_human'] ); ?></span>
							</div>
						</div>

						<h4><?php esc_html_e( 'Breakdown by Size:', 'gliffen-image-consolidator' ); ?></h4>
						<table class="wp-list-table widefat fixed striped gic-savings-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Disabled Size', 'gliffen-image-consolidator' ); ?></th>
									<th><?php esc_html_e( 'File Count', 'gliffen-image-consolidator' ); ?></th>
									<th><?php esc_html_e( 'Disk Space', 'gliffen-image-consolidator' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $storage_data['details'] as $size_name => $size_data ) : ?>
									<tr>
										<td><?php echo esc_html( $size_name ); ?></td>
										<td><?php echo intval( $size_data['file_count'] ); ?></td>
										<td><?php echo esc_html( $size_data['human'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<div class="gic-cleanup-actions" style="margin-top: 20px;">
							<button type="button" id="gic-move-to-cleanup" class="button button-primary">
								<?php esc_html_e( 'Move to Pending Cleanup', 'gliffen-image-consolidator' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Moves all disabled size files to a separate folder. You can review them before permanent deletion.', 'gliffen-image-consolidator' ); ?></p>
						</div>
					</div>
				</div>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No disabled size files found. Start by consolidating image sizes above.', 'gliffen-image-consolidator' ); ?></p>
			<?php endif; ?>

			<!-- Pending Cleanup Section -->
			<?php if ( $cleanup_stats['exists'] ) : ?>
				<div class="gic-content" style="margin-top: 40px; border-top: 1px solid #ccc; padding-top: 20px;">
					<h3><?php esc_html_e( 'Pending Cleanup Folder', 'gliffen-image-consolidator' ); ?></h3>
					<div class="gic-pending-box">
						<div class="gic-savings-stat">
							<span class="gic-savings-label"><?php esc_html_e( 'Files in Cleanup Folder:', 'gliffen-image-consolidator' ); ?></span>
							<span class="gic-savings-value"><?php echo intval( $cleanup_stats['file_count'] ); ?></span>
						</div>
						<div class="gic-savings-stat">
							<span class="gic-savings-label"><?php esc_html_e( 'Cleanup Folder Size:', 'gliffen-image-consolidator' ); ?></span>
							<span class="gic-savings-value gic-savings-size"><?php echo esc_html( $cleanup_stats['total_human'] ); ?></span>
						</div>
					</div>

					<?php if ( $cleanup_stats['file_count'] > 0 ) : ?>
						<div class="gic-cleanup-actions" style="margin-top: 20px;">
							<button type="button" id="gic-delete-cleanup" class="button button-secondary gic-danger-button" 
									onclick="return confirm('<?php esc_attr_e( 'Permanently delete all files in the cleanup folder? This cannot be undone.', 'gliffen-image-consolidator' ); ?>')">
								<?php esc_html_e( 'Permanently Delete', 'gliffen-image-consolidator' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Permanently delete all files in the pending cleanup folder. This cannot be undone.', 'gliffen-image-consolidator' ); ?></p>
						</div>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'The cleanup folder is empty.', 'gliffen-image-consolidator' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<!-- Metadata Cleanup Section -->
		<div class="gic-content" style="margin-top: 40px; border-top: 1px solid #ccc; padding-top: 20px;">
			<h3><?php esc_html_e( 'Metadata Cleanup', 'gliffen-image-consolidator' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Remove disabled image sizes from attachment metadata to ensure compatibility with thumbnail regenerators. This ensures your metadata matches your actual disk state.', 'gliffen-image-consolidator' ); ?></p>
			
			<div class="gic-cleanup-actions" style="margin-top: 20px;">
				<button type="button" id="gic-cleanup-metadata" class="button button-secondary">
					<?php esc_html_e( 'Clean Metadata', 'gliffen-image-consolidator' ); ?>
				</button>
				<p class="description"><?php esc_html_e( 'This will remove disabled size entries from all attachment metadata records.', 'gliffen-image-consolidator' ); ?></p>
			</div>
		</div>		</div>
		</div>
		<?php 
	}
}?>