<?php
/**
 * Media library integration and dimension calculation module for Svigent SVG Engine.
 *
 * @package SvigentTools
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SVGFlow_Media
 */
class SVGFlow_Media {

	/**
	 * Per-request cache of attachment dimensions, keyed by attachment ID.
	 *
	 * @var array
	 */
	private $dimension_cache = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_svg_metadata' ), 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_svg_for_js' ), 10, 3 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'filter_attachment_image_src' ), 10, 4 );
		add_filter( 'admin_post_thumbnail_html', array( $this, 'wrap_featured_image' ), 10, 3 );
		add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'disable_srcset_for_svg' ), 10, 4 );
		add_filter( 'get_image_tag', array( $this, 'filter_get_image_tag' ), 10, 6 );
	}

	/**
	 * Enqueue admin styles for Media Library SVG display.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style(
			'svg-flow-media',
			SVIGENT_TOOLS_URL . 'assets/css/media.css',
			array(),
			SVIGENT_TOOLS_VERSION
		);
	}

	/**
	 * Parse and generate metadata for SVG attachments upon upload.
	 *
	 * @since  1.0.0
	 * @param  array $metadata      Attachment metadata.
	 * @param  int   $attachment_id Attachment ID.
	 * @return array
	 */
	public function generate_svg_metadata( $metadata, $attachment_id ) {
		$mime = get_post_mime_type( $attachment_id );

		if ( 'image/svg+xml' !== $mime ) {
			return $metadata;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		$dimensions = $this->get_svg_dimensions( $file_path );
		$upload_dir = wp_upload_dir();
		$relative   = str_replace( trailingslashit( $upload_dir['basedir'] ), '', $file_path );
		$filename   = basename( $file_path );

		if ( ! is_array( $metadata ) ) {
			$metadata = array();
		}

		$metadata['file']   = $relative;
		$metadata['width']  = $dimensions ? (int) $dimensions['width'] : 0;
		$metadata['height'] = $dimensions ? (int) $dimensions['height'] : 0;

		// Map intermediate sizes to the original SVG file to avoid raster thumb generation.
		$sizes = array();
		foreach ( get_intermediate_image_sizes() as $size_name ) {
			$sizes[ $size_name ] = array(
				'file'      => $filename,
				'width'     => $metadata['width'],
				'height'    => $metadata['height'],
				'mime-type' => $mime,
			);
		}

		$metadata['sizes'] = $sizes;

		return $metadata;
	}

	/**
	 * Supply SVG dimensions and thumbnail URLs to the JavaScript media modal model.
	 *
	 * @since  1.0.0
	 * @param  array       $response   Array of prepared attachment data.
	 * @param  WP_Post     $attachment Attachment object.
	 * @param  array|false $meta       Array of attachment meta data.
	 * @return array
	 */
	public function prepare_svg_for_js( $response, $attachment, $meta ) {
		if ( isset( $response['mime'] ) && 'image/svg+xml' === $response['mime'] ) {
			$dimensions = $this->get_attachment_dimensions( $attachment->ID );

			if ( $dimensions ) {
				$response['width']       = (int) $dimensions['width'];
				$response['height']      = (int) $dimensions['height'];
				$response['orientation'] = $dimensions['orientation'];
			}

			// Point all size options to original SVG URL for lossless rendering.
			$sizes = array();
			$url   = isset( $response['url'] ) ? $response['url'] : wp_get_attachment_url( $attachment->ID );

			foreach ( get_intermediate_image_sizes() as $size_name ) {
				$sizes[ $size_name ] = array(
					'url'         => $url,
					'width'       => isset( $response['width'] ) ? $response['width'] : 0,
					'height'      => isset( $response['height'] ) ? $response['height'] : 0,
					'orientation' => isset( $response['orientation'] ) ? $response['orientation'] : 'landscape',
				);
			}

			$response['sizes'] = $sizes;
			$response['icon']  = $url;
		}

		return $response;
	}

	/**
	 * Ensure image src functions receive proper width and height values for SVGs.
	 *
	 * @since  1.0.0
	 * @param  array|false  $image         [src, width, height, is_intermediate].
	 * @param  int          $attachment_id Attachment ID.
	 * @param  string|array $size          Requested image size.
	 * @param  bool         $icon          Whether icon is requested.
	 * @return array|false
	 */
	public function filter_attachment_image_src( $image, $attachment_id, $size, $icon ) {
		if ( is_array( $image ) && 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
			if ( empty( $image[1] ) || empty( $image[2] ) ) {
				$dimensions = $this->get_attachment_dimensions( $attachment_id );

				if ( $dimensions ) {
					$image[1] = (int) $dimensions['width'];
					$image[2] = (int) $dimensions['height'];
				}
			}
		}

		return $image;
	}

	/**
	 * Wrap SVG featured image markup with an identification container for admin styling.
	 *
	 * @since  1.0.0
	 * @param  string   $content       Featured image HTML.
	 * @param  int      $post_id       Current post ID.
	 * @param  int|null $thumbnail_id  Thumbnail attachment ID.
	 * @return string
	 */
	public function wrap_featured_image( $content, $post_id, $thumbnail_id = null ) {
		if ( $thumbnail_id && 'image/svg+xml' === get_post_mime_type( $thumbnail_id ) ) {
			return '<span class="svg-flow-featured-thumb">' . $content . '</span>';
		}
		return $content;
	}

	/**
	 * Disable srcset attribute computation on SVG attachments.
	 *
	 * @since  1.0.0
	 * @param  array  $image_meta    Attachment metadata.
	 * @param  array  $size_array    Requested size array.
	 * @param  string $image_src    Image source URL.
	 * @param  int    $attachment_id Attachment ID.
	 * @return array Empty array to prevent srcset generation.
	 */
	public function disable_srcset_for_svg( $image_meta, $size_array, $image_src, $attachment_id ) {
		if ( 'image/svg+xml' === get_post_mime_type( $attachment_id ) ) {
			return array();
		}
		return $image_meta;
	}

	/**
	 * Correct default width and height tags on inserted SVG markup.
	 *
	 * @since  1.0.0
	 * @param  string       $html  Image tag HTML.
	 * @param  int          $id    Attachment ID.
	 * @param  string       $alt   Alt text.
	 * @param  string       $title Image title.
	 * @param  string       $align Alignment class.
	 * @param  string|array $size  Requested size.
	 * @return string
	 */
	public function filter_get_image_tag( $html, $id, $alt, $title, $align, $size ) {
		if ( false !== strpos( $html, '="1"' ) && 'image/svg+xml' === get_post_mime_type( $id ) ) {
			$dimensions = $this->get_attachment_dimensions( $id );

			if ( $dimensions && ! empty( $dimensions['width'] ) && ! empty( $dimensions['height'] ) ) {
				$html = preg_replace( '/width="1"\s+/i', sprintf( 'width="%d" ', $dimensions['width'] ), $html );
				$html = preg_replace( '/height="1"\s+/i', sprintf( 'height="%d" ', $dimensions['height'] ), $html );
			}
		}

		return $html;
	}

	/**
	 * Get an SVG attachment's dimensions, preferring stored metadata over parsing the file.
	 *
	 * @since  1.0.3
	 * @param  int $attachment_id Attachment ID.
	 * @return array|false Array ['width' => ..., 'height' => ..., 'orientation' => ...] or false.
	 */
	public function get_attachment_dimensions( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( isset( $this->dimension_cache[ $attachment_id ] ) ) {
			return $this->dimension_cache[ $attachment_id ];
		}

		$meta = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
			$dimensions = array(
				'width'       => (int) $meta['width'],
				'height'      => (int) $meta['height'],
				'orientation' => ( $meta['width'] >= $meta['height'] ) ? 'landscape' : 'portrait',
			);
		} else {
			$dimensions = $this->get_svg_dimensions( get_attached_file( $attachment_id ) );
		}

		$this->dimension_cache[ $attachment_id ] = $dimensions;

		return $dimensions;
	}

	/**
	 * Extract SVG dimensions from viewBox or width/height XML attributes.
	 *
	 * @since  1.0.0
	 * @param  string $file_path Absolute path to SVG file.
	 * @return array|false Array ['width' => ..., 'height' => ..., 'orientation' => ...] or false.
	 */
	public function get_svg_dimensions( $file_path ) {
		if ( ! $file_path || ! is_readable( $file_path ) ) {
			return false;
		}

		$size = filesize( $file_path );
		if ( false === $size || $size > SVGFlow_Sanitizer::max_size() ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $file_path );

		if ( false === $content || '' === trim( $content ) ) {
			return false;
		}

		if ( SVGFlow_Sanitizer::is_gzipped( $content ) ) {
			$content = SVGFlow_Sanitizer::inflate( $content );
			if ( false === $content ) {
				return false;
			}
		}

		// LIBXML_NONET blocks any network access while parsing.
		$prev_errors = libxml_use_internal_errors( true );
		$xml         = simplexml_load_string( $content, 'SimpleXMLElement', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev_errors );

		if ( false === $xml ) {
			return false;
		}

		$attributes = $xml->attributes();
		$width      = 0;
		$height     = 0;

		if ( isset( $attributes['viewBox'] ) ) {
			$viewbox_parts = preg_split( '/[\s,]+/', trim( (string) $attributes['viewBox'] ) );
			if ( isset( $viewbox_parts[2], $viewbox_parts[3] ) ) {
				$width  = (float) $viewbox_parts[2];
				$height = (float) $viewbox_parts[3];
			}
		}

		// Fall back to numeric width/height when there is no usable viewBox.
		if ( ( empty( $width ) || empty( $height ) ) && isset( $attributes->width, $attributes->height ) ) {
			$w_str = (string) $attributes->width;
			$h_str = (string) $attributes->height;

			if ( '%' !== substr( $w_str, -1 ) && '%' !== substr( $h_str, -1 ) ) {
				$width  = (float) $w_str;
				$height = (float) $h_str;
			}
		}

		if ( empty( $width ) || empty( $height ) ) {
			return false;
		}

		return array(
			'width'       => round( $width ),
			'height'      => round( $height ),
			'orientation' => ( $width >= $height ) ? 'landscape' : 'portrait',
		);
	}
}
