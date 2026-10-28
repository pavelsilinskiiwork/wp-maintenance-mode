<?php
/**
 * Settings storage and maintenance state.
 *
 * @package PavelSilinskii\MaintenanceMode
 */

namespace PavelSilinskii\MaintenanceMode;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the psmm_settings option and answers "is maintenance mode on right now?".
 */
class Settings {

	/**
	 * Option name.
	 */
	public const OPTION = 'psmm_settings';

	/**
	 * Format of the end date, as sent by an <input type="datetime-local">, in the site timezone.
	 */
	public const DATETIME_FORMAT = 'Y-m-d\TH:i';

	/**
	 * Default settings, used for any key missing from the saved option.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULTS = array(
		'enabled'          => false,
		'title'            => 'We\'ll be back soon',
		'message'          => 'Our website is undergoing scheduled maintenance. Thank you for your patience — please check back shortly.',
		'end_datetime'     => '',
		'show_countdown'   => true,
		'auto_disable'     => false,
		'retry_after'      => 60,
		'logo_url'         => '',
		'background_color' => '#1e1e2e',
		'text_color'       => '#ffffff',
		'bypass_roles'     => array(),
		'ip_whitelist'     => array(),
		'bypass_key'       => '',
	);

	/**
	 * Get saved settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::DEFAULTS );
	}

	/**
	 * Whether visitors should currently see the maintenance page.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		$settings = self::get();

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		$end = self::end_timestamp( $settings );

		// Scheduled end has passed: the site goes live on its own.
		if ( $settings['auto_disable'] && $end && $end <= time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Convert the stored end date (site timezone) to a Unix timestamp.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return int|null Null when no valid end date is set.
	 */
	public static function end_timestamp( array $settings ): ?int {
		if ( empty( $settings['end_datetime'] ) ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( self::DATETIME_FORMAT, (string) $settings['end_datetime'], wp_timezone() );

		return $date ? $date->getTimestamp() : null;
	}

	/**
	 * Generate a random secret for the bypass link.
	 *
	 * @return string
	 */
	public static function generate_key(): string {
		return wp_generate_password( 20, false, false );
	}
}
