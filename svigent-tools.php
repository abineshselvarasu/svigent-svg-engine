<?php
/**
 * Plugin Name:       Svigent SVG Engine – Safe SVG Upload & Inline SVG
 * Plugin URI:        https://wordpress.org/plugins/svigent-tools/
 * Description:       Safe SVG Upload & Smart Inliner Engine. Enable safe SVG & SVGZ uploads with automated DOM sanitization, clean Media Library thumbnail previews, automatic dimension detection, and high-performance client-side inline rendering.
 * Version:           1.0.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Abinesh Selvarasu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       svigent-tools
 * Domain Path:       /languages
 *
 * @package SvigentTools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Version.
 */
define( 'SVIGENT_TOOLS_VERSION', '1.0.2' );

/**
 * Plugin File & Directory Constants.
 */
define( 'SVIGENT_TOOLS_FILE', __FILE__ );
define( 'SVIGENT_TOOLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SVIGENT_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'SVIGENT_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check PHP Version Compatibility.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: Minimum required PHP version */
							__( 'Svigent SVG Engine requires PHP version %s or higher to run securely. Please upgrade your PHP version.', 'svigent-tools' ),
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
if ( file_exists( SVIGENT_TOOLS_PATH . 'vendor/autoload.php' ) ) {
	require_once SVIGENT_TOOLS_PATH . 'vendor/autoload.php';
}

/**
 * Load Plugin Core Classes.
 */
require_once SVIGENT_TOOLS_PATH . 'includes/class-svgflow-core.php';

/**
 * Bootstrap Svigent SVG Engine.
 *
 * @since 1.0.0
 * @return SVGFlow_Core
 */
function svigent_tools() {
	return SVGFlow_Core::get_instance();
}

// Initialize on plugins_loaded.
add_action( 'plugins_loaded', 'svigent_tools' );
