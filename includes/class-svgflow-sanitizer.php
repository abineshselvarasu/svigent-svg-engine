<?php
/**
 * Sanitization and MIME validation module for SVGFlow.
 *
 * @package SVGFlow
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

use enshrined\svgSanitize\Sanitizer;

/**
 * Class SVGFlow_Sanitizer
 */
class SVGFlow_Sanitizer {

	/**
	 * Sanitizer library instance.
	 *
	 * @var Sanitizer|null
	 */
	protected $sanitizer = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( class_exists( 'enshrined\svgSanitize\Sanitizer' ) ) {
			$this->sanitizer = new Sanitizer();
		}

		add_filter( 'upload_mimes', array( $this, 'filter_upload_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'filter_filetype_and_ext' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'process_upload_prefilter' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'process_upload_prefilter' ) );
	}

	/**
	 * Check if current user has permission to upload SVG files.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function can_user_upload() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		$permission = SVGFlow_Core::get_setting( 'upload_permission', 'admin_only' );
		$can_upload = true;

		if ( 'admin_only' === $permission ) {
			$can_upload = current_user_can( 'manage_options' );
		}

		/**
		 * Filter to customize SVG upload authorization.
		 *
		 * @since 1.0.0
		 * @param bool $can_upload          Whether current user can upload SVGs.
		 * @param int  $current_user_id     Current user ID.
		 */
		return (bool) apply_filters( 'svg_flow_user_can_upload', $can_upload, get_current_user_id() );
	}

	/**
	 * Register SVG and SVGZ MIME types if user is permitted.
	 *
	 * @since  1.0.0
	 * @param  array $mimes Allowed MIME types.
	 * @return array Modified MIME types.
	 */
	public function filter_upload_mimes( $mimes ) {
		if ( $this->can_user_upload() ) {
			$mimes['svg']  = 'image/svg+xml';
			$mimes['svgz'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * Normalize SVG filetype check in WordPress upload verification.
	 *
	 * @since  1.0.0
	 * @param  array  $data     File data ['ext' => '', 'type' => '', 'proper_filename' => ''].
	 * @param  string $file     Path to uploaded temp file.
	 * @param  string $filename Original filename.
	 * @param  array  $mimes    Allowed MIME types array.
	 * @return array
	 */
	public function filter_filetype_and_ext( $data, $file, $filename, $mimes ) {
		$ext = isset( $data['ext'] ) ? $data['ext'] : '';

		if ( empty( $ext ) && ! empty( $filename ) ) {
			$path_info = pathinfo( $filename );
			$ext       = isset( $path_info['extension'] ) ? strtolower( $path_info['extension'] ) : '';
		}

		if ( 'svg' === $ext ) {
			$data['type'] = 'image/svg+xml';
			$data['ext']  = 'svg';
		} elseif ( 'svgz' === $ext ) {
			$data['type'] = 'image/svg+xml';
			$data['ext']  = 'svgz';
		}

		return $data;
	}

	/**
	 * Inspect and sanitize uploaded SVG files before saving to uploads folder.
	 *
	 * @since  1.0.0
	 * @param  array $file Uploaded file array ['name' => ..., 'type' => ..., 'tmp_name' => ..., 'error' => ...].
	 * @return array
	 */
	public function process_upload_prefilter( $file ) {
		if ( ! isset( $file['tmp_name'] ) || empty( $file['tmp_name'] ) ) {
			return $file;
		}

		$filename = isset( $file['name'] ) ? $file['name'] : '';
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'svg', 'svgz' ), true ) ) {
			return $file;
		}

		// Verify permission.
		if ( ! $this->can_user_upload() ) {
			$file['error'] = esc_html__( 'Sorry, you do not have permission to upload SVG files.', 'svigent-tools' );
			return $file;
		}

		$sanitization_enabled = SVGFlow_Core::get_setting( 'sanitization_enabled', true );

		if ( ! $sanitization_enabled ) {
			return $file;
		}

		// Execute sanitization.
		$sanitized = $this->sanitize_file( $file['tmp_name'] );

		if ( ! $sanitized ) {
			$file['error'] = esc_html__( 'Security Notice: This SVG file contains invalid or unsafe XML and could not be uploaded.', 'svigent-tools' );
			return $file;
		}

		return $file;
	}

	/**
	 * Perform DOM-level sanitization on an SVG file.
	 *
	 * @since  1.0.0
	 * @param  string $file_path Absolute path to the temporary file.
	 * @return bool True on success, false on invalid/unsafe XML.
	 */
	public function sanitize_file( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		if ( ! $this->sanitizer ) {
			if ( class_exists( 'enshrined\svgSanitize\Sanitizer' ) ) {
				$this->sanitizer = new Sanitizer();
			} else {
				return false;
			}
		}

		// Read file content safely.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw_content = file_get_contents( $file_path );

		if ( false === $raw_content || '' === trim( $raw_content ) ) {
			return false;
		}

		$is_gzipped = $this->is_gzipped( $raw_content );

		if ( $is_gzipped ) {
			$raw_content = gzdecode( $raw_content );
			if ( false === $raw_content ) {
				return false;
			}
		}

		/**
		 * Configure Sanitizer options.
		 */
		$this->sanitizer->removeXMLTag( true );
		$this->sanitizer->minify( false );

		/**
		 * Filters for custom allowed tags and attributes.
		 */
		$custom_tags = apply_filters( 'svg_flow_allowed_tags', null );
		if ( is_array( $custom_tags ) ) {
			$this->sanitizer->setAllowedTags( $custom_tags );
		}

		$custom_attrs = apply_filters( 'svg_flow_allowed_attributes', null );
		if ( is_array( $custom_attrs ) ) {
			$this->sanitizer->setAllowedAttrs( $custom_attrs );
		}

		$clean_content = $this->sanitizer->sanitize( $raw_content );

		if ( false === $clean_content || '' === trim( $clean_content ) ) {
			return false;
		}

		// Re-encode if original file was gzipped.
		if ( $is_gzipped ) {
			$clean_content = gzencode( $clean_content );
		}

		// Write sanitized content back to temporary file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		$result = file_put_contents( $file_path, $clean_content );

		return ( false !== $result );
	}

	/**
	 * Check if binary string matches GZIP compression header.
	 *
	 * @since  1.0.0
	 * @param  string $content Raw file content.
	 * @return bool
	 */
	public function is_gzipped( $content ) {
		if ( function_exists( 'mb_strpos' ) ) {
			return 0 === mb_strpos( $content, "\x1f\x8b\x08" );
		}
		return 0 === strpos( $content, "\x1f\x8b\x08" );
	}
}
