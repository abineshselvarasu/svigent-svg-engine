<?php
/**
 * Frontend script enqueuing and inlining configuration module for SVGFlow.
 *
 * @package SVGFlow
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SVGFlow_Frontend
 */
class SVGFlow_Frontend {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue frontend JavaScript and responsive styles if enabled.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		$settings = SVGFlow_Core::get_settings();

		// Enqueue responsive CSS if enabled.
		if ( ! empty( $settings['responsive_css'] ) ) {
			wp_enqueue_style(
				'svg-flow-frontend',
				SVIGENT_TOOLS_URL . 'assets/css/frontend.css',
				array(),
				SVIGENT_TOOLS_VERSION
			);
		}

		// Enqueue inline replacement script only if feature is enabled.
		if ( ! empty( $settings['frontend_inlining'] ) ) {
			wp_enqueue_script(
				'svg-flow-frontend',
				SVIGENT_TOOLS_URL . 'assets/js/frontend.js',
				array(),
				SVIGENT_TOOLS_VERSION,
				true
			);

			$selector = ! empty( $settings['inline_selector'] )
				? sanitize_text_field( $settings['inline_selector'] )
				: 'img.style-svg, img.svg-flow';

			wp_localize_script(
				'svg-flow-frontend',
				'svgFlowConfig',
				array(
					'selector' => $selector,
				)
			);
		}
	}
}
