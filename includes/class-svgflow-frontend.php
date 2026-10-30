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

			$upload_dir = wp_get_upload_dir();

			/**
			 * Filters the base URLs the inliner may fetch SVGs from.
			 *
			 * Images whose src falls outside these locations are left as plain <img> tags,
			 * so SVG markup from third-party hosts never lands in the page.
			 *
			 * @since 1.0.2
			 * @param string[] $allowed_urls Base URLs. Defaults to wp-content and the uploads folder.
			 */
			$allowed_urls = (array) apply_filters(
				'svg_flow_inline_allowed_urls',
				array_unique(
					array(
						trailingslashit( content_url() ),
						trailingslashit( $upload_dir['baseurl'] ),
					)
				)
			);

			wp_localize_script(
				'svg-flow-frontend',
				'svgFlowConfig',
				array(
					'selector'    => $selector,
					'allowedUrls' => array_values( array_map( 'esc_url_raw', $allowed_urls ) ),
				)
			);
		}
	}
}
