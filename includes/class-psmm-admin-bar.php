<?php
/**
 * Admin bar status indicator.
 *
 * @package PavelSilinskii\MaintenanceMode
 */

namespace PavelSilinskii\MaintenanceMode;

defined( 'ABSPATH' ) || exit;

/**
 * Shows a red "Maintenance Mode: ON" item to administrators so the mode is never forgotten.
 */
class AdminBar {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'add_node' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether the indicator should be shown.
	 *
	 * @return bool
	 */
	private function is_visible(): bool {
		return is_admin_bar_showing() && current_user_can( 'manage_options' ) && Settings::is_active();
	}

	/**
	 * Add the status node.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar instance.
	 * @return void
	 */
	public function add_node( $admin_bar ) {
		if ( ! $this->is_visible() ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'psmm-status',
				'title' => esc_html__( 'Maintenance Mode: ON', 'pavel-silinskii-maintenance-mode' ),
				'href'  => admin_url( 'options-general.php?page=' . Admin::PAGE ),
				'meta'  => array( 'class' => 'psmm-status' ),
			)
		);
	}

	/**
	 * Color the node red.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->is_visible() ) {
			return;
		}

		wp_add_inline_style( 'admin-bar', '#wpadminbar .psmm-status > .ab-item{background:#d63638;color:#fff;}#wpadminbar .psmm-status:hover > .ab-item{background:#b32d2e;color:#fff;}' );
	}
}
