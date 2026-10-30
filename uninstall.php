<?php
/**
 * Removes the plugin's settings when it is deleted from the Plugins screen.
 *
 * @package SvigentTools
 * @since   1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( is_multisite() ) {
	$svigent_tools_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $svigent_tools_site_ids as $svigent_tools_site_id ) {
		delete_blog_option( $svigent_tools_site_id, 'svg_flow_settings' );
	}
} else {
	delete_option( 'svg_flow_settings' );
}
