<?php
/**
 * Maintenance page template.
 *
 * Override it by copying this file to your theme as psmm-maintenance.php.
 *
 * Available variables:
 *
 * @var array<string, mixed> $settings       Plugin settings.
 * @var int|null             $end_timestamp  Scheduled end (Unix time) or null.
 * @var bool                 $show_countdown Whether to render the countdown.
 *
 * @package PavelSilinskii\MaintenanceMode
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $settings['title'] . ' — ' . get_bloginfo( 'name' ) ); ?></title>
	<?php wp_print_styles( 'psmm-frontend' ); ?>
</head>
<body class="psmm-page">
	<main class="psmm-container">
		<?php if ( $settings['logo_url'] ) : ?>
			<img class="psmm-logo" src="<?php echo esc_url( $settings['logo_url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<?php endif; ?>

		<h1 class="psmm-title"><?php echo esc_html( $settings['title'] ); ?></h1>

		<div class="psmm-message"><?php echo wp_kses_post( wpautop( $settings['message'] ) ); ?></div>

		<?php if ( $show_countdown ) : ?>
			<div class="psmm-countdown" data-end="<?php echo esc_attr( (string) $end_timestamp ); ?>" aria-live="polite">
				<?php
				$psmm_units = array(
					'days'    => __( 'Days', 'pavel-silinskii-maintenance-mode' ),
					'hours'   => __( 'Hours', 'pavel-silinskii-maintenance-mode' ),
					'minutes' => __( 'Minutes', 'pavel-silinskii-maintenance-mode' ),
					'seconds' => __( 'Seconds', 'pavel-silinskii-maintenance-mode' ),
				);
				foreach ( $psmm_units as $psmm_unit => $psmm_label ) :
					?>
					<div class="psmm-countdown__item">
						<span class="psmm-countdown__value" data-unit="<?php echo esc_attr( $psmm_unit ); ?>">--</span>
						<span class="psmm-countdown__label"><?php echo esc_html( $psmm_label ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</main>
	<?php
	if ( $show_countdown ) {
		wp_print_scripts( 'psmm-countdown' );
	}
	?>
</body>
</html>
