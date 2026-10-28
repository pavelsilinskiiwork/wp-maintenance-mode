<?php
/**
 * Uninstall: remove plugin settings.
 *
 * @package PavelSilinskii\MaintenanceMode
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'psmm_settings' );
