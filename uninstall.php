<?php
/**
 * Uninstallation cleanup script for SVGFlow.
 *
 * Triggered automatically when the plugin is deleted from the WordPress admin.
 * Removes stored plugin options and metadata without leaving orphan database rows.
 *
 * @package SVGFlow
 * @since   1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Delete plugin settings option.
delete_option( 'svigent_tools_settings' );
delete_option( 'svg_flow_settings' );

// For WordPress multisite networks.
if ( is_multisite() ) {
	delete_site_option( 'svigent_tools_settings' );
	delete_site_option( 'svg_flow_settings' );
}
