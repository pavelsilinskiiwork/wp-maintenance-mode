<?php
/**
 * Plugin Name:       Pavel Silinskii Maintenance Mode
 * Plugin URI:        https://github.com/pavelsilinskiiwork/wp-maintenance-mode
 * Description:       Lightweight maintenance mode for WordPress. Shows a customizable maintenance page with a proper 503 status and Retry-After header, an optional countdown, and bypass by role, IP address or secret link.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      8.0
 * Author:            Pavel Silinskii
 * Author URI:        https://profiles.wordpress.org/pavelsilinskii/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pavel-silinskii-maintenance-mode
 *
 * @package PavelSilinskii\MaintenanceMode
 */

namespace PavelSilinskii\MaintenanceMode;

defined( 'ABSPATH' ) || exit;

define( 'PSMM_VERSION', '1.0.0' );
define( 'PSMM_PLUGIN_FILE', __FILE__ );
define( 'PSMM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PSMM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PSMM_PLUGIN_DIR . 'includes/class-psmm-settings.php';
require_once PSMM_PLUGIN_DIR . 'includes/class-psmm-frontend.php';
require_once PSMM_PLUGIN_DIR . 'includes/class-psmm-admin-bar.php';
require_once PSMM_PLUGIN_DIR . 'admin/class-psmm-admin.php';

/**
 * Save default settings on activation, without overwriting existing ones.
 * Maintenance mode stays off until it is enabled on the settings page.
 *
 * @return void
 */
function activate() {
	add_option( Settings::OPTION, array_merge( Settings::DEFAULTS, array( 'bypass_key' => Settings::generate_key() ) ) );
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

add_action(
	'plugins_loaded',
	function () {
		new Frontend();
		new AdminBar();

		if ( is_admin() ) {
			new Admin();
		}
	}
);
