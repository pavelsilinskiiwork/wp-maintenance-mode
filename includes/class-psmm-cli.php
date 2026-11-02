<?php
/**
 * WP-CLI commands for deploy scripts and CI/CD pipelines.
 *
 * @package PavelSilinskii\MaintenanceMode
 */

namespace PavelSilinskii\MaintenanceMode;

defined( 'ABSPATH' ) || exit;

/**
 * Turns maintenance mode on and off from the command line.
 *
 * Unlike core `wp maintenance-mode`, visitors get the branded page with 503 + Retry-After,
 * administrators and bypass-link holders keep seeing the site, and the timeout is configurable.
 *
 * ## EXAMPLES
 *
 *     # Deploy with a safety timeout: the site comes back by itself if the script fails.
 *     wp psmm on --for=15m
 *     git pull && composer install --no-dev && wp core update-db
 *     wp psmm off
 */
class Cli {

	/**
	 * Enable maintenance mode.
	 *
	 * ## OPTIONS
	 *
	 * [--for=<duration>]
	 * : Turn maintenance mode off automatically after this long, e.g. 90s, 15m, 2h, 1h30m.
	 * A plain number means minutes. Also used for the countdown and the Retry-After header.
	 * Without it, the mode stays on until `wp psmm off` (or the scheduled end from the settings page).
	 *
	 * ## EXAMPLES
	 *
	 *     wp psmm on
	 *     wp psmm on --for=10m
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function on( $args, $assoc_args ) {
		$expires_at = 0;

		if ( isset( $assoc_args['for'] ) ) {
			$expires_at = time() + $this->parse_duration( (string) $assoc_args['for'] );
		}

		$changes = array(
			'enabled'    => true,
			'expires_at' => $expires_at,
		);

		$settings = Settings::get();

		if ( '' === (string) $settings['bypass_key'] ) {
			$changes['bypass_key'] = Settings::generate_key();
		}

		// A scheduled end that has already passed would switch the mode straight back off.
		$scheduled = Settings::scheduled_end( $settings );
		if ( ! $expires_at && $settings['auto_disable'] && $scheduled && $scheduled <= time() ) {
			$changes['end_datetime'] = '';
		}

		Settings::update( $changes );

		if ( $expires_at ) {
			/* translators: %s: date and time */
			\WP_CLI::success( sprintf( __( 'Maintenance mode enabled until %s.', 'pavel-silinskii-maintenance-mode' ), $this->format_time( $expires_at ) ) );
		} else {
			\WP_CLI::success( __( 'Maintenance mode enabled.', 'pavel-silinskii-maintenance-mode' ) );
		}
	}

	/**
	 * Disable maintenance mode.
	 *
	 * ## EXAMPLES
	 *
	 *     wp psmm off
	 *
	 * @return void
	 */
	public function off() {
		Settings::update(
			array(
				'enabled'    => false,
				'expires_at' => 0,
			)
		);

		\WP_CLI::success( __( 'Maintenance mode disabled.', 'pavel-silinskii-maintenance-mode' ) );
	}

	/**
	 * Show the current state.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp psmm status
	 *     wp psmm status --format=json
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		$settings = Settings::get();
		$end      = Settings::end_timestamp( $settings );
		$key      = (string) $settings['bypass_key'];

		$item = array(
			'active'      => Settings::is_active() ? 'yes' : 'no',
			'enabled'     => $settings['enabled'] ? 'yes' : 'no',
			'ends_at'     => $end ? $this->format_time( $end ) : '',
			'auto_off'    => absint( $settings['expires_at'] ) || $settings['auto_disable'] ? 'yes' : 'no',
			'bypass_link' => '' !== $key ? add_query_arg( 'psmm_bypass', $key, home_url( '/' ) ) : '',
		);

		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', array( $item ), array_keys( $item ) );
	}

	/**
	 * Exit with code 0 if visitors currently see the maintenance page, 1 otherwise.
	 *
	 * ## EXAMPLES
	 *
	 *     wp psmm is-active && echo "Site is in maintenance"
	 *
	 * @subcommand is-active
	 *
	 * @return void
	 */
	public function is_active() {
		\WP_CLI::halt( Settings::is_active() ? 0 : 1 );
	}

	/**
	 * Parse 90s / 15m / 2h / 1h30m / 15 (minutes) into seconds.
	 *
	 * @param string $value Duration.
	 * @return int
	 */
	private function parse_duration( string $value ): int {
		$value = strtolower( trim( $value ) );

		if ( ctype_digit( $value ) ) {
			$seconds = (int) $value * MINUTE_IN_SECONDS;
		} elseif ( preg_match( '/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $m ) ) {
			$seconds = (int) ( $m[1] ?? 0 ) * HOUR_IN_SECONDS + (int) ( $m[2] ?? 0 ) * MINUTE_IN_SECONDS + (int) ( $m[3] ?? 0 );
		} else {
			$seconds = 0;
		}

		if ( $seconds <= 0 ) {
			/* translators: %s: value passed to --for */
			\WP_CLI::error( sprintf( __( 'Invalid duration "%s". Use e.g. 90s, 15m, 2h or 1h30m.', 'pavel-silinskii-maintenance-mode' ), $value ) );
		}

		return $seconds;
	}

	/**
	 * Format a timestamp in the site timezone.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private function format_time( int $timestamp ): string {
		return wp_date( 'Y-m-d H:i:s T', $timestamp );
	}
}
