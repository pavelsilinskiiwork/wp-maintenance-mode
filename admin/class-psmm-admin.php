<?php
/**
 * Admin settings page.
 *
 * @package PavelSilinskii\MaintenanceMode
 */

namespace PavelSilinskii\MaintenanceMode;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Settings > Maintenance Mode page and its fields.
 */
class Admin {

	/**
	 * Settings page slug.
	 */
	public const PAGE = 'psmm-settings';

	/**
	 * Settings group.
	 */
	private const GROUP = 'psmm_settings_group';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PSMM_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add the settings page under Settings.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'Maintenance Mode Settings', 'pavel-silinskii-maintenance-mode' ),
			__( 'Maintenance Mode', 'pavel-silinskii-maintenance-mode' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
				esc_html__( 'Settings', 'pavel-silinskii-maintenance-mode' )
			)
		);

		return $links;
	}

	/**
	 * Register the setting, sections and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) )
		);

		add_settings_section( 'psmm_general', __( 'General', 'pavel-silinskii-maintenance-mode' ), '__return_false', self::PAGE );
		add_settings_section( 'psmm_content', __( 'Page Content', 'pavel-silinskii-maintenance-mode' ), '__return_false', self::PAGE );
		add_settings_section( 'psmm_appearance', __( 'Appearance', 'pavel-silinskii-maintenance-mode' ), '__return_false', self::PAGE );
		add_settings_section( 'psmm_access', __( 'Access', 'pavel-silinskii-maintenance-mode' ), array( $this, 'render_access_section' ), self::PAGE );

		$fields = array(
			'enabled'          => array( __( 'Maintenance mode', 'pavel-silinskii-maintenance-mode' ), 'psmm_general' ),
			'end_datetime'     => array( __( 'Expected end', 'pavel-silinskii-maintenance-mode' ), 'psmm_general' ),
			'auto_disable'     => array( __( 'Auto-disable', 'pavel-silinskii-maintenance-mode' ), 'psmm_general' ),
			'retry_after'      => array( __( 'Retry-After', 'pavel-silinskii-maintenance-mode' ), 'psmm_general' ),
			'title'            => array( __( 'Title', 'pavel-silinskii-maintenance-mode' ), 'psmm_content' ),
			'message'          => array( __( 'Message', 'pavel-silinskii-maintenance-mode' ), 'psmm_content' ),
			'show_countdown'   => array( __( 'Countdown', 'pavel-silinskii-maintenance-mode' ), 'psmm_content' ),
			'logo_url'         => array( __( 'Logo', 'pavel-silinskii-maintenance-mode' ), 'psmm_appearance' ),
			'background_color' => array( __( 'Background color', 'pavel-silinskii-maintenance-mode' ), 'psmm_appearance' ),
			'text_color'       => array( __( 'Text color', 'pavel-silinskii-maintenance-mode' ), 'psmm_appearance' ),
			'bypass_roles'     => array( __( 'Roles with access', 'pavel-silinskii-maintenance-mode' ), 'psmm_access' ),
			'ip_whitelist'     => array( __( 'IP whitelist', 'pavel-silinskii-maintenance-mode' ), 'psmm_access' ),
			'bypass_key'       => array( __( 'Secret bypass link', 'pavel-silinskii-maintenance-mode' ), 'psmm_access' ),
		);

		$no_label_for = array( 'enabled', 'auto_disable', 'show_countdown', 'message', 'bypass_roles' );

		foreach ( $fields as $key => list( $title, $section ) ) {
			$args = in_array( $key, $no_label_for, true ) ? array() : array( 'label_for' => 'psmm-' . str_replace( '_', '-', $key ) );

			add_settings_field( 'psmm_' . $key, $title, array( $this, "render_{$key}_field" ), self::PAGE, $section, $args );
		}
	}

	/**
	 * Sanitize and whitelist submitted settings.
	 *
	 * @param mixed $input Submitted values.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = Settings::DEFAULTS;

		$end_datetime = isset( $input['end_datetime'] ) ? sanitize_text_field( $input['end_datetime'] ) : '';
		if ( '' !== $end_datetime && ! \DateTimeImmutable::createFromFormat( Settings::DATETIME_FORMAT, $end_datetime, wp_timezone() ) ) {
			$end_datetime = '';
		}

		$roles = isset( $input['bypass_roles'] ) ? array_map( 'sanitize_key', (array) $input['bypass_roles'] ) : array();

		$ips = isset( $input['ip_whitelist'] ) ? preg_split( '/[\s,]+/', (string) $input['ip_whitelist'], -1, PREG_SPLIT_NO_EMPTY ) : array();
		$ips = array_filter( (array) $ips, static fn( $ip ) => false !== filter_var( $ip, FILTER_VALIDATE_IP ) );

		$bypass_key = isset( $input['bypass_key'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $input['bypass_key'] ) : '';

		return array(
			'enabled'          => ! empty( $input['enabled'] ),
			'title'            => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $defaults['title'],
			'message'          => isset( $input['message'] ) ? wp_kses_post( $input['message'] ) : $defaults['message'],
			'end_datetime'     => $end_datetime,
			'show_countdown'   => ! empty( $input['show_countdown'] ),
			'auto_disable'     => ! empty( $input['auto_disable'] ),
			'retry_after'      => isset( $input['retry_after'] ) ? min( WEEK_IN_SECONDS / MINUTE_IN_SECONDS, max( 1, absint( $input['retry_after'] ) ) ) : $defaults['retry_after'],
			'logo_url'         => isset( $input['logo_url'] ) ? esc_url_raw( $input['logo_url'] ) : '',
			'background_color' => $this->sanitize_color( $input, 'background_color' ),
			'text_color'       => $this->sanitize_color( $input, 'text_color' ),
			'bypass_roles'     => array_values( array_intersect( $roles, array_keys( wp_roles()->roles ) ) ),
			'ip_whitelist'     => array_values( array_unique( $ips ) ),
			'bypass_key'       => '' !== $bypass_key ? $bypass_key : Settings::generate_key(),
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap psmm-admin">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<a href="<?php echo esc_url( add_query_arg( 'psmm_preview', '1', home_url( '/' ) ) ); ?>" class="button" target="_blank" rel="noopener">
					<?php esc_html_e( 'Preview maintenance page', 'pavel-silinskii-maintenance-mode' ); ?>
				</a>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Access section intro.
	 *
	 * @return void
	 */
	public function render_access_section() {
		echo '<p>' . esc_html__( 'Administrators always see the live site. Everyone else gets the maintenance page unless allowed below.', 'pavel-silinskii-maintenance-mode' ) . '</p>';
	}

	/**
	 * Render the enabled toggle.
	 *
	 * @return void
	 */
	public function render_enabled_field() {
		$this->render_checkbox( 'enabled', __( 'Enable maintenance mode', 'pavel-silinskii-maintenance-mode' ) );
		echo '<p class="description">' . esc_html__( 'Visitors get the maintenance page with HTTP status 503 Service Unavailable.', 'pavel-silinskii-maintenance-mode' ) . '</p>';
	}

	/**
	 * Render the end date input.
	 *
	 * @return void
	 */
	public function render_end_datetime_field() {
		printf(
			'<input type="datetime-local" id="psmm-end-datetime" name="%1$s[end_datetime]" value="%2$s" /><p class="description">%3$s</p>',
			esc_attr( Settings::OPTION ),
			esc_attr( $this->get_settings()['end_datetime'] ),
			esc_html(
				sprintf(
					/* translators: %s: site timezone, e.g. Europe/Berlin or UTC+2 */
					__( 'Optional. Site timezone: %s. Used for the countdown and the Retry-After header.', 'pavel-silinskii-maintenance-mode' ),
					wp_timezone_string()
				)
			)
		);
	}

	/**
	 * Render the auto-disable toggle.
	 *
	 * @return void
	 */
	public function render_auto_disable_field() {
		$this->render_checkbox( 'auto_disable', __( 'Turn maintenance mode off automatically at the expected end', 'pavel-silinskii-maintenance-mode' ) );
	}

	/**
	 * Render the Retry-After minutes input.
	 *
	 * @return void
	 */
	public function render_retry_after_field() {
		printf(
			'<input type="number" id="psmm-retry-after" name="%1$s[retry_after]" value="%2$d" min="1" max="10080" class="small-text" /> %3$s<p class="description">%4$s</p>',
			esc_attr( Settings::OPTION ),
			absint( $this->get_settings()['retry_after'] ),
			esc_html__( 'minutes', 'pavel-silinskii-maintenance-mode' ),
			esc_html__( 'Tells search engines when to come back. Used when no expected end is set.', 'pavel-silinskii-maintenance-mode' )
		);
	}

	/**
	 * Render the title input.
	 *
	 * @return void
	 */
	public function render_title_field() {
		printf(
			'<input type="text" id="psmm-title" name="%1$s[title]" value="%2$s" class="regular-text" />',
			esc_attr( Settings::OPTION ),
			esc_attr( $this->get_settings()['title'] )
		);
	}

	/**
	 * Render the message editor.
	 *
	 * @return void
	 */
	public function render_message_field() {
		wp_editor(
			$this->get_settings()['message'],
			'psmm-message',
			array(
				'textarea_name' => Settings::OPTION . '[message]',
				'textarea_rows' => 6,
				'media_buttons' => false,
				'teeny'         => true,
			)
		);
	}

	/**
	 * Render the countdown toggle.
	 *
	 * @return void
	 */
	public function render_show_countdown_field() {
		$this->render_checkbox( 'show_countdown', __( 'Show a countdown to the expected end', 'pavel-silinskii-maintenance-mode' ) );
	}

	/**
	 * Render the logo URL input with a media library button.
	 *
	 * @return void
	 */
	public function render_logo_url_field() {
		$logo_url = $this->get_settings()['logo_url'];

		printf(
			'<div class="psmm-logo-field"><input type="url" id="psmm-logo-url" name="%1$s[logo_url]" value="%2$s" class="regular-text" /> <button type="button" class="button psmm-logo-select">%3$s</button> <button type="button" class="button-link psmm-logo-remove">%4$s</button><div class="psmm-logo-preview">%5$s</div></div>',
			esc_attr( Settings::OPTION ),
			esc_attr( $logo_url ),
			esc_html__( 'Select image', 'pavel-silinskii-maintenance-mode' ),
			esc_html__( 'Remove', 'pavel-silinskii-maintenance-mode' ),
			$logo_url ? '<img src="' . esc_url( $logo_url ) . '" alt="" />' : ''
		);
	}

	/**
	 * Render the background color picker.
	 *
	 * @return void
	 */
	public function render_background_color_field() {
		$this->render_color( 'background_color' );
	}

	/**
	 * Render the text color picker.
	 *
	 * @return void
	 */
	public function render_text_color_field() {
		$this->render_color( 'text_color' );
	}

	/**
	 * Render the role checkboxes. Administrators are omitted — they always have access.
	 *
	 * @return void
	 */
	public function render_bypass_roles_field() {
		$current = (array) $this->get_settings()['bypass_roles'];

		echo '<fieldset class="psmm-checkbox-group">';

		foreach ( wp_roles()->get_names() as $role => $name ) {
			if ( 'administrator' === $role ) {
				continue;
			}

			printf(
				'<label><input type="checkbox" name="%1$s[bypass_roles][]" value="%2$s"%3$s /> %4$s</label>',
				esc_attr( Settings::OPTION ),
				esc_attr( $role ),
				checked( in_array( $role, $current, true ), true, false ),
				esc_html( translate_user_role( $name ) )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Render the IP whitelist textarea.
	 *
	 * @return void
	 */
	public function render_ip_whitelist_field() {
		$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by filter_var.

		printf(
			'<textarea id="psmm-ip-whitelist" name="%1$s[ip_whitelist]" rows="4" class="regular-text code">%2$s</textarea><p class="description">%3$s</p>',
			esc_attr( Settings::OPTION ),
			esc_textarea( implode( "\n", (array) $this->get_settings()['ip_whitelist'] ) ),
			esc_html(
				$remote_ip
					/* translators: %s: visitor IP address */
					? sprintf( __( 'One IPv4 or IPv6 address per line. Your current IP: %s', 'pavel-silinskii-maintenance-mode' ), $remote_ip )
					: __( 'One IPv4 or IPv6 address per line.', 'pavel-silinskii-maintenance-mode' )
			)
		);
	}

	/**
	 * Render the bypass key input and the resulting link.
	 *
	 * @return void
	 */
	public function render_bypass_key_field() {
		$key = (string) $this->get_settings()['bypass_key'];

		printf(
			'<input type="text" id="psmm-bypass-key" name="%1$s[bypass_key]" value="%2$s" class="regular-text code" autocomplete="off" /><p class="description">%3$s</p>',
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			esc_html__( 'Letters and digits only. Clear the field and save to generate a new key — this revokes all previously shared links.', 'pavel-silinskii-maintenance-mode' )
		);

		if ( '' !== $key ) {
			$link = add_query_arg( 'psmm_bypass', $key, home_url( '/' ) );

			printf(
				'<p class="psmm-bypass-link"><code>%1$s</code></p><p class="description">%2$s</p>',
				esc_html( $link ),
				esc_html__( 'Share this link with clients or testers. Opening it gives that browser access to the live site for 7 days.', 'pavel-silinskii-maintenance-mode' )
			);
		}
	}

	/**
	 * Enqueue admin assets on the settings page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'psmm-admin', PSMM_PLUGIN_URL . 'assets/css/psmm-admin.css', array(), PSMM_VERSION );
		wp_enqueue_script( 'psmm-admin', PSMM_PLUGIN_URL . 'assets/js/psmm-admin.js', array( 'jquery', 'wp-color-picker' ), PSMM_VERSION, true );
		wp_localize_script(
			'psmm-admin',
			'psmmAdmin',
			array(
				'mediaTitle'  => __( 'Select logo', 'pavel-silinskii-maintenance-mode' ),
				'mediaButton' => __( 'Use this image', 'pavel-silinskii-maintenance-mode' ),
			)
		);
	}

	/**
	 * Get saved settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings(): array {
		return Settings::get();
	}

	/**
	 * Return a submitted hex color, or the default if invalid.
	 *
	 * @param array  $input Submitted values.
	 * @param string $key   Setting key.
	 * @return string
	 */
	private function sanitize_color( array $input, string $key ): string {
		$color = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : null;

		return $color ? $color : Settings::DEFAULTS[ $key ];
	}

	/**
	 * Render a single checkbox.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Checkbox label.
	 * @return void
	 */
	private function render_checkbox( string $key, string $label ) {
		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1"%3$s /> %4$s</label>',
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			checked( ! empty( $this->get_settings()[ $key ] ), true, false ),
			esc_html( $label )
		);
	}

	/**
	 * Render a color picker input.
	 *
	 * @param string $key Setting key.
	 * @return void
	 */
	private function render_color( string $key ) {
		printf(
			'<input type="text" id="psmm-%1$s" name="%2$s[%3$s]" value="%4$s" class="psmm-color" data-default-color="%5$s" />',
			esc_attr( str_replace( '_', '-', $key ) ),
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			esc_attr( $this->get_settings()[ $key ] ),
			esc_attr( Settings::DEFAULTS[ $key ] )
		);
	}
}
