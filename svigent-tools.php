<?php
/**
 * Plugin Name:       Svigent SVG Engine
 * Plugin URI:        https://wordpress.org/plugins/svigent-tools/
 * Description:       Safe SVG Upload & Smart Inliner Engine. Enable safe SVG & SVGZ uploads with automated DOM sanitization, clean Media Library thumbnail previews, automatic dimension detection, and high-performance client-side inline rendering.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Abinesh Selvarasu
 * Author URI:        https://www.abineshspotlight.online
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       svigent-tools
 * Domain Path:       /languages
 *
 * @package SvigentTools
 */

defined( 'ABSPATH' ) || exit;

define( 'SVIGENT_TOOLS_VERSION', '1.0.3' );
define( 'SVIGENT_TOOLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SVIGENT_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'SVIGENT_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

if ( file_exists( SVIGENT_TOOLS_PATH . 'vendor/autoload.php' ) ) {
	require_once SVIGENT_TOOLS_PATH . 'vendor/autoload.php';
}

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

add_action( 'plugins_loaded', 'svigent_tools' );
