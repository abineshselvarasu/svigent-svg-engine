<?php
/**
 * Plugin Name:       SVGFlow
 * Plugin URI:        https://wordpress.org/plugins/svg-flow/
 * Description:       Safe SVG Upload & Smart Inliner. Enable safe SVG & SVGZ uploads with automated DOM sanitization, clean Media Library thumbnail previews, automatic dimension detection, and high-performance client-side inline rendering.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Abinesh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       svg-flow
 * Domain Path:       /languages
 *
 * @package SVGFlow
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Version.
 */
define( 'SVG_FLOW_VERSION', '1.0.0' );

/**
 * Plugin File & Directory Constants.
 */
define( 'SVG_FLOW_FILE', __FILE__ );
define( 'SVG_FLOW_PATH', plugin_dir_path( __FILE__ ) );
define( 'SVG_FLOW_URL', plugin_dir_url( __FILE__ ) );
define( 'SVG_FLOW_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check PHP Version Compatibility.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: Minimum required PHP version */
							__( 'SVGFlow requires PHP version %s or higher to run securely. Please upgrade your PHP version.', 'svg-flow' ),
							'7.4'
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

/**
 * Load Composer Autoloader for Dependencies.
 */
if ( file_exists( SVG_FLOW_PATH . 'vendor/autoload.php' ) ) {
	require_once SVG_FLOW_PATH . 'vendor/autoload.php';
}

/**
 * Load Plugin Core Classes.
 */
require_once SVG_FLOW_PATH . 'includes/class-svgflow-core.php';

/**
 * Bootstrap SVGFlow.
 *
 * @since 1.0.0
 * @return SVGFlow_Core
 */
function svg_flow() {
	return SVGFlow_Core::get_instance();
}

// Initialize on plugins_loaded.
add_action( 'plugins_loaded', 'svg_flow' );
