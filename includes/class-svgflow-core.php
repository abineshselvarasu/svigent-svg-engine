<?php
/**
 * Core coordinator class for Svigent SVG Engine.
 *
 * @package SvigentTools
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SVGFlow_Core
 */
class SVGFlow_Core {

	/**
	 * Single instance of the class.
	 *
	 * @var SVGFlow_Core|null
	 */
	private static $instance = null;

	/**
	 * Sanitizer module instance.
	 *
	 * @var SVGFlow_Sanitizer|null
	 */
	public $sanitizer = null;

	/**
	 * Media integration module instance.
	 *
	 * @var SVGFlow_Media|null
	 */
	public $media = null;

	/**
	 * Admin settings module instance.
	 *
	 * @var SVGFlow_Admin|null
	 */
	public $admin = null;

	/**
	 * Frontend inline engine module instance.
	 *
	 * @var SVGFlow_Frontend|null
	 */
	public $frontend = null;

	/**
	 * Main instance getter (Singleton).
	 *
	 * @since  1.0.0
	 * @return SVGFlow_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_modules();
		$this->register_hooks();
	}

	/**
	 * Load required module classes.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function load_dependencies() {
		require_once SVIGENT_TOOLS_PATH . 'includes/class-svgflow-sanitizer.php';
		require_once SVIGENT_TOOLS_PATH . 'includes/class-svgflow-media.php';
		require_once SVIGENT_TOOLS_PATH . 'includes/class-svgflow-frontend.php';

		if ( is_admin() ) {
			require_once SVIGENT_TOOLS_PATH . 'includes/class-svgflow-admin.php';
		}
	}

	/**
	 * Instantiate modules.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function init_modules() {
		$this->sanitizer = new SVGFlow_Sanitizer();
		$this->media     = new SVGFlow_Media();
		$this->frontend  = new SVGFlow_Frontend();

		if ( is_admin() ) {
			$this->admin = new SVGFlow_Admin();
		}
	}

	/**
	 * Register core hooks.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	private function register_hooks() {
		add_filter( 'plugin_action_links_' . SVIGENT_TOOLS_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Add custom action links on the Plugins list screen.
	 *
	 * @since  1.0.0
	 * @param  array $links Array of plugin action links.
	 * @return array Modified array of action links.
	 */
	public function add_action_links( $links ) {
		$settings_url  = admin_url( 'options-general.php?page=svigent-tools' );
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'svigent-tools' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Retrieve plugin settings with fallback to defaults.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'upload_permission' => 'admin_only',
			'frontend_inlining' => false,
			'inline_selector'   => 'img.style-svg, img.svg-flow',
			'responsive_css'    => true,
		);

		$stored = get_option( 'svg_flow_settings', array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $defaults );
	}

	/**
	 * Retrieve a specific setting value.
	 *
	 * @since  1.0.0
	 * @param  string $key      Setting key.
	 * @param  mixed  $fallback Fallback value if key is not found.
	 * @return mixed
	 */
	public static function get_setting( $key, $fallback = null ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $fallback;
	}
}
