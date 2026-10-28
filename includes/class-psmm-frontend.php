<?php
/**
 * Front-end gate: decides who sees the maintenance page and serves it.
 *
 * @package PavelSilinskii\MaintenanceMode
 */

namespace PavelSilinskii\MaintenanceMode;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the maintenance page with 503 + Retry-After to everyone who cannot bypass it.
 *
 * Only front-end template requests are affected: wp-admin, wp-login.php, the REST API,
 * admin-ajax.php, WP-Cron and WP-CLI never reach template_redirect.
 */
class Frontend {

	/**
	 * Bypass cookie name.
	 */
	private const COOKIE = 'psmm_bypass';

	/**
	 * Query argument carrying the secret bypass key.
	 */
	private const BYPASS_ARG = 'psmm_bypass';

	/**
	 * Query argument that lets administrators preview the page.
	 */
	private const PREVIEW_ARG = 'psmm_preview';

	/**
	 * How long the bypass cookie lives.
	 */
	private const COOKIE_TTL = WEEK_IN_SECONDS;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_show' ), 0 );
	}

	/**
	 * Show the maintenance page if needed, otherwise let WordPress continue.
	 *
	 * @return void
	 */
	public function maybe_show() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview flag, gated by capability.
		if ( isset( $_GET[ self::PREVIEW_ARG ] ) && current_user_can( 'manage_options' ) ) {
			$this->render( true );
		}

		if ( ! Settings::is_active() ) {
			return;
		}

		$this->maybe_accept_bypass_key();

		if ( is_robots() || is_favicon() || $this->can_bypass() ) {
			return;
		}

		$this->render( false );
	}

	/**
	 * Whether the current visitor may see the live site.
	 *
	 * @return bool
	 */
	private function can_bypass(): bool {
		$settings = Settings::get();
		$allowed  = current_user_can( 'manage_options' )
			|| $this->has_bypass_role( (array) $settings['bypass_roles'] )
			|| $this->is_whitelisted_ip( (array) $settings['ip_whitelist'] )
			|| $this->has_bypass_cookie( (string) $settings['bypass_key'] );

		/**
		 * Filters whether the current visitor bypasses maintenance mode.
		 *
		 * @param bool $allowed Whether the visitor can see the live site.
		 */
		return (bool) apply_filters( 'psmm_can_bypass', $allowed );
	}

	/**
	 * Check the logged-in user's roles against the allowed list.
	 *
	 * @param string[] $roles Roles allowed to bypass.
	 * @return bool
	 */
	private function has_bypass_role( array $roles ): bool {
		if ( ! $roles || ! is_user_logged_in() ) {
			return false;
		}

		return (bool) array_intersect( $roles, (array) wp_get_current_user()->roles );
	}

	/**
	 * Check the visitor IP against the whitelist.
	 *
	 * Uses REMOTE_ADDR only — forwarded headers are client-controlled and easy to spoof.
	 *
	 * @param string[] $whitelist Allowed IP addresses.
	 * @return bool
	 */
	private function is_whitelisted_ip( array $whitelist ): bool {
		if ( ! $whitelist || empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return false;
		}

		$ip = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by filter_var.

		return $ip && in_array( $ip, $whitelist, true );
	}

	/**
	 * Check the bypass cookie set by the secret link.
	 *
	 * @param string $key Current bypass key.
	 * @return bool
	 */
	private function has_bypass_cookie( string $key ): bool {
		if ( '' === $key || empty( $_COOKIE[ self::COOKIE ] ) ) {
			return false;
		}

		return hash_equals( $this->cookie_value( $key ), (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared with hash_equals only.
	}

	/**
	 * If the URL carries a valid bypass key, set the cookie and redirect to the clean URL.
	 *
	 * @return void
	 */
	private function maybe_accept_bypass_key() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the secret key itself is the credential.
		if ( empty( $_GET[ self::BYPASS_ARG ] ) ) {
			return;
		}

		$key   = (string) Settings::get()['bypass_key'];
		$given = sanitize_text_field( wp_unslash( $_GET[ self::BYPASS_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $key || ! hash_equals( $key, $given ) ) {
			return;
		}

		setcookie( self::COOKIE, $this->cookie_value( $key ), time() + self::COOKIE_TTL, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		wp_safe_redirect( remove_query_arg( self::BYPASS_ARG ) );
		exit;
	}

	/**
	 * Cookie value derived from the key, so changing the key revokes every issued cookie.
	 *
	 * @param string $key Bypass key.
	 * @return string
	 */
	private function cookie_value( string $key ): string {
		return hash_hmac( 'sha256', $key, wp_salt( 'auth' ) );
	}

	/**
	 * Seconds a client should wait before retrying.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @param int|null             $end      Scheduled end timestamp.
	 * @return int
	 */
	private function retry_after( array $settings, ?int $end ): int {
		if ( $end && $end > time() ) {
			return $end - time();
		}

		return max( 1, absint( $settings['retry_after'] ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Send headers, output the maintenance page and stop.
	 *
	 * @param bool $preview Admin preview: sends 200 instead of 503.
	 * @return void
	 */
	private function render( bool $preview ) {
		$settings       = Settings::get();
		$end_timestamp  = Settings::end_timestamp( $settings );
		$show_countdown = $settings['show_countdown'] && $end_timestamp && $end_timestamp > time();

		// Tell page-cache plugins not to store this response.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- shared constant read by caching plugins, must keep this exact name.
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		if ( ! $preview ) {
			status_header( 503 );
			header( 'Retry-After: ' . $this->retry_after( $settings, $end_timestamp ) );
		}

		$this->register_assets( $settings, $show_countdown );

		$template = locate_template( 'psmm-maintenance.php' );

		/**
		 * Filters the maintenance page template path.
		 *
		 * A theme can also override it with a psmm-maintenance.php file.
		 *
		 * @param string $template Absolute path to the template.
		 */
		$template = apply_filters( 'psmm_template', $template ? $template : PSMM_PLUGIN_DIR . 'templates/maintenance.php' );

		include $template;
		exit;
	}

	/**
	 * Register the page stylesheet and countdown script; the template prints them.
	 *
	 * @param array<string, mixed> $settings       Plugin settings.
	 * @param bool                 $show_countdown Whether the countdown is shown.
	 * @return void
	 */
	private function register_assets( array $settings, bool $show_countdown ) {
		wp_register_style( 'psmm-frontend', PSMM_PLUGIN_URL . 'assets/css/psmm-frontend.css', array(), PSMM_VERSION );
		wp_add_inline_style(
			'psmm-frontend',
			sprintf(
				':root{--psmm-bg:%1$s;--psmm-text:%2$s;}',
				esc_html( $settings['background_color'] ),
				esc_html( $settings['text_color'] )
			)
		);

		if ( $show_countdown ) {
			wp_register_script( 'psmm-countdown', PSMM_PLUGIN_URL . 'assets/js/psmm-countdown.js', array(), PSMM_VERSION, true );
		}
	}
}
