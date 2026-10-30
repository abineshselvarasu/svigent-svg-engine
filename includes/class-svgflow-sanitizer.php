<?php
/**
 * Sanitization and MIME validation module for Svigent SVG Engine.
 *
 * @package SvigentTools
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

use enshrined\svgSanitize\Sanitizer;
use enshrined\svgSanitize\data\AllowedAttributes;
use enshrined\svgSanitize\data\AllowedTags;
use enshrined\svgSanitize\data\AttributeInterface;
use enshrined\svgSanitize\data\TagInterface;

/**
 * Class SVGFlow_Sanitizer
 */
class SVGFlow_Sanitizer {

	/**
	 * Default maximum size (in bytes) a compressed .svgz file may inflate to.
	 *
	 * @since 1.0.2
	 */
	const DEFAULT_MAX_INFLATED_SIZE = 10485760;

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
	public function filter_filetype_and_ext( $data, $file, $filename, $mimes ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'svg', 'svgz' ), true ) || ! $this->can_user_upload() ) {
			return $data;
		}

		// Only vouch for the file when its bytes really are SVG (or gzip for .svgz).
		if ( ! $this->looks_like_svg( $file, $ext ) ) {
			return $data;
		}

		$data['type'] = 'image/svg+xml';
		$data['ext']  = $ext;

		return $data;
	}

	/**
	 * Check a file's content matches its SVG/SVGZ extension.
	 *
	 * @since  1.0.3
	 * @param  string $file_path Path to the file.
	 * @param  string $ext       'svg' or 'svgz'.
	 * @return bool
	 */
	protected function looks_like_svg( $file_path, $ext ) {
		if ( ! is_string( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$head = file_get_contents( $file_path, false, null, 0, 4096 );
		if ( false === $head ) {
			return false;
		}

		if ( 'svgz' === $ext ) {
			return self::is_gzipped( $head );
		}

		return false !== stripos( $head, '<svg' );
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

		if ( ! $this->can_user_upload() ) {
			$file['error'] = esc_html__( 'Sorry, you do not have permission to upload SVG files.', 'svigent-tools' );
			return $file;
		}

		if ( ! $this->sanitize_file( $file['tmp_name'] ) ) {
			$file['error'] = esc_html__( 'Security Notice: This SVG file contains invalid or unsafe XML and could not be uploaded.', 'svigent-tools' );
			return $file;
		}

		clearstatcache( true, $file['tmp_name'] );
		$file['size'] = filesize( $file['tmp_name'] );

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
		clearstatcache( true, $file_path );
		$size = filesize( $file_path );
		if ( false === $size || $size > self::max_size() ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw_content = file_get_contents( $file_path );

		if ( false === $raw_content || '' === trim( $raw_content ) ) {
			return false;
		}

		$is_gzipped = self::is_gzipped( $raw_content );

		if ( $is_gzipped ) {
			$raw_content = self::inflate( $raw_content );
			if ( false === $raw_content ) {
				return false;
			}
		}

		$this->sanitizer->removeXMLTag( true );
		$this->sanitizer->minify( false );
		$this->sanitizer->removeRemoteReferences( true );

		/**
		 * Filters the SVG tags kept during sanitization.
		 *
		 * @since 1.0.0
		 * @since 1.0.2 Receives the library's default list instead of null.
		 * @param string[] $tags Allowed tag names.
		 */
		$custom_tags = apply_filters( 'svg_flow_allowed_tags', AllowedTags::getTags() );
		if ( is_array( $custom_tags ) ) {
			$tag_list        = new class() implements TagInterface {
				/**
				 * Allowed tag names.
				 *
				 * @var string[]
				 */
				public static $tags = array();

				/**
				 * Return allowed tag names.
				 *
				 * @return string[]
				 */
				public static function getTags() {
					return self::$tags;
				}
			};
			$tag_list::$tags = array_values( array_filter( $custom_tags, 'is_string' ) );
			$this->sanitizer->setAllowedTags( $tag_list );
		}

		/**
		 * Filters the SVG attributes kept during sanitization.
		 *
		 * @since 1.0.0
		 * @since 1.0.2 Receives the library's default list instead of null.
		 * @param string[] $attributes Allowed attribute names.
		 */
		$custom_attrs = apply_filters( 'svg_flow_allowed_attributes', AllowedAttributes::getAttributes() );
		if ( is_array( $custom_attrs ) ) {
			$attr_list              = new class() implements AttributeInterface {
				/**
				 * Allowed attribute names.
				 *
				 * @var string[]
				 */
				public static $attributes = array();

				/**
				 * Return allowed attribute names.
				 *
				 * @return string[]
				 */
				public static function getAttributes() {
					return self::$attributes;
				}
			};
			$attr_list::$attributes = array_values( array_filter( $custom_attrs, 'is_string' ) );
			$this->sanitizer->setAllowedAttrs( $attr_list );
		}

		$clean_content = $this->sanitizer->sanitize( $raw_content );

		// Anything that is not an <svg> document after cleaning is rejected outright.
		if ( ! is_string( $clean_content ) || false === stripos( $clean_content, '<svg' ) ) {
			return false;
		}

		if ( $is_gzipped ) {
			$clean_content = gzencode( $clean_content );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $file_path, $clean_content );

		return ( false !== $result );
	}

	/**
	 * Decompress gzip content in chunks, refusing anything that inflates past the size limit.
	 *
	 * Guards against decompression bombs: a tiny .svgz that expands to gigabytes of XML.
	 *
	 * @since  1.0.2
	 * @param  string $compressed Gzip-encoded content.
	 * @return string|false Decompressed content, or false if invalid or too large.
	 */
	public static function inflate( $compressed ) {
		if ( ! function_exists( 'inflate_init' ) ) {
			return false;
		}

		$max_size = self::max_size();

		$context = inflate_init( ZLIB_ENCODING_GZIP );
		if ( false === $context ) {
			return false;
		}

		$output = '';
		$length = strlen( $compressed );

		for ( $offset = 0; $offset < $length; $offset += 8192 ) {
			$chunk = inflate_add( $context, substr( $compressed, $offset, 8192 ), ZLIB_SYNC_FLUSH );
			if ( false === $chunk ) {
				return false;
			}

			$output .= $chunk;
			if ( strlen( $output ) > $max_size ) {
				return false;
			}
		}

		$tail = inflate_add( $context, '', ZLIB_FINISH );
		if ( false === $tail ) {
			return false;
		}

		$output .= $tail;

		return ( strlen( $output ) > $max_size ) ? false : $output;
	}

	/**
	 * Maximum size (in bytes) an SVG may have, or an .svgz may decompress to.
	 *
	 * @since  1.0.3
	 * @return int
	 */
	public static function max_size() {
		/**
		 * Filters the maximum SVG size in bytes, also used as the .svgz decompression limit.
		 *
		 * @since 1.0.2
		 * @param int $max_size Maximum size in bytes. Default 10 MB.
		 */
		return (int) apply_filters( 'svg_flow_max_inflated_size', self::DEFAULT_MAX_INFLATED_SIZE );
	}

	/**
	 * Check if binary string starts with a GZIP header.
	 *
	 * @since  1.0.0
	 * @param  string $content Raw file content.
	 * @return bool
	 */
	public static function is_gzipped( $content ) {
		return 0 === strncmp( $content, "\x1f\x8b\x08", 3 );
	}
}
